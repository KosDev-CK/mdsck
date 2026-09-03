<?php

namespace Modules\GestionTI\Tests\Feature\Ebs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Notifications\AvisoNotification;
use Modules\GestionTI\Support\Avisos\AvisoDispatcher;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncException;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;
use Tests\TestCase;

class EbsRequisitionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function service(EbsRequisitionsClient $client): EbsRequisitionSyncService
    {
        return new EbsRequisitionSyncService($client, app(AvisoDispatcher::class));
    }

    protected function mockClient(): EbsRequisitionsClient
    {
        return $this->createMock(EbsRequisitionsClient::class);
    }

    /**
     * Payload real de `requisition_header_line` (ver docs/gestionti-progreso.md).
     */
    protected function requisicionCreada(array $overrides = []): array
    {
        return array_replace_recursive([
            'requisitionHeaderId' => 2293693,
            'requisition' => ['code' => '6489', 'description' => 'Equipo Laptop Nueva', 'status' => 'APPROVED', 'date' => '2026-09-01T09:40:46.000+00:00'],
            'wf' => ['itemKey' => '2293693-108955', 'itemType' => 'REQAPPRV'],
            'action' => ['code' => '', 'date' => ''],
            'approver' => ['user' => '', 'name' => '', 'date' => ''],
            'sequenceNum' => null,
            'create' => ['user' => 'ALAN.HERNANDEZ', 'decription' => 'LANDIT'],
            'organization' => ['code' => 'L01', 'description' => 'L01'],
            'requisition_lines' => [
                [
                    'requsition_line_id' => 2574493,
                    'lineNumber' => 1,
                    'lineTypeId' => 1021,
                    'categoryId' => 2612,
                    'itemId' => 6962,
                    'itemDescription' => 'LAPTOP EQUIPO PORTATIL PERFIL EJECUTIVO CI7',
                    'unitMeasurement' => 'Pieza',
                    'unitPrice' => 1,
                    'quantity' => 1,
                    'currencyCode' => 'MXN',
                ],
            ],
        ], $overrides);
    }

    /**
     * Payload real de `requisition_header_approved` (ver docs/gestionti-progreso.md).
     */
    protected function requisicionAprobada(array $overrides = []): array
    {
        return array_replace_recursive([
            'requisitionHeaderId' => 2282192,
            'requisition' => ['code' => '6415', 'description' => 'Equipo Telefonico- Red Movil', 'status' => 'APPROVED', 'date' => '2026-08-12T13:38:04.000+00:00'],
            'wf' => ['itemKey' => '2282192-36493', 'itemType' => 'REQAPPRV'],
            'action' => ['code' => 'APPROVE', 'date' => '2026-09-01T15:58:29.000+00:00'],
            'approver' => ['user' => 'SUSANA.LILLO', 'name' => 'CK ALBURQUENQUE LILLO SUSANA DEL CARMEN', 'date' => '2026-09-01T15:58:29.000+00:00'],
            'sequenceNum' => 4,
            'create' => ['user' => 'ALAN.HERNANDEZ', 'decription' => 'LANDIT'],
            'organization' => ['code' => 'L01', 'description' => 'L01'],
            'notes' => [
                ['key' => 'Observaciones y/o justificación', 'value' => 'Reemplazo por falla'],
                ['key' => 'Nombre completo de quién solicita', 'value' => 'ANTONIO HERNANDEZ EMILIA'],
            ],
        ], $overrides);
    }

    protected function baseCatalogos(): array
    {
        $empleado = Empleado::create(['numero_empleado' => 'EMP-EBS-1', 'nombre' => 'Solicitante EBS']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id, 'sdp_display_id' => 'SDP-EBS-1']);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $empresa = Empresa::create(['razon_social' => 'Kosmos EBS S.A. de C.V.', 'nombre_comercial' => 'Kosmos EBS']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-EBS', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        return compact('empleado', 'ticket', 'tipoEquipo', 'centroCosto');
    }

    protected function crearSolicitud(array $catalogos, array $overrides = []): SolicitudSicBorrador
    {
        // 'estatus' explícito (aunque la columna tiene default 'capturado' a
        // nivel de BD) — sin esto, el atributo en memoria del objeto recién
        // creado queda null hasta un refresh() real, y el código bajo
        // prueba (que recibe la instancia directamente, no vía
        // findOrFail()) necesita el valor real ya en memoria.
        return SolicitudSicBorrador::create(array_merge([
            'ticket_id' => $catalogos['ticket']->id,
            'empleado_id' => $catalogos['empleado']->id,
            'tipo_equipo_id' => $catalogos['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $catalogos['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_CAPTURADO,
        ], $overrides));
    }

    // --- sincronizarCreadas ------------------------------------------------

    public function test_sincronizar_creadas_creates_a_new_ebs_requisition_with_its_lines(): void
    {
        $client = $this->mockClient();
        $client->method('obtenerCreadas')->willReturn([$this->requisicionCreada()]);

        $this->service($client)->sincronizarCreadas(1);

        $this->assertDatabaseHas('ebs_requisitions', [
            'requisition_header_id' => 2293693,
            'code' => '6489',
            'description' => 'Equipo Laptop Nueva',
            'status' => 'APPROVED',
            'organization_code' => 'L01',
            'created_by_user' => 'ALAN.HERNANDEZ',
            'created_by_description' => 'LANDIT',
        ]);

        $ebsRequisicion = EbsRequisition::where('requisition_header_id', 2293693)->first();
        $this->assertNotNull($ebsRequisicion->ultima_sincronizacion_creadas_at);

        $this->assertDatabaseHas('ebs_requisition_lines', [
            'ebs_requisition_id' => $ebsRequisicion->id,
            'requisition_line_id' => 2574493,
            'line_number' => 1,
            'item_description' => 'LAPTOP EQUIPO PORTATIL PERFIL EJECUTIVO CI7',
            'currency_code' => 'MXN',
        ]);
    }

    public function test_sincronizar_creadas_upserts_an_existing_requisition_ebs_data_always_wins(): void
    {
        $client = $this->mockClient();
        $client->method('obtenerCreadas')->willReturn([$this->requisicionCreada()]);

        $this->service($client)->sincronizarCreadas(1);

        $existing = EbsRequisition::where('requisition_header_id', 2293693)->first();
        $existing->update(['description' => 'Descripción local que se debe pisar', 'status' => 'REJECTED']);

        // Corrida real de EBS con datos frescos.
        $this->service($client)->sincronizarCreadas(1);

        $this->assertSame(1, EbsRequisition::where('requisition_header_id', 2293693)->count());
        $this->assertDatabaseHas('ebs_requisitions', [
            'requisition_header_id' => 2293693,
            'description' => 'Equipo Laptop Nueva',
            'status' => 'APPROVED',
        ]);

        // Las líneas se borran y recrean, no se acumulan duplicadas.
        $this->assertSame(1, $existing->fresh()->lines()->count());
    }

    public function test_sincronizar_creadas_links_automatically_by_matching_code_and_folio_sic(): void
    {
        $catalogos = $this->baseCatalogos();
        $solicitud = $this->crearSolicitud($catalogos, ['folio_sic' => '6489']);

        $client = $this->mockClient();
        $client->method('obtenerCreadas')->willReturn([$this->requisicionCreada()]);

        $this->service($client)->sincronizarCreadas(1);

        $ebsRequisicion = EbsRequisition::where('requisition_header_id', 2293693)->first();

        $this->assertSame($ebsRequisicion->id, $solicitud->fresh()->ebs_requisition_id);
        // status = APPROVED en el fixture -> mapea a autorizada.
        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->fresh()->estatus);
    }

    public function test_sincronizar_creadas_does_not_relink_a_solicitud_already_linked_to_a_different_requisition(): void
    {
        $catalogos = $this->baseCatalogos();

        $otraRequisicion = EbsRequisition::create([
            'requisition_header_id' => 999999,
            'code' => 'OTRO-FOLIO',
        ]);

        $solicitud = $this->crearSolicitud($catalogos, [
            'folio_sic' => '6489',
            'ebs_requisition_id' => $otraRequisicion->id,
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
        ]);

        $client = $this->mockClient();
        $client->method('obtenerCreadas')->willReturn([$this->requisicionCreada()]);

        $this->service($client)->sincronizarCreadas(1);

        $solicitud->refresh();
        $this->assertSame($otraRequisicion->id, $solicitud->ebs_requisition_id);
        // No se tocó el estatus tampoco, porque no se consideró candidata.
        $this->assertSame(SolicitudSicBorrador::ESTATUS_SIC_CREADA, $solicitud->estatus);
    }

    public function test_sincronizar_creadas_never_touches_capture_fields(): void
    {
        $catalogos = $this->baseCatalogos();
        $solicitud = $this->crearSolicitud($catalogos, [
            'folio_sic' => '6489',
            'motivo' => 'Motivo original de captura humana',
        ]);

        $client = $this->mockClient();
        $client->method('obtenerCreadas')->willReturn([$this->requisicionCreada()]);

        $this->service($client)->sincronizarCreadas(1);

        $this->assertSame('Motivo original de captura humana', $solicitud->fresh()->motivo);
        $this->assertSame($catalogos['empleado']->id, $solicitud->fresh()->empleado_id);
    }

    // --- mapeo de estatus ---------------------------------------------------

    public function test_mapear_estatus_local_the_three_cases(): void
    {
        $this->assertSame(
            SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            EbsRequisitionSyncService::mapearEstatusLocal('IN PROCESS', SolicitudSicBorrador::ESTATUS_CAPTURADO)
        );

        $this->assertSame(
            SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            EbsRequisitionSyncService::mapearEstatusLocal('APPROVED', SolicitudSicBorrador::ESTATUS_SIC_CREADA)
        );

        $this->assertSame(
            SolicitudSicBorrador::ESTATUS_RECHAZADA,
            EbsRequisitionSyncService::mapearEstatusLocal('REJECTED', SolicitudSicBorrador::ESTATUS_SIC_CREADA)
        );
    }

    public function test_mapear_estatus_local_in_process_does_not_regress_an_already_advanced_status(): void
    {
        $this->assertNull(
            EbsRequisitionSyncService::mapearEstatusLocal('IN PROCESS', SolicitudSicBorrador::ESTATUS_AUTORIZADA)
        );
    }

    public function test_mapear_estatus_local_unknown_status_returns_null(): void
    {
        $this->assertNull(
            EbsRequisitionSyncService::mapearEstatusLocal('CANCELLED', SolicitudSicBorrador::ESTATUS_CAPTURADO)
        );
    }

    // --- sincronizarAprobadas + avisos --------------------------------------

    /**
     * `sincronizarAprobadas()` intencionalmente NO escribe `code`/`description`
     * (ver el propio método) — depende de que `sincronizarCreadas()` ya haya
     * corrido antes ese mismo día (como pasa siempre en producción, y en el
     * comando programado). Estos tests que ejercitan `sincronizarAprobadas()`
     * en aislamiento necesitan sembrar ese `code` a mano para poder vincular.
     */
    protected function seedEbsRequisitionConCode(int $headerId, string $code): EbsRequisition
    {
        return EbsRequisition::create(['requisition_header_id' => $headerId, 'code' => $code]);
    }

    public function test_sincronizar_aprobadas_dispatches_aviso_when_solicitud_transitions_to_autorizada(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $catalogos = $this->baseCatalogos();
        $catalogos['empleado']->update(['correo' => 'ebs-solicitante@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'ebs-solicitante@example.com']);

        $solicitud = $this->crearSolicitud($catalogos, [
            'folio_sic' => '6415',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
        ]);
        $this->seedEbsRequisitionConCode(2282192, '6415');

        $client = $this->mockClient();
        $client->method('obtenerAprobadas')->willReturn([$this->requisicionAprobada()]);

        $this->service($client)->sincronizarAprobadas(1, dispararAvisos: true);

        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->fresh()->estatus);
        Notification::assertSentTo($solicitanteUser, AvisoNotification::class);
    }

    public function test_sincronizar_aprobadas_replaces_notes_and_does_not_touch_lines(): void
    {
        $client = $this->mockClient();
        $client->method('obtenerCreadas')->willReturn([$this->requisicionCreada(['requisitionHeaderId' => 2282192, 'requisition' => ['code' => '6415']])]);
        $client->method('obtenerAprobadas')->willReturn([$this->requisicionAprobada()]);

        $service = $this->service($client);
        $service->sincronizarCreadas(1);
        $service->sincronizarAprobadas(1, dispararAvisos: false);

        $ebsRequisicion = EbsRequisition::where('requisition_header_id', 2282192)->first();

        $this->assertSame(2, $ebsRequisicion->notes()->count());
        $this->assertDatabaseHas('ebs_requisition_notes', [
            'ebs_requisition_id' => $ebsRequisicion->id,
            'clave' => 'Nombre completo de quién solicita',
            'valor' => 'ANTONIO HERNANDEZ EMILIA',
        ]);
        // sincronizarAprobadas nunca toca requisition_lines: la línea creada
        // por sincronizarCreadas() sigue intacta.
        $this->assertSame(1, $ebsRequisicion->lines()->count());
    }

    public function test_sincronizar_aprobadas_does_not_dispatch_aviso_when_disparar_avisos_is_false(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $catalogos = $this->baseCatalogos();
        $catalogos['empleado']->update(['correo' => 'ebs-sin-aviso@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'ebs-sin-aviso@example.com']);

        $solicitud = $this->crearSolicitud($catalogos, [
            'folio_sic' => '6415',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
        ]);
        $this->seedEbsRequisitionConCode(2282192, '6415');

        $client = $this->mockClient();
        $client->method('obtenerAprobadas')->willReturn([$this->requisicionAprobada()]);

        $this->service($client)->sincronizarAprobadas(1, dispararAvisos: false);

        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->fresh()->estatus);
        Notification::assertNotSentTo($solicitanteUser, AvisoNotification::class);
    }

    public function test_sincronizar_aprobadas_does_not_duplicate_aviso_when_run_twice_with_the_same_status_already_reached(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $catalogos = $this->baseCatalogos();
        $catalogos['empleado']->update(['correo' => 'ebs-doble@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'ebs-doble@example.com']);

        $solicitud = $this->crearSolicitud($catalogos, [
            'folio_sic' => '6415',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
        ]);
        $this->seedEbsRequisitionConCode(2282192, '6415');

        $client = $this->mockClient();
        $client->method('obtenerAprobadas')->willReturn([$this->requisicionAprobada()]);

        $service = $this->service($client);
        $service->sincronizarAprobadas(1, dispararAvisos: true);
        $service->sincronizarAprobadas(1, dispararAvisos: true);

        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->fresh()->estatus);
        Notification::assertSentToTimes($solicitanteUser, AvisoNotification::class, 1);
    }

    public function test_sincronizar_aprobadas_rejected_maps_to_rechazada_and_dispatches_the_rejection_aviso(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $catalogos = $this->baseCatalogos();
        $catalogos['empleado']->update(['correo' => 'ebs-rechazo@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'ebs-rechazo@example.com']);

        $solicitud = $this->crearSolicitud($catalogos, [
            'folio_sic' => '6415',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
        ]);
        $this->seedEbsRequisitionConCode(2282192, '6415');

        $client = $this->mockClient();
        $client->method('obtenerAprobadas')->willReturn([
            $this->requisicionAprobada(['requisition' => ['status' => 'REJECTED']]),
        ]);

        $this->service($client)->sincronizarAprobadas(1, dispararAvisos: true);

        $this->assertSame(SolicitudSicBorrador::ESTATUS_RECHAZADA, $solicitud->fresh()->estatus);
        Notification::assertSentTo($solicitanteUser, AvisoNotification::class);
    }

    // --- vincularManualmente -------------------------------------------------

    public function test_vincular_manualmente_links_and_syncs_the_local_status(): void
    {
        $catalogos = $this->baseCatalogos();
        $solicitud = $this->crearSolicitud($catalogos);

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 555,
            'code' => 'MANUAL-1',
            'status' => 'APPROVED',
        ]);

        $client = $this->mockClient();
        $this->service($client)->vincularManualmente($solicitud, $ebsRequisicion);

        $solicitud->refresh();
        $this->assertSame($ebsRequisicion->id, $solicitud->ebs_requisition_id);
        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->estatus);
    }

    // --- excepciones del cliente se propagan (decisión de quien llama) -----

    public function test_sincronizar_creadas_propagates_client_exceptions_to_the_caller(): void
    {
        $client = $this->mockClient();
        $client->method('obtenerCreadas')->willThrowException(new EbsRequisitionSyncException('EBS caída'));

        $this->expectException(EbsRequisitionSyncException::class);

        $this->service($client)->sincronizarCreadas(1);
    }
}
