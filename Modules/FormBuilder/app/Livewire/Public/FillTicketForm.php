<?php

namespace Modules\FormBuilder\Livewire\Public;

use App\Concerns\GuardsAgainstFlooding;
use App\Models\SecurityEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormAnswer;
use Modules\FormBuilder\Models\FormField;
use Modules\FormBuilder\Models\FormSubmission;
use Modules\FormBuilder\Models\TicketFormLink;

#[Layout('layouts.guest', ['maxWidth' => 'max-w-2xl'])]
class FillTicketForm extends Component
{
    use GuardsAgainstFlooding;

    public string $token;

    public ?TicketFormLink $link = null;

    public string $status = 'invalid';

    public bool $verified = false;

    public bool $justSubmitted = false;

    public string $confirmedEmail = '';

    public array $answers = [];

    /**
     * The row currently being composed for each repeater field, keyed by
     * field_key, before it's added to (or updated in) $answers.
     */
    public array $repeaterDrafts = [];

    /**
     * Row index within $answers currently being edited, keyed by repeater
     * field_key. Absent for a field means the draft is for a new row.
     */
    public array $repeaterEditIndex = [];

    public function mount(string $token)
    {
        $this->token = $token;
        $this->link = TicketFormLink::findByToken($token);
        $this->status = $this->link?->status() ?? 'invalid';
    }

    public function verifyEmail()
    {
        // Allowed for a 'used' link too — the recipient may reload the page
        // after submitting (losing the transient $justSubmitted flag) and
        // still needs to re-prove their email to see/download their answer
        // again. Nothing else (invalid/expired/locked) has anything to show.
        if (! $this->link || ! in_array($this->status, ['pending', 'used'], true)) {
            return;
        }

        if ($this->tooManyRequests('ticket-form.verify')) {
            session()->flash('error', 'Demasiados intentos. Espera un momento e intenta de nuevo.');

            return;
        }

        $match = strtolower(trim($this->confirmedEmail)) === strtolower($this->link->recipient_email);

        if ($match) {
            $this->verified = true;

            if ($this->status === 'pending') {
                $this->initializeAnswers();
            }

            return;
        }

        $this->link->increment('failed_verify_attempts');

        if ($this->link->failed_verify_attempts >= config('security.ticket_link_max_verify_attempts')) {
            $this->link->update(['locked_at' => now()]);
        }

        $this->link->refresh();
        $this->status = $this->link->status();

        SecurityEvent::log(SecurityEvent::TICKET_FORM_LINK_VERIFY_FAILED, request(), null, $this->confirmedEmail, [
            'ticket_form_link_id' => $this->link->id,
        ]);

        session()->flash('error', 'El correo no coincide con el destinatario de este enlace.');
        $this->confirmedEmail = '';
    }

    protected function initializeAnswers(): void
    {
        $this->answers = [];
        $this->repeaterDrafts = [];
        $this->repeaterEditIndex = [];

        foreach ($this->currentForm()?->fields ?? [] as $field) {
            if (in_array($field->type, FormField::DISPLAY_ONLY_TYPES, true) || $field->type === 'timestamp') {
                continue;
            }

            if ($field->type === 'repeater') {
                $this->answers[$field->field_key] = [];
                $this->repeaterDrafts[$field->field_key] = $this->emptyRepeaterRow($field);

                continue;
            }

            $this->answers[$field->field_key] = $this->defaultValueFor($field);
        }
    }

    protected function defaultValueFor(FormField $field): mixed
    {
        return match ($field->type) {
            'multiple_choice' => [],
            'checkbox' => false,
            default => '',
        };
    }

    protected function emptyRepeaterRow(FormField $repeaterField): array
    {
        $row = [];

        foreach ($repeaterField->children as $child) {
            $row[$child->field_key] = $this->defaultValueFor($child);
        }

        return $row;
    }

    public function saveRepeaterRow(string $fieldKey)
    {
        $field = $this->currentForm()?->fields->firstWhere('field_key', $fieldKey);

        if (! $field) {
            return;
        }

        $rules = [];
        foreach ($field->children as $child) {
            $rules["repeaterDrafts.{$fieldKey}.{$child->field_key}"] = $this->rulesFor($child);
        }

        $this->validate($rules);

        $rowIndex = $this->repeaterEditIndex[$fieldKey] ?? null;

        if ($rowIndex !== null && array_key_exists($rowIndex, $this->answers[$fieldKey] ?? [])) {
            $this->answers[$fieldKey][$rowIndex] = $this->repeaterDrafts[$fieldKey];
        } else {
            $this->answers[$fieldKey][] = $this->repeaterDrafts[$fieldKey];
        }

        $this->repeaterDrafts[$fieldKey] = $this->emptyRepeaterRow($field);
        unset($this->repeaterEditIndex[$fieldKey]);
    }

    public function editRepeaterRow(string $fieldKey, int $rowIndex)
    {
        if (! array_key_exists($rowIndex, $this->answers[$fieldKey] ?? [])) {
            return;
        }

        $this->repeaterDrafts[$fieldKey] = $this->answers[$fieldKey][$rowIndex];
        $this->repeaterEditIndex[$fieldKey] = $rowIndex;
    }

    public function cancelRepeaterEdit(string $fieldKey)
    {
        $field = $this->currentForm()?->fields->firstWhere('field_key', $fieldKey);

        if (! $field) {
            return;
        }

        $this->repeaterDrafts[$fieldKey] = $this->emptyRepeaterRow($field);
        unset($this->repeaterEditIndex[$fieldKey]);
    }

