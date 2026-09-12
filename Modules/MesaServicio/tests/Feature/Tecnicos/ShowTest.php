<?php

namespace Modules\MesaServicio\Tests\Feature\Tecnicos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormAnswer;
use Modules\FormBuilder\Models\FormField;
use Modules\FormBuilder\Models\FormSubmission;
use Modules\FormBuilder\Models\TicketFormLink;
use Modules\MesaServicio\Livewire\Tecnicos\Show;
use Modules\MesaServicio\Models\SdpSurveyLink;
use Modules\MesaServicio\Models\SdpSurveySetting;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Dashboard',
            'slug' => 'mesaservicio-dashboard',
            'route_name' => 'mesaservicio.dashboard.index',
            'permission_name' => 'screens.mesaservicio-dashboard.manage',
            'icon' => 'chart-bar',
            'order' => 0,
        ]);

        $role = Role::findOrCreate('Ficha Tecnico Test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function estado(string $tipo, string $nombre): SdpTicketStatus
    {
        return SdpTicketStatus::create([
            'sdp_id' => fake()->unique()->randomNumber(9),
            'nombre' => $nombre,
            'tipo' => $tipo,
            'activo' => true,
        ]);
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan', 'activo' => true, 'es_nivel_1' => false]);
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get("/mesa-servicio/tecnicos/{$technician->id}")->assertForbidden();
    }

    public function test_it_only_shows_tickets_belonging_to_the_technician(): void
    {
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO, 'Abierto');

        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'correo' => 'juan@example.test', 'activo' => true, 'es_nivel_1' => false]);
        $otro = SdpTechnician::create(['sdp_id' => 't2', 'nombre' => 'Ana Ruiz', 'activo' => true, 'es_nivel_1' => false]);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Ticket de Juan', 'created_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Ticket de Ana', 'created_time' => now(),
            'sdp_technician_id' => $otro->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee('Ticket de Juan')
            ->assertDontSee('Ticket de Ana');
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee(route('mesaservicio.ayuda.pdf', 'ficha-tecnico'), escape: false);
    }

    public function test_it_separates_pendientes_from_atendidos(): void
    {
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO, 'Abierto');
        $completado = $this->estado(SdpTicketStatus::TIPO_COMPLETADO, 'Completado');

        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Pendiente de Juan', 'created_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Atendido de Juan', 'created_time' => now(), 'completed_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $completado->id, 'estado_nombre' => 'Completado',
        ]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Show::class, ['tecnico' => $technician]);

        $component->assertSee('Pendiente de Juan')->assertSee('Atendido de Juan');
        $component->assertViewHas('pendientes', fn ($pendientes) => $pendientes->pluck('asunto')->all() === ['Pendiente de Juan']);
        $component->assertViewHas('atendidos', fn ($atendidos) => $atendidos->pluck('asunto')->all() === ['Atendido de Juan']);
    }

    private function encuestaField(): array
    {
        $form = Form::create(['name' => 'Encuesta de prueba', 'status' => 'published']);

        $field = FormField::create([
            'form_id' => $form->id,
            'type' => 'single_choice',
            'label' => '¿Qué tan satisfecho quedaste?',
            'field_key' => SdpSurveySetting::CALIFICACION_FIELD_KEY,
            'is_required' => true,
            'order' => 0,
            'options' => [
                ['value' => '1', 'label' => 'Muy insatisfecho'],
                ['value' => '5', 'label' => 'Muy satisfecho'],
            ],
        ]);

        SdpSurveySetting::current()->update(['form_id' => $form->id]);

        return [$form, $field];
    }

    private function registrarRespuesta(SdpTechnician $tecnico, SdpTicket $ticket, Form $form, FormField $field, int $calificacion): void
    {
        $link = TicketFormLink::create([
            'form_id' => $form->id,
            'ticket_number' => $ticket->display_id ?? $ticket->sdp_id,
            'recipient_email' => 'solicitante@example.test',
            'token_hash' => TicketFormLink::hashToken(Str::random(48)),
            'expires_at' => now()->addDay(),
        ]);

        SdpSurveyLink::create([
            'ticket_form_link_id' => $link->id,
            'sdp_ticket_id' => $ticket->id,
            'sdp_technician_id' => $tecnico->id,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'ticket_form_link_id' => $link->id,
            'submitted_at' => now(),
        ]);

        FormAnswer::create([
            'submission_id' => $submission->id,
            'form_field_id' => $field->id,
            // Igual que la app real (FillTicketForm::submit()): single_choice
            // guarda el 'value' de la opción elegida como escalar, no como
            // arreglo, pese al cast 'array' de FormAnswer::value.
            'value' => (string) $calificacion,
        ]);
    }

    public function test_it_shows_an_empty_state_when_there_are_no_answered_surveys(): void
    {
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee('Sin encuestas de satisfacción respondidas');
    }

    public function test_it_shows_an_empty_state_when_no_survey_is_configured_even_with_tickets(): void
    {
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);
        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Ticket', 'created_time' => now(),
            'sdp_technician_id' => $technician->id,
        ]);

        $this->assertNull(SdpSurveySetting::current()->form_id);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee('Sin encuestas de satisfacción respondidas');
    }

    public function test_it_calculates_the_average_rating_and_count_for_the_technician(): void
    {
        [$form, $field] = $this->encuestaField();

        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        $ticket1 = SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Uno', 'created_time' => now(), 'sdp_technician_id' => $technician->id]);
        $ticket2 = SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'Dos', 'created_time' => now(), 'sdp_technician_id' => $technician->id]);

        $this->registrarRespuesta($technician, $ticket1, $form, $field, 4);
        $this->registrarRespuesta($technician, $ticket2, $form, $field, 5);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertViewHas('satisfaccion', ['promedio' => 4.5, 'total' => 2])
            ->assertSee('4.5 / 5')
            ->assertDontSee('Sin encuestas de satisfacción respondidas');
    }

    public function test_it_shows_the_vencido_badge_for_an_overdue_ticket(): void
    {
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO, 'Abierto');
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Ticket vencido', 'created_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
            'vencido' => true, 'primera_respuesta_vencida' => false,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee('Vencido');
    }

    public function test_it_shows_the_first_response_overdue_badge_when_not_fully_overdue(): void
    {
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO, 'Abierto');
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Ticket con 1ra respuesta vencida', 'created_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
            'vencido' => false, 'primera_respuesta_vencida' => true,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee('1ra resp. vencida');
    }

    public function test_it_shows_the_en_tiempo_badge_when_not_overdue_at_all(): void
    {
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO, 'Abierto');
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Ticket en tiempo', 'created_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
            'vencido' => false, 'primera_respuesta_vencida' => false,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee('En tiempo');
    }

    public function test_it_ignores_survey_answers_from_other_technicians(): void
    {
        [$form, $field] = $this->encuestaField();

        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);
        $otro = SdpTechnician::create(['sdp_id' => 't2', 'nombre' => 'Ana Ruiz', 'activo' => true, 'es_nivel_1' => false]);

        $ticketOtro = SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'De Ana', 'created_time' => now(), 'sdp_technician_id' => $otro->id]);
        $this->registrarRespuesta($otro, $ticketOtro, $form, $field, 1);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertViewHas('satisfaccion', ['promedio' => null, 'total' => 0])
            ->assertSee('Sin encuestas de satisfacción respondidas');
    }
}
