<?php

namespace Modules\FormBuilder\Tests\Feature\Public;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\FormBuilder\Livewire\Public\FillTicketForm;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormAnswer;
use Modules\FormBuilder\Models\FormField;
use Modules\FormBuilder\Models\FormSubmission;
use Modules\FormBuilder\Models\TicketFormLink;
use Tests\TestCase;

class FillTicketFormTest extends TestCase
{
    use RefreshDatabase;

    protected function createLinkWithForm(array $overrides = []): array
    {
        $creator = User::factory()->create(['is_active' => true]);
        $form = Form::create(['name' => 'Alta de equipo', 'status' => 'published', 'created_by' => $creator->id]);

        FormField::create([
            'form_id' => $form->id,
            'type' => 'short_text',
            'label' => 'Nombre del solicitante',
            'field_key' => 'nombre_del_solicitante',
            'is_required' => true,
            'order' => 0,
        ]);

        [$rawToken, $hash] = TicketFormLink::generateToken();

        $link = TicketFormLink::create(array_merge([
            'form_id' => $form->id,
            'ticket_number' => 'INC000123',
            'recipient_email' => 'destinatario@example.com',
            'token_hash' => $hash,
            'expires_at' => now()->addHours(24),
            'created_by' => $creator->id,
        ], $overrides));

        return [$rawToken, $link, $form];
    }

    public function test_an_unknown_token_shows_invalid_status(): void
    {
        Livewire::test(FillTicketForm::class, ['token' => 'not-a-real-token'])
            ->assertSet('status', 'invalid');
    }

    public function test_an_expired_link_shows_expired_status(): void
    {
        [$rawToken] = $this->createLinkWithForm(['expires_at' => now()->subHour()]);

        Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->assertSet('status', 'expired');
    }

    public function test_wrong_email_does_not_reveal_the_form_and_locks_after_max_attempts(): void
    {
        [$rawToken, $link] = $this->createLinkWithForm();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(FillTicketForm::class, ['token' => $rawToken])
                ->set('confirmedEmail', 'incorrecto@example.com')
                ->call('verifyEmail')
                ->assertSet('verified', false);
        }

        $this->assertSame(5, $link->fresh()->failed_verify_attempts);
        $this->assertSame('locked', $link->fresh()->status());

        Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->assertSet('status', 'locked');
    }

    public function test_correct_email_reveals_the_form_and_submitting_marks_the_link_used(): void
    {
        [$rawToken, $link, $form] = $this->createLinkWithForm();
        $field = $form->fields->first();

        $component = Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->set('confirmedEmail', 'destinatario@example.com')
            ->call('verifyEmail')
            ->assertSet('verified', true);

        $component
            ->set("answers.{$field->field_key}", 'Juan Pérez')
            ->call('submit')
            ->assertSet('status', 'used')
            ->assertSet('justSubmitted', true);

        $this->assertNotNull($link->fresh()->used_at);
        $this->assertDatabaseHas('form_submissions', ['ticket_form_link_id' => $link->id]);
        $this->assertDatabaseHas('form_answers', [
            'form_field_id' => $field->id,
            'value' => json_encode('Juan Pérez'),
        ]);
    }

    public function test_a_used_link_cannot_be_filled_again(): void
    {
        [$rawToken, $link] = $this->createLinkWithForm(['used_at' => now()]);

        Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->assertSet('status', 'used')
            ->assertSet('justSubmitted', false);
    }

    public function test_revisiting_a_used_link_can_re_verify_to_see_and_download_the_copy(): void
    {
        [$rawToken, $link, $form] = $this->createLinkWithForm(['used_at' => now()]);

        $field = $form->fields->first();
        $submission = FormSubmission::create([
            'form_id' => $form->id, 'ticket_form_link_id' => $link->id, 'submitted_at' => now(),
        ]);
        FormAnswer::create([
            'submission_id' => $submission->id, 'form_field_id' => $field->id, 'value' => 'Juan Pérez',
        ]);

        $component = Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->assertSet('verified', false)
            ->set('confirmedEmail', 'destinatario@example.com')
            ->call('verifyEmail')
            ->assertSet('verified', true);

        $component->call('exportPdf')->assertFileDownloaded();

        $this->assertNotNull($component->viewData('printUrl'));
    }

    public function test_print_url_is_not_exposed_before_verification(): void
    {
        [$rawToken] = $this->createLinkWithForm(['used_at' => now()]);

        Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->assertViewHas('printUrl', null);
    }
}
