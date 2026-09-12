<?php

namespace Modules\MesaServicio\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\FormBuilder\Models\Form;
use Modules\MesaServicio\Livewire\Catalogos\Destinatarios;
use Modules\MesaServicio\Models\SdpReportRecipientEmail;
use Modules\MesaServicio\Models\SdpSurveySetting;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DestinatariosTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Destinatarios de reporte',
            'slug' => 'mesaservicio-destinatarios',
            'route_name' => 'mesaservicio.destinatarios.index',
            'permission_name' => 'screens.mesaservicio-destinatarios.manage',
            'icon' => 'envelope',
            'order' => 2,
        ]);

        $role = Role::findOrCreate('Rol destinatarios test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/destinatarios')->assertForbidden();
    }

    public function test_it_lists_supervisors_with_the_role_and_active(): void
    {
        Role::findOrCreate('Supervisor Mesa de Servicio', 'web');

        $activeSupervisor = User::factory()->create(['is_active' => true, 'name' => 'Supervisora Activa']);
        $activeSupervisor->assignRole('Supervisor Mesa de Servicio');

        $inactiveSupervisor = User::factory()->create(['is_active' => false, 'name' => 'Supervisor Inactivo']);
        $inactiveSupervisor->assignRole('Supervisor Mesa de Servicio');

        $notSupervisor = User::factory()->create(['is_active' => true, 'name' => 'Sin Rol']);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->assertSee('Supervisora Activa')
            ->assertDontSee('Supervisor Inactivo')
            ->assertDontSee('Sin Rol');
    }

    public function test_it_can_add_a_recipient_email(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->set('newEmail', 'destinatario@example.test')
            ->set('newNombre', 'Destinatario de prueba')
            ->call('addEmail')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sdp_report_recipient_emails', [
            'email' => 'destinatario@example.test',
            'nombre' => 'Destinatario de prueba',
        ]);
    }

    public function test_it_rejects_duplicate_or_invalid_emails(): void
    {
        SdpReportRecipientEmail::create(['email' => 'existente@example.test']);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->set('newEmail', 'existente@example.test')
            ->call('addEmail')
            ->assertHasErrors('newEmail');

        Livewire::test(Destinatarios::class)
            ->set('newEmail', 'no-es-un-correo')
            ->call('addEmail')
            ->assertHasErrors('newEmail');
    }

    public function test_it_can_remove_a_recipient_email(): void
    {
        $recipient = SdpReportRecipientEmail::create(['email' => 'quitar@example.test']);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->call('removeEmail', $recipient->id);

        $this->assertDatabaseMissing('sdp_report_recipient_emails', ['id' => $recipient->id]);
    }

    public function test_it_loads_the_currently_configured_survey_form(): void
    {
        $form = Form::create(['name' => 'Encuesta', 'status' => 'published']);
        SdpSurveySetting::current()->update(['form_id' => $form->id]);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->assertSet('surveyFormId', (string) $form->id);
    }

    public function test_it_saves_the_selected_survey_form(): void
    {
        $form = Form::create(['name' => 'Encuesta', 'status' => 'published']);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->set('surveyFormId', (string) $form->id)
            ->assertHasNoErrors();

        $this->assertSame($form->id, SdpSurveySetting::current()->form_id);
    }

    public function test_it_can_deactivate_the_survey_by_selecting_none(): void
    {
        $form = Form::create(['name' => 'Encuesta', 'status' => 'published']);
        SdpSurveySetting::current()->update(['form_id' => $form->id]);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->set('surveyFormId', '');

        $this->assertNull(SdpSurveySetting::current()->form_id);
    }

    public function test_it_rejects_a_form_that_is_not_published(): void
    {
        $draft = Form::create(['name' => 'Borrador', 'status' => 'draft']);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->set('surveyFormId', (string) $draft->id);

        $this->assertNull(SdpSurveySetting::current()->form_id);
    }

    public function test_it_only_offers_published_forms_in_the_picker(): void
    {
        Form::create(['name' => 'Encuesta Publicada', 'status' => 'published']);
        Form::create(['name' => 'Encuesta Borrador', 'status' => 'draft']);

        $this->actingAs($this->actingUser());

        Livewire::test(Destinatarios::class)
            ->assertSee('Encuesta Publicada')
            ->assertDontSee('Encuesta Borrador');
    }
}
