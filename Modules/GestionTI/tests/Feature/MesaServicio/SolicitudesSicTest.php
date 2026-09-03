<?php

namespace Modules\GestionTI\Tests\Feature\MesaServicio;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\MesaServicio\SolicitudesSic;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\UnidadNegocio;
use Modules\GestionTI\Notifications\AvisoNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudesSicTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Solicitud de SIC',
            'slug' => 'gestionti-solicitudes-sic',
            'route_name' => 'gestionti.solicitudes-sic.index',
            'permission_name' => 'screens.gestionti-solicitudes-sic.manage',
            'icon' => 'document-text',
            'order' => 2,
        ]);

        $role = Role::findOrCreate('Mesa de Servicio', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function baseCatalogos(): array
    {
        $empleado = Empleado::create(['numero_empleado' => 'EMP-100', 'nombre' => 'Solicitante Uno']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id, 'sdp_display_id' => 'SDP-001']);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $empresa = Empresa::create(['razon_social' => 'Kosmos Demo S.A. de C.V.', 'nombre_comercial' => 'Kosmos Demo']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-100', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $unidadNegocio = UnidadNegocio::create(['nombre' => 'CEDIS']);

        return compact('empleado', 'ticket', 'tipoEquipo', 'centroCosto', 'unidadNegocio');
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/solicitudes-sic')->assertForbidden();
    }

    public function test_can_create_a_solicitud_sic_starting_in_capturado(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        Livewire::test(SolicitudesSic::class)
            ->call('create')
            ->set('form.ticket_id', $c['ticket']->id)
            ->set('form.empleado_id', $c['empleado']->id)
            ->set('form.tipo_equipo_id', $c['tipoEquipo']->id)
            ->set('form.motivo', 'Equipo nuevo para ingreso')
            ->set('form.centro_costo_id', $c['centroCosto']->id)
            ->set('form.urgencia', 'media')
            ->set('form.fecha_solicitud', '2026-08-31')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_sic_borrador', [
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'estatus' => SolicitudSicBorrador::ESTATUS_CAPTURADO,
        ]);
    }

    public function test_validation_requires_the_core_fields(): void
    {
        $this->actingAs($this->actingUser());

        // form.fecha_solicitud no se incluye aquí porque create() la
        // precarga con la fecha de hoy (default razonable del formulario),
        // así que nunca llega vacía a validate().
        Livewire::test(SolicitudesSic::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors([
                'form.ticket_id', 'form.empleado_id', 'form.tipo_equipo_id',
                'form.motivo', 'form.centro_costo_id', 'form.urgencia',
            ]);
    }

    public function test_urgencia_must_be_one_of_the_three_allowed_values(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        Livewire::test(SolicitudesSic::class)
            ->call('create')
            ->set('form.ticket_id', $c['ticket']->id)
            ->set('form.empleado_id', $c['empleado']->id)
            ->set('form.tipo_equipo_id', $c['tipoEquipo']->id)
            ->set('form.motivo', 'Equipo nuevo')
            ->set('form.centro_costo_id', $c['centroCosto']->id)
            ->set('form.urgencia', 'urgentisima')
            ->set('form.fecha_solicitud', '2026-08-31')
            ->call('save')
            ->assertHasErrors(['form.urgencia']);
    }

    public function test_can_leave_unidad_negocio_unassigned(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        Livewire::test(SolicitudesSic::class)
            ->call('create')
            ->set('form.ticket_id', $c['ticket']->id)
            ->set('form.empleado_id', $c['empleado']->id)
            ->set('form.tipo_equipo_id', $c['tipoEquipo']->id)
            ->set('form.motivo', 'Equipo nuevo')
            ->set('form.centro_costo_id', $c['centroCosto']->id)
            ->set('form.unidad_negocio_id', '')
            ->set('form.urgencia', 'baja')
            ->set('form.fecha_solicitud', '2026-08-31')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_sic_borrador', [
            'empleado_id' => $c['empleado']->id,
            'unidad_negocio_id' => null,
        ]);
    }

    public function test_uploading_an_adjunto_creates_a_documento_digitalizado(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $file = UploadedFile::fake()->create('sic.pdf', 100, 'application/pdf');

        Livewire::test(SolicitudesSic::class)
            ->call('create')
            ->set('form.ticket_id', $c['ticket']->id)
            ->set('form.empleado_id', $c['empleado']->id)
            ->set('form.tipo_equipo_id', $c['tipoEquipo']->id)
            ->set('form.motivo', 'Equipo nuevo')
            ->set('form.centro_costo_id', $c['centroCosto']->id)
            ->set('form.urgencia', 'alta')
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('adjunto', $file)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudSicBorrador::first();

        $this->assertDatabaseHas('documentos_digitalizados', [
            'entidad_relacionada' => 'SolicitudSicBorrador',
            'entidad_id' => $solicitud->id,
            'tipo_documento' => 'sic',
            'nombre_archivo' => 'sic.pdf',
        ]);

        $documento = $solicitud->documentoAdjunto();
        $this->assertNotNull($documento);
        Storage::disk('public')->assertExists($documento->referencia);
    }

    public function test_estatus_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $capturada = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Capturada',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
        ]);

        $autorizada = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Autorizada',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'alta',
            'fecha_solicitud' => '2026-08-02',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'SIC-1',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->set('estatusFilter', SolicitudSicBorrador::ESTATUS_AUTORIZADA)
            ->assertSee('Autorizada')
            ->assertDontSee('Capturada');
    }

    public function test_can_advance_from_capturado_to_sic_creada_with_folio(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('openAdvance', $solicitud->id)
            ->set('advanceFolioSic', 'SIC-98765')
            ->call('confirmAdvanceToSicCreada')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_sic_borrador', [
            'id' => $solicitud->id,
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'SIC-98765',
        ]);
    }

    /**
     * Fase 5, punto 1 (EBS) — "buscar y elegir" una `EbsRequisition` ya
     * importada en vez de escribir el folio a mano. La captura de texto
     * libre (sin elegir ninguna) sigue funcionando exactamente igual — ver
     * `test_can_advance_from_capturado_to_sic_creada_with_folio()` arriba,
     * que no toca `advanceEbsRequisitionId` en absoluto y confirma que ese
     * camino no se rompió.
     */
    public function test_picking_an_ebs_requisition_autocompletes_the_folio_and_links_it(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
        ]);

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => '6489',
            'description' => 'Equipo Laptop Nueva',
            'status' => 'APPROVED',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('openAdvance', $solicitud->id)
            ->set('advanceEbsRequisitionId', $ebsRequisicion->id)
            ->assertSet('advanceFolioSic', '6489')
            ->call('confirmAdvanceToSicCreada')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame(SolicitudSicBorrador::ESTATUS_SIC_CREADA, $solicitud->estatus);
        $this->assertSame('6489', $solicitud->folio_sic);
        $this->assertSame($ebsRequisicion->id, $solicitud->ebs_requisition_id);
    }

    public function test_leaving_the_ebs_requisition_unselected_keeps_the_manual_text_entry_behavior(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('openAdvance', $solicitud->id)
            ->set('advanceFolioSic', 'FOLIO-MANUAL-1')
            ->call('confirmAdvanceToSicCreada')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame(SolicitudSicBorrador::ESTATUS_SIC_CREADA, $solicitud->estatus);
        $this->assertSame('FOLIO-MANUAL-1', $solicitud->folio_sic);
        $this->assertNull($solicitud->ebs_requisition_id);
    }

    public function test_cannot_pick_an_ebs_requisition_already_linked_to_another_solicitud(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $otraSolicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Otra',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
        ]);

        $ebsRequisicion = EbsRequisition::create(['requisition_header_id' => 1, 'code' => '6489']);
        $otraSolicitud->update(['ebs_requisition_id' => $ebsRequisicion->id]);

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('openAdvance', $solicitud->id)
            ->set('advanceEbsRequisitionId', $ebsRequisicion->id)
            ->set('advanceFolioSic', '6489')
            ->call('confirmAdvanceToSicCreada')
            ->assertHasErrors(['advanceEbsRequisitionId']);

        $this->assertNull($solicitud->fresh()->ebs_requisition_id);
        $this->assertSame(SolicitudSicBorrador::ESTATUS_CAPTURADO, $solicitud->fresh()->estatus);
    }

    public function test_advance_requires_a_folio(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('openAdvance', $solicitud->id)
            ->set('advanceFolioSic', '')
            ->call('confirmAdvanceToSicCreada')
            ->assertHasErrors(['advanceFolioSic']);
    }

    public function test_can_mark_sic_creada_as_autorizada(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'SIC-1',
        ]);

        Livewire::test(SolicitudesSic::class)->call('marcarAutorizada', $solicitud->id);

        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->fresh()->estatus);
    }

    public function test_can_mark_sic_creada_as_rechazada(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'SIC-1',
        ]);

        Livewire::test(SolicitudesSic::class)->call('marcarRechazada', $solicitud->id);

        $this->assertSame(SolicitudSicBorrador::ESTATUS_RECHAZADA, $solicitud->fresh()->estatus);
    }

    public function test_cannot_skip_directly_from_capturado_to_autorizada(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
        ]);

        Livewire::test(SolicitudesSic::class)->call('marcarAutorizada', $solicitud->id);

        $this->assertSame(SolicitudSicBorrador::ESTATUS_CAPTURADO, $solicitud->fresh()->estatus);
    }

    public function test_can_edit_an_existing_solicitud(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Motivo original',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('edit', $solicitud->id)
            ->assertSet('form.motivo', 'Motivo original')
            ->set('form.motivo', 'Motivo actualizado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_sic_borrador', [
            'id' => $solicitud->id,
            'motivo' => 'Motivo actualizado',
        ]);
    }

    public function test_export_sic_pdf_generates_the_file_with_all_data_captured(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo para ingreso',
            'especificaciones_requeridas' => '16GB RAM, SSD 512GB',
            'centro_costo_id' => $c['centroCosto']->id,
            'unidad_negocio_id' => $c['unidadNegocio']->id,
            'urgencia' => 'alta',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'SIC-12345',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('exportSicPdf', $solicitud->id)
            ->assertFileDownloaded('formato-sic-SIC-12345.pdf');
    }

    public function test_export_sic_pdf_works_without_optional_fields(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'unidad_negocio_id' => null,
            'especificaciones_requeridas' => null,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_CAPTURADO,
            'folio_sic' => 'SIC-54321',
        ]);

        Livewire::test(SolicitudesSic::class)
            ->call('exportSicPdf', $solicitud->id)
            ->assertFileDownloaded('formato-sic-SIC-54321.pdf');
    }

    public function test_export_sic_pdf_without_folio_shows_sin_folio_asignado(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_CAPTURADO,
        ]);

        $html = view('gestionti::pdf.formato-sic', [
            'solicitud' => $solicitud->fresh(['ticket', 'empleado', 'tipoEquipo', 'centroCosto', 'unidadNegocio']),
        ])->render();
        $this->assertStringContainsString('Sin folio asignado', $html);

        Livewire::test(SolicitudesSic::class)
            ->call('exportSicPdf', $solicitud->id)
            ->assertFileDownloaded('formato-sic-'.$solicitud->id.'.pdf');
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-solicitudes-sic',
            'route_name' => 'gestionti.solicitudes-sic.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-solicitudes-sic.manage'));
    }

    /**
     * Confirma que `marcarAutorizada()` dispara `SIC_AUTORIZADA` (Fase 4,
     * "Configuración de Avisos") hacia el solicitante — resuelto por
     * coincidencia de correo, ver docs/gestionti-progreso.md.
     */
    public function test_marking_as_autorizada_dispatches_sic_autorizada_aviso(): void
    {
        $this->actingAs($this->actingUser());
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $c = $this->baseCatalogos();
        $c['empleado']->update(['correo' => 'solicitante@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'solicitante@example.com']);

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'SIC-AUTORIZADA-1',
        ]);

        Livewire::test(SolicitudesSic::class)->call('marcarAutorizada', $solicitud->id);

        Notification::assertSentTo($solicitanteUser, AvisoNotification::class);
        $this->assertSame(2, AvisoEnviado::where('destinatario_user_id', $solicitanteUser->id)->count());
    }

    /**
     * Mismo criterio que arriba, para `marcarRechazada()` / `SIC_RECHAZADA`.
     */
    public function test_marking_as_rechazada_dispatches_sic_rechazada_aviso(): void
    {
        $this->actingAs($this->actingUser());
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $c = $this->baseCatalogos();
        $c['empleado']->update(['correo' => 'solicitante2@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'solicitante2@example.com']);

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'SIC-RECHAZADA-1',
        ]);

        Livewire::test(SolicitudesSic::class)->call('marcarRechazada', $solicitud->id);

        Notification::assertSentTo($solicitanteUser, AvisoNotification::class);
        $this->assertSame(2, AvisoEnviado::where('destinatario_user_id', $solicitanteUser->id)->count());
    }
}
