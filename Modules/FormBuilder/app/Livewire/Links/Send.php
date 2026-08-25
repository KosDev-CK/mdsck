<?php

namespace Modules\FormBuilder\Livewire\Links;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\TicketFormLink;
use Modules\FormBuilder\Notifications\TicketFormLinkNotification;

#[Layout('layouts.app')]
class Send extends Component
{
    public string $formId = '';

    public string $ticketNumber = '';

    public string $recipientEmail = '';

    protected function rules(): array
    {
        return [
            'formId' => ['required', 'integer', 'exists:forms,id'],
            'ticketNumber' => ['required', 'string', 'max:255'],
            'recipientEmail' => ['required', 'email', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'formId' => 'plantilla',
            'ticketNumber' => 'número de ticket',
            'recipientEmail' => 'correo destino',
        ];
    }

    public function generateLink()
    {
        $this->validate();

        $form = Form::wherePublished()->find($this->formId);

        if (! $form) {
            $this->addError('formId', 'Selecciona una plantilla publicada.');

            return;
        }

        [$rawToken, $hash] = TicketFormLink::generateToken();

        try {
            DB::transaction(function () use ($form, $rawToken, $hash) {
                $link = TicketFormLink::create([
                    'form_id' => $form->id,
                    'ticket_number' => $this->ticketNumber,
                    'recipient_email' => $this->recipientEmail,
                    'token_hash' => $hash,
                    'expires_at' => now()->addHours(config('security.ticket_link_ttl_hours')),
                    'created_by' => auth()->id(),
                ]);

                Notification::route('mail', $link->recipient_email)
                    ->notify(new TicketFormLinkNotification($link, $rawToken));
            });
        } catch (\Throwable $e) {
            Log::error('No se pudo generar/enviar el enlace de formulario por ticket.', ['exception' => $e]);

            session()->flash('error', 'No se pudo enviar el correo. Verifica la dirección e intenta de nuevo.');

            return;
        }

        $this->reset(['ticketNumber', 'recipientEmail']);
        session()->flash('status', 'Enlace generado y enviado por correo.');
    }

    public function render()
    {
        return view('formbuilder::livewire.links.send', [
            'forms' => Form::wherePublished()->orderBy('name')->get(),
            'links' => TicketFormLink::with('form')
                ->where('created_by', auth()->id())
                ->latest()
                ->get(),
        ]);
    }
}