    public function removeRepeaterRow(string $fieldKey, int $rowIndex)
    {
        unset($this->answers[$fieldKey][$rowIndex]);
        $this->answers[$fieldKey] = array_values($this->answers[$fieldKey]);

        $editIndex = $this->repeaterEditIndex[$fieldKey] ?? null;

        if ($editIndex === $rowIndex) {
            $this->cancelRepeaterEdit($fieldKey);
        } elseif ($editIndex !== null && $editIndex > $rowIndex) {
            $this->repeaterEditIndex[$fieldKey]--;
        }
    }

    protected function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->currentForm()?->fields ?? [] as $field) {
            if (in_array($field->type, FormField::DISPLAY_ONLY_TYPES, true) || $field->type === 'timestamp') {
                continue;
            }

            if ($field->type === 'repeater') {
                foreach ($field->children as $child) {
                    $attributes["answers.{$field->field_key}.*.{$child->field_key}"] = $child->label;
                    $attributes["repeaterDrafts.{$field->field_key}.{$child->field_key}"] = $child->label;
                }

                continue;
            }

            $attributes["answers.{$field->field_key}"] = $field->label;
        }

        return $attributes;
    }

    protected function currentForm(): ?Form
    {
        return $this->link?->form;
    }

    protected function rules(): array
    {
        $rules = [];

        foreach ($this->currentForm()?->fields ?? [] as $field) {
            if (in_array($field->type, FormField::DISPLAY_ONLY_TYPES, true) || $field->type === 'timestamp') {
                continue;
            }

            $key = "answers.{$field->field_key}";

            if ($field->type === 'repeater') {
                $rules[$key] = $field->is_required ? ['required', 'array', 'min:1'] : ['array'];

                foreach ($field->children as $child) {
                    $rules["{$key}.*.{$child->field_key}"] = $this->rulesFor($child);
                }

                continue;
            }

            $rules[$key] = $this->rulesFor($field);
        }

        return $rules;
    }

    protected function rulesFor(FormField $field, bool $forceNullable = false): array
    {
        $fieldRules = ($field->is_required && ! $forceNullable) ? ['required'] : ['nullable'];

        $fieldRules[] = match ($field->type) {
            'email' => 'email',
            'number' => 'numeric',
            'date' => 'date',
            'multiple_choice' => 'array',
            default => null,
        };

        return array_values(array_filter($fieldRules));
    }

    public function submit()
    {
        if (! $this->link || $this->status !== 'pending' || ! $this->verified) {
            return;
        }

        // Never trust $verified alone as authorization — a forged Livewire
        // request could set it without ever calling verifyEmail(). Re-check
        // the email match here too before writing anything.
        if (strtolower(trim($this->confirmedEmail)) !== strtolower($this->link->recipient_email)) {
            $this->verified = false;
            session()->flash('error', 'No se pudo confirmar tu correo. Verifica el enlace e intenta de nuevo.');

            return;
        }

        if (! empty($this->rules())) {
            $this->validate();
        }

        $form = $this->currentForm();
        $linkId = $this->link->id;

        try {
            DB::transaction(function () use ($form, $linkId) {
                $link = TicketFormLink::whereKey($linkId)->lockForUpdate()->first();

                if (! $link || ! $link->isPending()) {
                    throw new \RuntimeException('ticket-form-link-not-pending');
                }

                $submission = FormSubmission::create([
                    'form_id' => $form->id,
                    'ticket_form_link_id' => $link->id,
                    'submitted_at' => now(),
                ]);

                foreach ($form->fields as $field) {
                    if (in_array($field->type, FormField::DISPLAY_ONLY_TYPES, true)) {
                        continue;
                    }

                    $value = match ($field->type) {
                        'timestamp' => now()->toDateTimeString(),
                        default => $this->answers[$field->field_key] ?? null,
                    };

                    FormAnswer::create([
                        'submission_id' => $submission->id,
                        'form_field_id' => $field->id,
                        'value' => $value,
                    ]);
                }

                $link->update(['used_at' => now()]);
            });
        } catch (\Throwable $e) {
            $this->link->refresh();
            $this->status = $this->link->status();

            session()->flash('error', 'Este formulario ya no puede llenarse.');

            return;
        }

        $this->link->refresh();
        $this->status = $this->link->status();
        $this->justSubmitted = true;

        SecurityEvent::log(SecurityEvent::TICKET_FORM_LINK_SUBMITTED, request(), null, $this->link->recipient_email, [
            'ticket_form_link_id' => $this->link->id,
        ]);
    }

    public function exportPdf()
    {
        if (! $this->verified || ! $this->link || $this->status !== 'used') {
            return;
        }

        $link = $this->link->fresh(['form.fields', 'submission.answers']);
        $form = $link->form;

        $pdf = Pdf::loadView($form->pdfView(), ['form' => $form, 'link' => $link]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            $form->downloadFilename($link->ticket_number)
        );
    }

    /**
     * A short-lived signed URL for "Imprimir" — a real, navigable GET link
     * (a wire:click action can only trigger a download, never open a tab),
     * scoped to this link. No separate email check on that route: reaching
     * this point already proved the visitor knows both the token and the
     * recipient email.
     */
    public function printUrl(): ?string
    {
        if (! $this->verified || ! $this->link || $this->status !== 'used') {
            return null;
        }

        return URL::temporarySignedRoute(
            'formbuilder.public.print',
            now()->addMinutes(15),
            ['ticketFormLink' => $this->link->id]
        );
    }

    public function render()
    {
        return view('formbuilder::livewire.public.fill-ticket-form', [
            'currentForm' => $this->currentForm(),
            'printUrl' => $this->printUrl(),
        ]);
    }
}
