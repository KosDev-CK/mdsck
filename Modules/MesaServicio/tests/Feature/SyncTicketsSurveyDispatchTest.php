<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\TicketFormLink;
use Modules\FormBuilder\Notifications\TicketFormLinkNotification;
use Modules\MesaServicio\Models\SdpSurveyLink;
use Modules\MesaServicio\Models\SdpSurveySetting;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Tests\TestCase;

/**
 * Fase 6: disparo automático de la encuesta de satisfacción dentro de
 * sdp:sync-tickets cuando un ticket pasa a un estado local de tipo
 * "completado". Deliberadamente en un archivo aparte de
 * SyncTicketsCommandTest (que ya cubre el resto del comando) para mantener
 * cada archivo enfocado en un solo aspecto.
 */
class SyncTicketsSurveyDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // El test de resiliencia registra un listener estático
        // TicketFormLink::creating(...) para forzar un fallo determinista —
        // se limpia explícitamente para no filtrarse a otros tests que
        // corran después en el mismo proceso de PHPUnit (los listeners de
        // Eloquent son estáticos, RefreshDatabase no los toca).
        TicketFormLink::flushEventListeners();

        parent::tearDown();
    }

    protected function fakeTokenAndRequests(array $requests): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => Http::response([
                'requests' => $requests,
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);
    }

    protected function ticket(array $overrides = []): array
    {
        return array_replace([
            'id' => (string) fake()->unique()->randomNumber(9),
            'display_id' => (string) fake()->unique()->randomNumber(5),
            'subject' => 'Ticket de prueba',
            'technician' => ['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test'],
            'requester' => ['name' => 'Solicitante Uno', 'email_id' => 'solicitante@example.test'],
            'status' => ['id' => 's1', 'name' => 'Completado', 'internal_name' => 'Closed', 'in_progress' => false],
            'created_time' => ['value' => '1700000000000'],
            'last_updated_time' => ['value' => '1700000000000'],
        ], $overrides);
    }

    protected function estadoCompletado(): SdpTicketStatus
    {
        return SdpTicketStatus::create([
            'sdp_id' => 's1', 'nombre' => 'Completado', 'internal_name' => 'Closed',
            'tipo' => SdpTicketStatus::TIPO_COMPLETADO, 'activo' => true,
        ]);
    }

    protected function configurarEncuesta(): Form
    {
        $form = Form::create(['name' => 'Encuesta', 'status' => 'published']);
        SdpSurveySetting::current()->update(['form_id' => $form->id]);

        return $form;
    }

    public function test_it_generates_a_ticket_form_link_and_survey_link_and_notifies_the_requester(): void
    {
        Notification::fake();
        $this->estadoCompletado();
        $form = $this->configurarEncuesta();

        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1', 'display_id' => '55001'])]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $ticket = SdpTicket::where('sdp_id', 'tk-1')->first();
        $this->assertNotNull($ticket);

        $this->assertSame(1, SdpSurveyLink::count());
        $this->assertSame(1, TicketFormLink::count());

        $link = TicketFormLink::first();
        $this->assertSame($form->id, $link->form_id);
        $this->assertSame('55001', $link->ticket_number);
        $this->assertSame('solicitante@example.test', $link->recipient_email);
        // Lo dispara el comando, no un usuario autenticado.
        $this->assertNull($link->created_by);

        $surveyLink = SdpSurveyLink::first();
        $this->assertSame($link->id, $surveyLink->ticket_form_link_id);
        $this->assertSame($ticket->id, $surveyLink->sdp_ticket_id);
        $this->assertNotNull($surveyLink->sdp_technician_id);

        Notification::assertSentOnDemand(
            TicketFormLinkNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'solicitante@example.test'
        );
    }

    public function test_it_does_not_dispatch_again_on_a_later_run_of_the_same_completed_ticket(): void
    {
        Notification::fake();
        $this->estadoCompletado();
        $this->configurarEncuesta();

        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();
        $this->assertSame(1, SdpSurveyLink::count());

        // Segunda corrida: mismo ticket, sigue completado — no debe crear un
        // segundo SdpSurveyLink ni un segundo TicketFormLink.
        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(1, SdpSurveyLink::count());
        $this->assertSame(1, TicketFormLink::count());
    }

    public function test_it_does_not_dispatch_when_no_survey_form_is_configured(): void
    {
        Notification::fake();
        $this->estadoCompletado();

        $this->assertNull(SdpSurveySetting::current()->form_id);

        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(0, SdpSurveyLink::count());
        $this->assertSame(0, TicketFormLink::count());
        Notification::assertNothingSent();
    }

    public function test_it_does_not_dispatch_when_the_ticket_has_no_requester_email(): void
    {
        Notification::fake();
        $this->estadoCompletado();
        $this->configurarEncuesta();

        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'requester' => ['name' => 'Solicitante Uno', 'email_id' => null]]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(0, SdpSurveyLink::count());
        $this->assertSame(0, TicketFormLink::count());
        Notification::assertNothingSent();
    }

    public function test_it_does_not_dispatch_for_tickets_that_are_not_completed(): void
    {
        Notification::fake();
        SdpTicketStatus::create([
            'sdp_id' => 's-open', 'nombre' => 'Abierto', 'tipo' => SdpTicketStatus::TIPO_EN_CURSO, 'activo' => true,
        ]);
        $this->configurarEncuesta();

        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'status' => ['id' => 's-open', 'name' => 'Abierto', 'in_progress' => true]]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(0, SdpSurveyLink::count());
        Notification::assertNothingSent();
    }

    public function test_a_failure_dispatching_the_survey_for_one_ticket_does_not_break_the_rest_of_the_batch(): void
    {
        Notification::fake();
        $this->estadoCompletado();
        $this->configurarEncuesta();

        // Forzado de fallo determinista y sin mocking frágil de facades: se
        // registra un listener de modelo que hace fallar la creación del
        // TicketFormLink únicamente para el ticket marcado como "FAIL" — el
        // resto del batch debe seguir procesándose con normalidad pese a la
        // excepción (capturada y logueada dentro de
        // SyncTicketsCommand::dispatchSurveyIfNewlyCompleted()).
        TicketFormLink::creating(function (TicketFormLink $link) {
            if ($link->ticket_number === 'FAIL-TICKET') {
                throw new \RuntimeException('Fallo simulado de prueba.');
            }
        });

        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-fail', 'display_id' => 'FAIL-TICKET']),
            $this->ticket(['id' => 'tk-ok', 'display_id' => 'OK-TICKET']),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        // Ambos tickets se sincronizaron igual (el fallo es solo en la
        // encuesta, no en el upsert del ticket).
        $this->assertSame(2, SdpTicket::count());

        // Solo el ticket que no falló generó su encuesta.
        $this->assertSame(1, TicketFormLink::count());
        $this->assertSame(1, SdpSurveyLink::count());
        $this->assertDatabaseHas('ticket_form_links', ['ticket_number' => 'OK-TICKET']);
        $this->assertDatabaseMissing('ticket_form_links', ['ticket_number' => 'FAIL-TICKET']);
    }
}
