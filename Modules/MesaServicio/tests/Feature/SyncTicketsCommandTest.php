<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Models\SdpSite;
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
            // Fase 8 (Parte 6) — campos nuevos confirmados, con defaults
            // inofensivos para no romper ningún assert existente.
            'item' => ['name' => 'Laptop'],
            'service_category' => ['name' => 'Hardware'],
            'level' => ['id' => 'l1', 'name' => '1. Mesa de Ayuda'],
            'assigned_time' => ['value' => '1700000000000', 'display_value' => 'x'],
            'time_elapsed' => '256000',
        ], $overrides);
    }

    /**
     * Fase 8: cada ticket sincronizado dispara una llamada al historial
     * (SdpClient::getRequestHistory()), cuya URL también matchea el patrón
     * genérico '*\/api/v3/requests*' usado por fakeTokenAndRequests() — el
     * payload de listado no trae la clave "history", así que se lee como
     * vacío sin error para los tests que no le interesa el historial (todos
     * los existentes antes de esta fase). Los tests de esta fase que sí
     * necesitan simular el historial usan una closure sobre la URL en vez de
     * un segundo patrón (para no depender del orden de registro de
     * Http::fake() con patrones superpuestos).
     */

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
                && $criteria['condition'] === 'greater than'
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

    public function test_desde_option_sends_created_time_after_criteria_for_that_days_start(): void
    {
        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets', ['--desde' => '2026-09-01'])->assertSuccessful();

        $expectedMs = Carbon::parse('2026-09-01')->startOfDay()->getTimestampMs();

        Http::assertSent(function ($request) use ($expectedMs) {
            if (! str_contains($request->url(), '/api/v3/requests')) {
                return false;
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $inputData = json_decode($query['input_data'], true);
            $criteria = $inputData['list_info']['search_criteria'][0] ?? null;

            return $criteria
                && $criteria['field'] === 'created_time'
                && $criteria['condition'] === 'greater than'
                && (int) $criteria['value'] === $expectedMs;
        });
    }

    public function test_desde_option_does_not_touch_the_watermark(): void
    {
        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->assertNull(SdpSyncState::get(SdpSyncState::KEY_TICKETS));

        $this->artisan('sdp:sync-tickets', ['--desde' => '2026-09-01'])->assertSuccessful();

        $this->assertNull(SdpSyncState::get(SdpSyncState::KEY_TICKETS));
    }

    public function test_desde_option_does_not_touch_an_existing_watermark(): void
    {
        $watermark = Carbon::createFromTimestampMs(1700000000000);
        SdpSyncState::set(SdpSyncState::KEY_TICKETS, $watermark);

        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets', ['--desde' => '2026-09-01'])->assertSuccessful();

        $this->assertSame($watermark->getTimestampMs(), SdpSyncState::get(SdpSyncState::KEY_TICKETS)->getTimestampMs());
    }

    public function test_invalid_desde_option_fails_gracefully(): void
    {
        $this->artisan('sdp:sync-tickets', ['--desde' => 'not-a-date'])->assertFailed();
    }

    // --- Fase 8 (Parte 2) — detección de folios combinados vía historial ---

    public function test_it_marks_a_locally_existing_absorbed_ticket_as_combinado_when_history_reports_a_merge(): void
    {
        $combinadoStatus = SdpTicketStatus::create([
            'sdp_id' => 'local-combinado', 'nombre' => 'Combinado',
            'tipo' => SdpTicketStatus::TIPO_COMPLETADO, 'activo' => true,
        ]);

        // El ticket absorbido ya se había sincronizado antes de fusionarse.
        $absorbido = SdpTicket::create([
            'sdp_id' => 'tk-absorbido', 'asunto' => 'Ticket viejo', 'display_id' => '500',
            'created_time' => now()->subDays(2),
        ]);

        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => function ($request) {
                if (str_contains($request->url(), '/history')) {
                    return Http::response([
                        'history' => [
                            ['operation' => 'request_note_add', 'description' => 'irrelevante'],
                            ['operation' => 'merge_with', 'description' => '500', 'time' => ['value' => '1700000005000']],
                        ],
                        'list_info' => ['has_more_rows' => false],
                    ], 200);
                }

                return Http::response([
                    'requests' => [$this->ticket(['id' => 'tk-1', 'display_id' => '600'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $absorbido->refresh();
        $this->assertSame('Combinado', $absorbido->estado_nombre);
        $this->assertSame($combinadoStatus->id, $absorbido->sdp_ticket_status_id);
        $this->assertSame('600', $absorbido->combinado_con_display_id);
        $this->assertNotNull($absorbido->combinado_detectado_en);
        $this->assertEquals(1700000005000, $absorbido->completed_time->getTimestampMs());

        // El ticket absorbente se sincronizó normal, sin marcarse a sí mismo.
        $this->assertDatabaseHas('sdp_tickets', ['sdp_id' => 'tk-1', 'combinado_con_display_id' => null]);
    }

    public function test_it_does_not_re_mark_an_already_combinado_ticket_on_a_later_run(): void
    {
        $absorbido = SdpTicket::create([
            'sdp_id' => 'tk-absorbido', 'asunto' => 'Ticket viejo', 'display_id' => '500',
            'created_time' => now()->subDays(2), 'estado_nombre' => 'Combinado',
            'combinado_con_display_id' => '600', 'combinado_detectado_en' => now()->subHour(),
        ]);
        $detectadoOriginal = $absorbido->combinado_detectado_en;

        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => function ($request) {
                if (str_contains($request->url(), '/history')) {
                    return Http::response([
                        'history' => [
                            ['operation' => 'merge_with', 'description' => '500', 'time' => ['value' => '1700000005000']],
                        ],
                        'list_info' => ['has_more_rows' => false],
                    ], 200);
                }

                return Http::response([
                    'requests' => [$this->ticket(['id' => 'tk-1', 'display_id' => '600'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $absorbido->refresh();
        $this->assertTrue($detectadoOriginal->equalTo($absorbido->combinado_detectado_en));
    }

    public function test_a_merge_pointing_to_a_display_id_never_captured_locally_does_not_create_a_placeholder(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => function ($request) {
                if (str_contains($request->url(), '/history')) {
                    return Http::response([
                        'history' => [
                            ['operation' => 'merge_with', 'description' => '999999', 'time' => ['value' => '1700000005000']],
                        ],
                        'list_info' => ['has_more_rows' => false],
                    ], 200);
                }

                return Http::response([
                    'requests' => [$this->ticket(['id' => 'tk-1', 'display_id' => '600'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(1, SdpTicket::count());
        $this->assertDatabaseMissing('sdp_tickets', ['display_id' => '999999']);
    }

    public function test_a_failure_fetching_one_tickets_history_does_not_fail_the_whole_sync(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => function ($request) {
                if (str_contains($request->url(), '/history')) {
                    return Http::response('server error', 500);
                }

                return Http::response([
                    'requests' => [$this->ticket(['id' => 'tk-1', 'display_id' => '600'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(1, SdpTicket::count());
        $this->assertDatabaseHas('sdp_tickets', ['sdp_id' => 'tk-1']);
    }

    // --- Fase 8 (Parte 6) — campos nuevos confirmados ---

    public function test_it_populates_the_new_confirmed_ticket_fields(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket([
                'id' => 'tk-1',
                'item' => ['name' => 'Laptop Dell'],
                'service_category' => ['name' => 'Hardware'],
                'level' => ['id' => 'l1', 'name' => '1. Mesa de Ayuda'],
                'assigned_time' => ['value' => '1700000001000', 'display_value' => 'x'],
                'time_elapsed' => '256000',
                'resolution' => ['content' => '<p>Resuelto</p>', 'submitted_by' => ['name' => 'Ana Resolutora']],
            ]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $ticket = SdpTicket::where('sdp_id', 'tk-1')->first();

        $this->assertSame('Laptop Dell', $ticket->articulo);
        $this->assertSame('Hardware', $ticket->categoria_servicio);
        $this->assertSame('1. Mesa de Ayuda', $ticket->nivel);
        $this->assertSame(1700000001000, $ticket->assigned_time->getTimestampMs());
        // time_elapsed llega en milisegundos ('256000' en el fixture) — se
        // guarda convertido a segundos.
        $this->assertSame(256, $ticket->tiempo_transcurrido_segundos);
        $this->assertSame('Ana Resolutora', $ticket->resuelto_por);
    }

    // --- Fase 8 (Parte 7) — resolución del sitio embebido ---

    public function test_it_creates_the_site_on_the_fly_from_the_embedded_site_object(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'site' => ['id' => 'site-1', 'name' => 'Oficina CDMX']]),
        ]);

        $this->assertSame(0, SdpSite::count());

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $ticket = SdpTicket::where('sdp_id', 'tk-1')->first();
        $site = SdpSite::where('sdp_id', 'site-1')->first();

        $this->assertNotNull($site);
        $this->assertSame('Oficina CDMX', $site->nombre);
        $this->assertSame($site->id, $ticket->sdp_site_id);
        // La columna string "sitio" ya existente sigue poblándose igual.
        $this->assertSame('Oficina CDMX', $ticket->sitio);
    }

    public function test_it_reuses_an_existing_site_instead_of_duplicating_it(): void
    {
        $existing = SdpSite::create(['sdp_id' => 'site-1', 'nombre' => 'Oficina CDMX']);

        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'site' => ['id' => 'site-1', 'name' => 'Oficina CDMX']]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(1, SdpSite::count());
        $this->assertSame($existing->id, SdpTicket::where('sdp_id', 'tk-1')->first()->sdp_site_id);
    }

    public function test_ticket_without_a_site_id_leaves_the_fk_null(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 'tk-1', 'site' => ['name' => 'Base Site']]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $this->assertSame(0, SdpSite::count());
        $this->assertNull(SdpTicket::where('sdp_id', 'tk-1')->first()->sdp_site_id);
    }

    // --- Fase 8 (Parte 3) — campos personalizados (UDF) confirmados ---

    public function test_it_populates_udf_fields_from_the_embedded_udf_fields_object(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket([
                'id' => 'tk-1',
                'udf_fields' => [
                    'udf_char24' => 'Logística',
                    'udf_char10' => 'N3 Oracle',
                    'udf_char3' => 'Aplicativos',
                    'udf_char11' => 'DEV-Oracle',
                    'udf_date1' => ['value' => '1700000001000', 'display_value' => 'x'],
                    'udf_date3' => ['value' => '1700000002000', 'display_value' => 'x'],
                    'udf_char13' => 'CASE-123',
                    'udf_char12' => 'DEV-Julio Coyotl Cortes',
                    'udf_char14' => 'N4 SAP',
                    'udf_date2' => ['value' => '1700000003000', 'display_value' => 'x'],
                    'udf_date4' => ['value' => '1700000004000', 'display_value' => 'x'],
                    'udf_char16' => 'CASE-456',
                    'udf_char15' => 'Soporte Externo',
                ],
            ]),
        ]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $ticket = SdpTicket::where('sdp_id', 'tk-1')->first();

        $this->assertSame('Logística', $ticket->area_operativa);
        $this->assertSame('N3 Oracle', $ticket->grupo_resolutor);
        $this->assertSame('Aplicativos', $ticket->super_categoria);
        $this->assertSame('DEV-Oracle', $ticket->n3_area_escalamiento);
        $this->assertSame(1700000001000, $ticket->n3_fecha_escalamiento->getTimestampMs());
        $this->assertSame(1700000002000, $ticket->n3_fecha_solucion->getTimestampMs());
        $this->assertSame('CASE-123', $ticket->n3_no_seguimiento_proveedor);
        $this->assertSame('DEV-Julio Coyotl Cortes', $ticket->n3_recurso_escalamiento);
        $this->assertSame('N4 SAP', $ticket->n4_area_escalamiento);
        $this->assertSame(1700000003000, $ticket->n4_fecha_escalamiento->getTimestampMs());
        $this->assertSame(1700000004000, $ticket->n4_fecha_solucion->getTimestampMs());
        $this->assertSame('CASE-456', $ticket->n4_no_seguimiento_proveedor);
        $this->assertSame('Soporte Externo', $ticket->n4_recurso_escalamiento);
    }

    public function test_udf_fields_default_to_null_when_absent(): void
    {
        $this->fakeTokenAndRequests([$this->ticket(['id' => 'tk-1'])]);

        $this->artisan('sdp:sync-tickets')->assertSuccessful();

        $ticket = SdpTicket::where('sdp_id', 'tk-1')->first();

        $this->assertNull($ticket->area_operativa);
        $this->assertNull($ticket->n3_fecha_escalamiento);
    }
}
