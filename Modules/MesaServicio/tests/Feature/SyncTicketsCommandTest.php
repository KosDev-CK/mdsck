<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Models\SdpSyncState;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Tests\TestCase;

class SyncTicketsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeTokenAndRequests(array $requests, array $listInfo = []): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => Http::response([
                'response_status' => [['status_code' => 2000, 'status' => 'success']],
                'requests' => $requests,
                'list_info' => $listInfo,
            ], 200),
        ]);
    }

    /**
     * Shape calcada de la respuesta real confirmada contra la instancia de
     * mdsLandIT (EU) en esta sesión (ver docs/mesaservicio-progreso.md,
     * Fase 2) — objetos de fecha con {value, display_value}, technician
     * embebido, status con {name, internal_name, in_progress}.
     */
    protected function ticket(array $overrides = []): array
    {
        return array_replace([
            'id' => (string) fake()->randomNumber(9),
            'display_id' => (string) fake()->randomNumber(5),
            'subject' => 'Ticket de prueba',
            'technician' => ['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista'],
            'requester' => ['name' => 'Solicitante Uno', 'email_id' => 'solicitante@example.test'],
            'status' => ['id' => 's1', 'name' => 'Abierto', 'internal_name' => 'Open', 'in_progress' => true],
            'category' => ['name' => 'Oracle'],
            'subcategory' => ['name' => 'WMS'],
            'department' => ['name' => 'Implementación'],
            'site' => ['name' => 'Base Site'],
            'priority' => ['name' => '4'],
            'urgency' => ['name' => '3. Baja'],
            'impact' => ['name' => '3. Usuario'],
            'request_type' => ['name' => 'Incidente'],
            'mode' => ['name' => 'E-Mail'],
            'group' => ['name' => 'Key User'],
            'created_time' => ['value' => '1700000000000', 'display_value' => 'x'],
            'responded_time' => null,
            'resolved_time' => null,
            'completed_time' => null,
            'due_by_time' => null,
            'last_updated_time' => ['value' => '1700000000000', 'display_value' => 'x'],
            'is_first_response_overdue' => false,
            'is_overdue' => false,
            'resolution' => null,
        ], $overrides);
    }

    public function test_it_upserts_a_ticket_resolving_technician_and_status(): void
    {
        SdpTicketStatus::create([
            'sdp_id' => 's1', 'nombre' => 'Abierto', 'internal_name' => 'Open',
            'tipo' => SdpTicketStatus::TIPO_EN_CURSO, 'activo' => true,
        ]);

        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(1, SdpTicket::count());

        $ticket = SdpTicket::where('sdp_id', 'tk-1')->first();

        $this->assertNotNull($ticket);
        $this->assertSame('Ticket de prueba', $ticket->asunto);
        $this->assertSame('Abierto', $ticket->estado_nombre);
        $this->assertSame('Oracle', $ticket->categoria);
        $this->assertSame('WMS', $ticket->subcategoria);
        $this->assertSame('Solicitante Uno', $ticket->solicitante_nombre);
        $this->assertNotNull($ticket->sdp_technician_id);
        $this->assertSame('Juan Pérez', $ticket->technician->nombre);
        $this->assertNotNull($ticket->sdp_ticket_status_id);
        $this->assertSame(SdpTicketStatus::TIPO_EN_CURSO, $ticket->ticketStatus->tipo);
        $this->assertIsArray($ticket->raw_payload);
        $this->assertSame('tk-1', $ticket->raw_payload['id']);
    }

    public function test_it_creates_the_technician_on_the_fly_without_touching_activo_or_nivel_1(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'technician' => ['id' => 't-new', 'name' => 'Nuevo Técnico', 'email_id' => 'nuevo@example.test']]),
        ]);

        $this->assertSame(0, SdpTechnician::count());

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $technician = SdpTechnician::where('sdp_id', 't-new')->first();
        $this->assertNotNull($technician);
        $this->assertSame('Nuevo Técnico', $technician->nombre);
        // Valores por defecto de la migración, no tocados explícitamente.
        $this->assertTrue($technician->activo);
        $this->assertFalse($technician->es_nivel_1);
    }

    public function test_it_never_overwrites_an_existing_technicians_nivel_1_flag(): void
    {
        SdpTechnician::create([
            'sdp_id' => 't1', 'nombre' => 'Juan Pérez (viejo)', 'correo' => 'juan@example.test',
            'activo' => false, 'es_nivel_1' => true,
        ]);

        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'technician' => ['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test']]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'es_nivel_1' => true,
            // 'activo' tampoco se toca desde este comando — sigue en false,
            // como lo dejó el fixture, aunque el técnico sí apareció aquí.
            'activo' => false,
        ]);
    }

    public function test_it_leaves_ticket_status_fk_null_and_keeps_raw_name_when_status_is_not_in_the_catalog(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'status' => ['id' => 's-unknown', 'name' => 'Estado Nuevo Sin Sembrar', 'in_progress' => true]]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $ticket = SdpTicket::where('sdp_id', 'tk-1')->first();

        $this->assertNull($ticket->sdp_ticket_status_id);
        $this->assertSame('Estado Nuevo Sin Sembrar', $ticket->estado_nombre);
    }

    public function test_it_is_idempotent_running_it_twice(): void
    {
        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();
        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(1, SdpTicket::count());
    }

    public function test_it_paginates_across_multiple_pages(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $inputData = json_decode($query['input_data'], true);
                $startIndex = $inputData['list_info']['start_index'];

                if ($startIndex === 1) {
                    return Http::response([
                        'requests' => [$this->ticket(['id' => 'tk-1'])],
                        'list_info' => ['has_more_rows' => true],
                    ], 200);
                }

                return Http::response([
                    'requests' => [$this->ticket(['id' => 'tk-2'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(2, SdpTicket::count());
        $this->assertDatabaseHas('sdp_tickets', ['sdp_id' => 'tk-1']);
        $this->assertDatabaseHas('sdp_tickets', ['sdp_id' => 'tk-2']);
    }

    public function test_first_run_without_watermark_sends_no_search_criteria(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => Http::response([
                'requests' => [$this->ticket(['id' => 'tk-1'])],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->assertNull(SdpSyncState::get(SdpSyncState::KEY_TICKETS));

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v3/requests')) {
                return false;
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $inputData = json_decode($query['input_data'], true);

            return ! array_key_exists('search_criteria', $inputData['list_info']);
        });

        $this->assertNotNull(SdpSyncState::get(SdpSyncState::KEY_TICKETS));
    }

    public function test_it_filters_by_watermark_on_subsequent_runs(): void
    {
        $watermark = Carbon::createFromTimestampMs(1700000000000);
        SdpSyncState::set(SdpSyncState::KEY_TICKETS, $watermark);

        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        Http::assertSent(function ($request) use ($watermark) {
            if (! str_contains($request->url(), '/api/v3/requests')) {
                return false;
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $inputData = json_decode($query['input_data'], true);
            $criteria = $inputData['list_info']['search_criteria'][0] ?? null;

            return $criteria
                && $criteria['field'] === 'last_updated_time'
                && $criteria['condition'] === 'after'
                // Margen de 1s restado al leer la marca de agua (ver comando).
                && (int) $criteria['value'] === $watermark->clone()->subSecond()->getTimestampMs();
        });
    }

    public function test_it_advances_the_watermark_to_the_max_last_updated_time_seen(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'last_updated_time' => ['value' => '1700000000000']]),
            $this->ticket(['id' => 'tk-2', 'last_updated_time' => ['value' => '1750000000000']]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $watermark = SdpSyncState::get(SdpSyncState::KEY_TICKETS);

        $this->assertNotNull($watermark);
        $this->assertSame(1750000000000, $watermark->getTimestampMs());
    }

    public function test_it_handles_zero_results_without_error_and_without_moving_the_watermark(): void
    {
        $this->fakeTokenAndRequests([], ['has_more_rows' => false]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(0, SdpTicket::count());
        $this->assertNull(SdpSyncState::get(SdpSyncState::KEY_TICKETS));
    }
}
