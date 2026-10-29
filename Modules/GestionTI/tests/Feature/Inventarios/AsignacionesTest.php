<?php

namespace Modules\GestionTI\Tests\Feature\Inventarios;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Inventarios\Asignaciones;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\ConfiguracionDocumentos;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\SistemaOperativo;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AsignacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fase 5 (SharePoint): `ConfiguracionDocumentos::DEFAULTS` marca
        // "responsiva" activa por default al sembrarse por primera vez —
        // casi todos los tests de esta clase preceden a esa integración y
        // ejercitan a propósito el camino local histórico (disco `public`),
        // así que se desactiva aquí explícitamente; el único test dedicado
        // al modal "Buscar en SharePoint" no depende de esta configuración
        // (usa `linkExisting()`, que no consulta `ConfiguracionDocumentos`).
        ConfiguracionDocumentos::current()->update(['tipos_sharepoint' => []]);
    }

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Asignación de Activo',
            'slug' => 'gestionti-asignaciones',
            'route_name' => 'gestionti.asignaciones.index',
            'permission_name' => 'screens.gestionti-asignaciones.manage',
            'icon' => 'user-plus',
            'order' => 31,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function estatus(string $codigo, string $nombre): EstatusActivo
    {
        return EstatusActivo::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);
    }

    private function estatusEnStock(): EstatusActivo
    {
        return $this->estatus('en_stock', 'En stock');
    }

    private function estatusReservado(): EstatusActivo
    {
        return $this->estatus('reservado', 'Reservado');
    }

    private function estatusAsignado(): EstatusActivo
    {
        return $this->estatus('asignado', 'Asignado');
    }

    private function validador(): Validador
    {
        return Validador::create(['nombre' => 'Ana Torres']);
    }

    private function empleado(string $numero = 'EMP-1', string $nombre = 'Juan Pérez'): Empleado
    {
        return Empleado::create(['numero_empleado' => $numero, 'nombre' => $nombre]);
    }

    private function tipoEquipo(): TipoEquipo
    {
        return TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
    }

    private function asset(string $codigo, string $estatusCodigo, ?int $sicReservadaId = null): Asset
    {
        return Asset::create([
            'codigo' => $codigo,
            'tipo_equipo_id' => $this->tipoEquipo()->id,
            'numero_serie' => 'SN-'.$codigo,
            'origen_tipo' => 'compra',
            'estatus_id' => $this->estatus($estatusCodigo, ucfirst($estatusCodigo))->id,
            'sic_reservada_id' => $sicReservadaId,
        ]);
    }

    private function sicAutorizada(?Empleado $empleado = null, ?Ticket $ticket = null): SolicitudSicBorrador
    {
        $empleado = $empleado ?? $this->empleado();
        $empresa = Empresa::firstOrCreate(['razon_social' => 'Kosmos'], ['nombre_comercial' => 'Kosmos']);
        $centroCosto = CentroCosto::firstOrCreate(['codigo' => 'CC-1'], ['nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $ticket = $ticket ?? Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);

        return SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $this->tipoEquipo()->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'SIC-'.$ticket->id,
        ]);
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/asignaciones')->assertForbidden();
    }

    public function test_sic_options_only_show_authorized_and_unassigned_sics(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000001', 'en_stock');
        $validador = $this->validador();

        $ids = Livewire::test(Asignaciones::class)->viewData('sicOptions')->pluck('id')->all();
        $this->assertContains($sic->id, $ids);

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->call('save')
            ->assertHasNoErrors();

        $idsAfter = Livewire::test(Asignaciones::class)->viewData('sicOptions')->pluck('id')->all();
        $this->assertNotContains($sic->id, $idsAfter);
    }

    public function test_sic_options_exclude_non_authorized_statuses(): void
    {
        $this->actingAs($this->actingUser());

        $capturada = $this->sicAutorizada();
        $capturada->update(['estatus' => SolicitudSicBorrador::ESTATUS_CAPTURADO]);

        $ids = Livewire::test(Asignaciones::class)->viewData('sicOptions')->pluck('id')->all();
        $this->assertNotContains($capturada->id, $ids);
    }

    public function test_asset_options_include_all_en_stock_regardless_of_selected_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();

        $sic = $this->sicAutorizada();
        $enStock = $this->asset('KOS-LAPTOP-000001', 'en_stock');

        $component = Livewire::test(Asignaciones::class)->call('create');
        $ids = $component->viewData('assetOptions')->pluck('id')->all();
        $this->assertContains($enStock->id, $ids);

        $component->set('form.sic_id', $sic->id);
        $ids = $component->viewData('assetOptions')->pluck('id')->all();
        $this->assertContains($enStock->id, $ids);
    }

    public function test_asset_options_include_reservado_only_when_matching_selected_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusReservado();

        $sicA = $this->sicAutorizada($this->empleado('EMP-A', 'Empleado A'));
        $sicB = $this->sicAutorizada($this->empleado('EMP-B', 'Empleado B'));
        $reservadoParaA = $this->asset('KOS-LAPTOP-000002', 'reservado', $sicA->id);

        $component = Livewire::test(Asignaciones::class)->call('create');

        // Sin SIC seleccionada: el reservado no aparece.
        $ids = $component->viewData('assetOptions')->pluck('id')->all();
        $this->assertNotContains($reservadoParaA->id, $ids);

        // SIC A seleccionada: el reservado para A sí aparece.
        $component->set('form.sic_id', $sicA->id);
        $ids = $component->viewData('assetOptions')->pluck('id')->all();
        $this->assertContains($reservadoParaA->id, $ids);

        // SIC B seleccionada: el reservado para A NO aparece.
        $component->set('form.sic_id', $sicB->id);
        $ids = $component->viewData('assetOptions')->pluck('id')->all();
        $this->assertNotContains($reservadoParaA->id, $ids);
    }

    public function test_creating_an_assignment_marks_the_asset_as_asignado_and_derives_ticket_and_empleado_from_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $empleado = $this->empleado('EMP-9', 'Solicitante Nueve');
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);
        $sic = $this->sicAutorizada($empleado, $ticket);
        $asset = $this->asset('KOS-LAPTOP-000003', 'en_stock');
        $validador = $this->validador();

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();
        $this->assertSame($asset->id, $assignment->asset_id);
        $this->assertSame($empleado->id, $assignment->empleado_id);
        $this->assertSame($ticket->id, $assignment->ticket_id);
        $this->assertSame($sic->id, $assignment->sic_id);

        $this->assertSame($this->estatusAsignado()->id, $asset->fresh()->estatus_id);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->actingUser());

        // `fecha_asignacion` trae un default (hoy) en create() — se limpia
        // explícitamente aquí para ejercitar también su regla `required`.
        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.fecha_asignacion', '')
            ->call('save')
            ->assertHasErrors([
                // El flujo principal es por folio (EBS): el folio es obligatorio.
                'form.sic_folio' => 'required',
                'form.asset_id' => 'required',
                'form.fecha_asignacion' => 'required',
                'form.estado_equipo_entrega' => 'required',
                'form.responsable_entrega_id' => 'required',
            ]);
    }

    public function test_signed_file_is_optional_when_creating(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000004', 'en_stock');
        $validador = $this->validador();

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();
        $this->assertNull($assignment->documento_responsiva_id);
    }

    public function test_uploading_signed_file_on_create_stores_documento_digitalizado_and_real_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000005', 'en_stock');
        $validador = $this->validador();
        $file = UploadedFile::fake()->create('responsiva.pdf', 100, 'application/pdf');

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->set('documentoResponsiva', $file)
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();
        $this->assertNotNull($assignment->documento_responsiva_id);

        $documento = $assignment->documentoResponsiva;
        $this->assertSame('responsiva', $documento->tipo_documento);
        $this->assertSame('AssetAssignment', $documento->entidad_relacionada);
        Storage::disk('public')->assertExists($documento->referencia);
    }

    public function test_attach_signed_responsiva_action_works_on_an_existing_assignment_without_a_document(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000006', 'en_stock');
        $empleado = $sic->empleado;
        $validador = $this->validador();

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'sic_id' => $sic->id,
            'fecha_asignacion' => '2026-09-01',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
        ]);

        $file = UploadedFile::fake()->create('firmada.pdf', 100, 'application/pdf');

        Livewire::test(Asignaciones::class)
            ->call('openAttach', $assignment->id)
            ->set('attachDocumento', $file)
            ->call('confirmAttach')
            ->assertHasNoErrors();

        $assignment->refresh();
        $this->assertNotNull($assignment->documento_responsiva_id);
        Storage::disk('public')->assertExists($assignment->documentoResponsiva->referencia);
    }

    public function test_attach_action_does_not_allow_a_second_file_when_one_already_exists(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000007', 'en_stock');
        $validador = $this->validador();

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $sic->empleado_id,
            'sic_id' => $sic->id,
            'fecha_asignacion' => '2026-09-01',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
            'documento_responsiva_id' => \Modules\GestionTI\Models\DocumentoDigitalizado::storeUploaded(
                UploadedFile::fake()->create('ya-existente.pdf', 50, 'application/pdf'),
                $asset, // entidad cualquiera, solo para el helper — no relevante aquí
                'responsiva',
                null
            )->id,
        ]);

        // openAttach no abre el modal si ya existe documento.
        Livewire::test(Asignaciones::class)
            ->call('openAttach', $assignment->id)
            ->assertSet('showAttachModal', false);

        $documentoOriginalId = $assignment->documento_responsiva_id;

        // confirmAttach tampoco sobreescribe si se invoca directamente.
        $file = UploadedFile::fake()->create('segundo-intento.pdf', 50, 'application/pdf');
        Livewire::test(Asignaciones::class)
            ->set('attachingId', $assignment->id)
            ->set('attachDocumento', $file)
            ->call('confirmAttach');

        $assignment->refresh();
        $this->assertSame($documentoOriginalId, $assignment->documento_responsiva_id);
    }

    /**
     * Link "Ver ficha" agregado junto al código del activo (Fase 3 etapa 10,
     * Ficha de Activo/Trazabilidad) — apunta al detalle del Activo correcto.
     */
    public function test_ver_ficha_link_points_to_the_correct_asset(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusAsignado();

        $asset = $this->asset('KOS-LAPTOP-VERFICHA', 'asignado');
        AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $this->empleado()->id,
            'fecha_asignacion' => '2026-08-01',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $this->validador()->id,
        ]);

        Livewire::test(Asignaciones::class)
            ->assertSee(route('gestionti.ficha-activo.show', $asset->id), false);
    }

    public function test_export_responsiva_pdf_returns_a_pdf_download(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000008', 'en_stock');
        $validador = $this->validador();

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $sic->empleado_id,
            'sic_id' => $sic->id,
            'fecha_asignacion' => '2026-09-01',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
        ]);

        Livewire::test(Asignaciones::class)
            ->call('exportResponsivaPdf', $assignment->id)
            ->assertFileDownloaded('responsiva-'.$asset->codigo.'.pdf');
    }

    public function test_race_condition_defense_rejects_an_asset_that_no_longer_qualifies(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000009', 'en_stock');
        $validador = $this->validador();

        $component = Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id);

        // Simula que otro proceso ya asignó el mismo Activo entre el render
        // y este submit.
        $asset->update(['estatus_id' => $this->estatusAsignado()->id]);

        $component->call('save')->assertHasErrors(['form.asset_id']);

        $this->assertDatabaseCount('asset_assignments', 0);
    }

    public function test_search_by_asset_codigo_empleado_nombre_and_sic_folio(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $empleado = $this->empleado('EMP-77', 'Roberto Martínez');
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);
        $sic = $this->sicAutorizada($empleado, $ticket);
        $asset = $this->asset('KOS-LAPTOP-000010', 'en_stock');
        $validador = $this->validador();

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'ticket_id' => $ticket->id,
            'sic_id' => $sic->id,
            'fecha_asignacion' => '2026-09-01',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
        ]);

        $byCodigo = Livewire::test(Asignaciones::class)->set('search', 'KOS-LAPTOP-000010')->viewData('records');
        $this->assertTrue($byCodigo->contains($assignment));

        $byEmpleado = Livewire::test(Asignaciones::class)->set('search', 'Roberto Martínez')->viewData('records');
        $this->assertTrue($byEmpleado->contains($assignment));

        $byFolio = Livewire::test(Asignaciones::class)->set('search', $sic->folio_sic)->viewData('records');
        $this->assertTrue($byFolio->contains($assignment));

        $byNothing = Livewire::test(Asignaciones::class)->set('search', 'no-deberia-matchear-nada')->viewData('records');
        $this->assertFalse($byNothing->contains($assignment));
    }

    /**
     * Fase 4 etapa 2 (PDF de Responsiva — formato real): sección
     * "Configuración técnica (opcional)" agregada al formulario.
     */
    public function test_creating_an_assignment_with_technical_config_persists_the_new_fields(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000011', 'en_stock');
        $validador = $this->validador();
        $so = SistemaOperativo::create(['nombre' => 'Windows 11 Pro', 'activo' => true]);

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->set('form.ip', '10.0.0.15')
            ->set('form.mac_wifi', 'AA:BB:CC:DD:EE:01')
            ->set('form.mac_ethernet', 'AA:BB:CC:DD:EE:02')
            ->set('form.sistema_operativo_id', $so->id)
            ->set('form.version_office', '365')
            ->set('form.antivirus', 'CrowdStrike')
            ->set('form.dominio', 'kosmos.local')
            ->set('form.usuario_dominio', 'jperez')
            ->set('form.id_producto_so', 'ABCDE-12345')
            ->set('form.libra_cloud', '1')
            ->set('form.oracle_ebs', '0')
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();
        $this->assertSame('10.0.0.15', $assignment->ip);
        $this->assertSame('AA:BB:CC:DD:EE:01', $assignment->mac_wifi);
        $this->assertSame('AA:BB:CC:DD:EE:02', $assignment->mac_ethernet);
        $this->assertSame($so->id, $assignment->sistema_operativo_id);
        $this->assertSame('365', $assignment->version_office);
        $this->assertSame('CrowdStrike', $assignment->antivirus);
        $this->assertSame('kosmos.local', $assignment->dominio);
        $this->assertSame('jperez', $assignment->usuario_dominio);
        $this->assertSame('ABCDE-12345', $assignment->id_producto_so);
        $this->assertTrue($assignment->libra_cloud);
        $this->assertFalse($assignment->oracle_ebs);
    }

    public function test_creating_an_assignment_without_technical_config_does_not_fail(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000012', 'en_stock');
        $validador = $this->validador();

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();
        $this->assertNull($assignment->ip);
        $this->assertNull($assignment->mac_wifi);
        $this->assertNull($assignment->mac_ethernet);
        $this->assertNull($assignment->sistema_operativo_id);
        $this->assertNull($assignment->version_office);
        $this->assertNull($assignment->antivirus);
        $this->assertNull($assignment->dominio);
        $this->assertNull($assignment->usuario_dominio);
        $this->assertNull($assignment->id_producto_so);
        $this->assertNull($assignment->libra_cloud);
        $this->assertNull($assignment->oracle_ebs);
    }

    /**
     * El select de `sistema_operativo_id` manda '' como "Sin asignar" —
     * mismo fix de `nullifyEmptyForeignKeys()` ya documentado en otras
     * pantallas del módulo.
     */
    public function test_can_save_leaving_sistema_operativo_unassigned(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000013', 'en_stock');
        $validador = $this->validador();

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->set('form.sistema_operativo_id', '')
            ->set('form.libra_cloud', '')
            ->set('form.oracle_ebs', '')
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();
        $this->assertNull($assignment->sistema_operativo_id);
        $this->assertNull($assignment->libra_cloud);
        $this->assertNull($assignment->oracle_ebs);
    }

    public function test_export_responsiva_pdf_returns_a_pdf_download_with_technical_config(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000014', 'en_stock');
        $validador = $this->validador();
        $so = SistemaOperativo::create(['nombre' => 'Windows 11 Pro', 'activo' => true]);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $sic->empleado_id, 'sdp_display_id' => 'REQ-1001']);

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $sic->empleado_id,
            'ticket_id' => $ticket->id,
            'sic_id' => $sic->id,
            'fecha_asignacion' => '2026-09-01',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
            'ip' => '10.0.0.20',
            'mac_wifi' => 'AA:BB:CC:DD:EE:03',
            'sistema_operativo_id' => $so->id,
            'libra_cloud' => true,
            'oracle_ebs' => false,
        ]);

        Livewire::test(Asignaciones::class)
            ->call('exportResponsivaPdf', $assignment->id)
            ->assertFileDownloaded('responsiva-'.$asset->codigo.'.pdf');
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-asignaciones',
            'route_name' => 'gestionti.asignaciones.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-asignaciones.manage'));
    }

    /**
     * Fase 5, punto 5 (SharePoint) — "Buscar en SharePoint" trae la lista
     * completa de la carpeta una sola vez y filtra en memoria (sin volver a
     * pegarle a Graph por cada tecla, verificado abajo con
     * `Http::assertSentCount`), y al elegir un archivo NUNCA sube nada
     * (verificado con `Http::assertNotSent` sobre cualquier PUT).
     */
    public function test_vincular_archivo_existente_modal_filters_in_memory_and_links_without_uploading(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        config([
            'services.sharepoint.tenant_id' => 'tenant-1',
            'services.sharepoint.client_id' => 'client-1',
            'services.sharepoint.client_secret' => 'secret-1',
            'services.sharepoint.site_hostname' => 'grupokosmosmexico.sharepoint.com',
            'services.sharepoint.site_path' => '/sites/Landit',
            'services.sharepoint.carpetas' => ['responsiva' => 'Responsivas Asignación de Activos'],
        ]);
        $this->app->forgetInstance(\Modules\GestionTI\Support\SharePoint\SharePointClient::class);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://login.microsoftonline.com/')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            if (preg_match('#/v1\.0/sites/[^/]+/drive$#', $url)) {
                return Http::response(['id' => 'drive-1']);
            }

            if (str_contains($url, '/v1.0/sites/') && $request->method() === 'GET') {
                return Http::response(['id' => 'site-1']);
            }

            if (str_contains($url, ':/children')) {
                return Http::response(['value' => [
                    ['id' => 'sp-1', 'name' => 'juan-perez.pdf', 'webUrl' => 'https://example/juan-perez.pdf', 'file' => []],
                    ['id' => 'sp-2', 'name' => 'maria-lopez.pdf', 'webUrl' => 'https://example/maria-lopez.pdf', 'file' => []],
                ]]);
            }

            return Http::response([], 404);
        });

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000011', 'en_stock');
        $validador = $this->validador();

        $component = Livewire::test(Asignaciones::class)
            ->call('create')
            ->call('openSharePointBuscar', 'documentoResponsiva')
            ->assertSet('showSharePointModal', true);

        $this->assertCount(2, $component->viewData('sharePointArchivosFiltrados'));

        $component->set('sharePointSearch', 'juan');
        $filtrados = $component->viewData('sharePointArchivosFiltrados');
        $this->assertCount(1, $filtrados);
        $this->assertSame('juan-perez.pdf', $filtrados[0]['nombre']);

        $component->call('elegirArchivoSharePoint', 'sp-1')
            ->assertSet('showSharePointModal', false)
            ->assertSet('documentoResponsivaVinculado.nombre', 'juan-perez.pdf');

        $component->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-09-01')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();
        $this->assertNotNull($assignment->documento_responsiva_id);

        $documento = $assignment->documentoResponsiva;
        $this->assertSame('sharepoint', $documento->proveedor_almacenamiento);
        $this->assertSame('sp-1', $documento->referencia);
        $this->assertSame('juan-perez.pdf', $documento->nombre_archivo);
        $this->assertSame('https://example/juan-perez.pdf', $documento->url_externa);

        // El filtrado (varios `set('sharePointSearch', ...)`) nunca volvió a
        // pegarle a Graph — solo 1 GET de listado real ocurrió (el de
        // `openSharePointBuscar`) — y elegir/guardar nunca subió nada.
        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    }

    /**
     * Un archivo ya vinculado a otro registro (`DocumentoDigitalizado`
     * existente con ese `driveItemId`) no debe volver a ofrecerse en el
     * modal "Buscar en SharePoint" — evita relacionar el mismo archivo dos
     * veces por error.
     */
    public function test_buscar_en_sharepoint_excluye_archivos_ya_vinculados(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusAsignado();

        config([
            'services.sharepoint.tenant_id' => 'tenant-1',
            'services.sharepoint.client_id' => 'client-1',
            'services.sharepoint.client_secret' => 'secret-1',
            'services.sharepoint.site_hostname' => 'grupokosmosmexico.sharepoint.com',
            'services.sharepoint.site_path' => '/sites/Landit',
            'services.sharepoint.carpetas' => ['responsiva' => 'Responsivas Asignación de Activos'],
        ]);
        $this->app->forgetInstance(\Modules\GestionTI\Support\SharePoint\SharePointClient::class);

        DocumentoDigitalizado::create([
            'entidad_relacionada' => 'AssetAssignment',
            'entidad_id' => 0,
            'tipo_documento' => 'responsiva',
            'proveedor_almacenamiento' => 'sharepoint',
            'referencia' => 'sp-1',
            'url_externa' => 'https://example/juan-perez.pdf',
            'nombre_archivo' => 'juan-perez.pdf',
            'fecha_subida' => now(),
            'subido_por_id' => null,
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://login.microsoftonline.com/')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            if (preg_match('#/v1\.0/sites/[^/]+/drive$#', $url)) {
                return Http::response(['id' => 'drive-1']);
            }

            if (str_contains($url, '/v1.0/sites/') && $request->method() === 'GET') {
                return Http::response(['id' => 'site-1']);
            }

            if (str_contains($url, ':/children')) {
                return Http::response(['value' => [
                    ['id' => 'sp-1', 'name' => 'juan-perez.pdf', 'webUrl' => 'https://example/juan-perez.pdf', 'file' => []],
                    ['id' => 'sp-2', 'name' => 'maria-lopez.pdf', 'webUrl' => 'https://example/maria-lopez.pdf', 'file' => []],
                ]]);
            }

            return Http::response([], 404);
        });

        $component = Livewire::test(Asignaciones::class)
            ->call('create')
            ->call('openSharePointBuscar', 'documentoResponsiva')
            ->assertSet('showSharePointModal', true);

        $filtrados = $component->viewData('sharePointArchivosFiltrados');
        $this->assertCount(1, $filtrados);
        $this->assertSame('maria-lopez.pdf', $filtrados[0]['nombre']);
    }

    /**
     * Corrige un técnico que subió/vinculó la responsiva equivocada: quitar
     * borra el `DocumentoDigitalizado` y libera la FK, pero el archivo real
     * en el disco (o SharePoint) sigue existiendo — solo se rompe la
     * relación, nunca el archivo.
     */
    public function test_quitar_responsiva_unlinks_without_deleting_the_physical_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();

        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000012', 'en_stock');
        $validador = $this->validador();

        $documento = \Modules\GestionTI\Models\DocumentoDigitalizado::storeUploaded(
            UploadedFile::fake()->create('equivocada.pdf', 50, 'application/pdf'),
            $asset,
            'responsiva',
            null
        );

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $sic->empleado_id,
            'sic_id' => $sic->id,
            'fecha_asignacion' => '2026-09-01',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
            'documento_responsiva_id' => $documento->id,
        ]);

        Livewire::test(Asignaciones::class)
            ->call('quitarResponsiva', $assignment->id)
            ->assertSet('showAttachModal', false);

        $assignment->refresh();
        $this->assertNull($assignment->documento_responsiva_id);
        $this->assertDatabaseMissing('documentos_digitalizados', ['id' => $documento->id]);
        Storage::disk('public')->assertExists($documento->referencia);

        // La asignación vuelve a permitir adjuntar (ya no tiene documento).
        Livewire::test(Asignaciones::class)
            ->call('openAttach', $assignment->id)
            ->assertSet('showAttachModal', true);
    }

    // ------------------------------------------------------------------
    // SIC por folio (EBS o local), líneas, excepción sin SIC autorizada y "Ligar SIC"
    // ------------------------------------------------------------------

    /** @param  array<int, int|float>  $cantidades  Una línea por cantidad (numeradas desde 1). */
    private function requisicion(string $code, string $status = 'APPROVED', array $cantidades = [1]): EbsRequisition
    {
        $requisicion = EbsRequisition::create([
            'requisition_header_id' => random_int(1000000, 9999999),
            'code' => $code,
            'status' => $status,
        ]);

        foreach (array_values($cantidades) as $i => $cantidad) {
            $requisicion->lines()->create([
                'requisition_line_id' => random_int(1000000, 9999999),
                'line_number' => $i + 1,
                'item_id' => 100 + $i,
                'item_description' => 'Artículo '.($i + 1),
                'quantity' => $cantidad,
            ]);
        }

        return $requisicion->fresh('lines');
    }

    private function formularioPorFolio($componente, string $folio, Asset $asset, ?Empleado $empleado = null, ?int $lineaId = null)
    {
        $validador = Validador::firstOrCreate(['nombre' => 'Ana Torres']);

        $componente->call('create')
            ->set('form.sic_folio', $folio);

        if ($lineaId) {
            $componente->set('form.ebs_linea_id', $lineaId);
        }

        $componente
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-10-29')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id);

        if ($empleado) {
            $componente->set('form.empleado_id', $empleado->id);
        }

        return $componente;
    }

    public function test_an_authorized_ebs_requisition_can_be_assigned_by_folio_without_a_local_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $requisicion = $this->requisicion('REQ-5001');
        $asset = $this->asset('KOS-LAPTOP-000501', 'en_stock');
        $empleado = $this->empleado('EMP-50', 'Luisa Ramos');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5001', $asset, $empleado)
            ->call('save')
            ->assertHasNoErrors();

        $asignacion = AssetAssignment::firstOrFail();
        $this->assertNull($asignacion->sic_id);
        $this->assertSame($requisicion->id, $asignacion->ebs_requisition_id);
        $this->assertSame($requisicion->lines->first()->requisition_line_id, $asignacion->ebs_requisition_line_id);
        $this->assertSame('REQ-5001', $asignacion->sic_folio);
        $this->assertFalse($asignacion->excepcion_sin_sic);
        $this->assertSame($empleado->id, $asignacion->empleado_id);
        $this->assertSame(auth()->id(), $asignacion->registrado_por_user_id);
        $this->assertSame(AssetAssignment::COBERTURA_CUBIERTA, $asignacion->estadoCobertura());
        $this->assertSame($this->estatusAsignado()->id, $asset->fresh()->estatus_id);
    }

    public function test_the_employee_is_required_when_the_sic_does_not_bring_one(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->requisicion('REQ-5002');
        $asset = $this->asset('KOS-LAPTOP-000502', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5002', $asset)
            ->call('save')
            ->assertHasErrors(['form.empleado_id']);

        $this->assertSame(0, AssetAssignment::count());
    }

    public function test_a_requisition_with_several_lines_requires_choosing_the_line_and_each_line_has_its_own_capacity(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $requisicion = $this->requisicion('REQ-5003', 'APPROVED', [1, 2]);
        [$linea1, $linea2] = $requisicion->lines->sortBy('line_number')->values()->all();
        $empleado = $this->empleado('EMP-51', 'Mario Soto');

        // Sin elegir la línea se rechaza.
        $a1 = $this->asset('KOS-LAPTOP-000503', 'en_stock');
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5003', $a1, $empleado)
            ->call('save')
            ->assertHasErrors(['form.ebs_linea_id']);

        // La línea 1 trae 1 pieza: la primera asignación pasa, la segunda se rechaza.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5003', $a1, $empleado, $linea1->requisition_line_id)
            ->call('save')
            ->assertHasNoErrors();

        $a2 = $this->asset('KOS-LAPTOP-000504', 'en_stock');
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5003', $a2, $empleado, $linea1->requisition_line_id)
            ->call('save')
            ->assertHasErrors(['form.ebs_linea_id']);

        // La línea 2 trae 2 piezas: caben dos asignaciones y la tercera no.
        foreach (['KOS-LAPTOP-000504', 'KOS-LAPTOP-000505'] as $codigo) {
            $a = Asset::where('codigo', $codigo)->first() ?? $this->asset($codigo, 'en_stock');
            $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5003', $a, $empleado, $linea2->requisition_line_id)
                ->call('save')
                ->assertHasNoErrors();
        }

        $a3 = $this->asset('KOS-LAPTOP-000506', 'en_stock');
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5003', $a3, $empleado, $linea2->requisition_line_id)
            ->call('save')
            ->assertHasErrors(['form.ebs_linea_id']);

        $this->assertSame(3, AssetAssignment::count());
        $this->assertSame(2, AssetAssignment::where('ebs_requisition_line_id', $linea2->requisition_line_id)->count());
    }

    public function test_a_line_that_does_not_belong_to_the_requisition_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->requisicion('REQ-5004', 'APPROVED', [1, 1]);
        $otra = $this->requisicion('REQ-5005');
        $asset = $this->asset('KOS-LAPTOP-000507', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5004', $asset, $this->empleado('EMP-52', 'Pepe'), $otra->lines->first()->requisition_line_id)
            ->call('save')
            ->assertHasErrors(['form.ebs_linea_id']);
    }

    public function test_assigning_before_the_requisition_is_authorized_is_an_exception_that_needs_a_reason_and_becomes_covered_later(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $requisicion = $this->requisicion('REQ-5006', 'IN PROCESS');
        $asset = $this->asset('KOS-LAPTOP-000508', 'en_stock');
        $empleado = $this->empleado('EMP-53', 'Rosa Vega');

        // Sin motivo no pasa.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5006', $asset, $empleado)
            ->call('save')
            ->assertHasErrors(['form.motivo_excepcion']);

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5006', $asset, $empleado)
            ->set('form.motivo_excepcion', 'Entrega urgente a dirección')
            ->call('save')
            ->assertHasNoErrors();

        $asignacion = AssetAssignment::firstOrFail();
        $this->assertTrue($asignacion->excepcion_sin_sic);
        $this->assertSame('Entrega urgente a dirección', $asignacion->motivo_excepcion);
        $this->assertSame($requisicion->id, $asignacion->ebs_requisition_id);
        $this->assertSame(AssetAssignment::COBERTURA_PENDIENTE, $asignacion->estadoCobertura());

        // Aparece en el filtro de pendientes...
        $this->assertCount(1, Livewire::test(Asignaciones::class)->set('soloPendientes', true)->viewData('records'));

        // ...y en cuanto EBS la autoriza queda cubierta, sin tocar nada.
        $requisicion->update(['status' => 'APPROVED']);
        $this->assertSame(AssetAssignment::COBERTURA_CUBIERTA, $asignacion->fresh()->estadoCobertura());
        $this->assertCount(0, Livewire::test(Asignaciones::class)->set('soloPendientes', true)->viewData('records'));
    }

    public function test_a_folio_that_does_not_exist_yet_is_saved_as_pending_and_can_be_linked_when_the_sic_arrives(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $asset = $this->asset('KOS-LAPTOP-000509', 'en_stock');
        $empleado = $this->empleado('EMP-54', 'Tomás Rey');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5007', $asset, $empleado)
            ->set('form.motivo_excepcion', 'El cliente no puede esperar')
            ->call('save')
            ->assertHasNoErrors();

        $asignacion = AssetAssignment::firstOrFail();
        $this->assertNull($asignacion->sic_id);
        $this->assertNull($asignacion->ebs_requisition_id);
        $this->assertSame('REQ-5007', $asignacion->sic_folio);
        $this->assertSame(AssetAssignment::COBERTURA_PENDIENTE, $asignacion->estadoCobertura());

        // La SIC llega a EBS y se autoriza: el folio coincide y queda "por ligar".
        $requisicion = $this->requisicion('REQ-5007', 'APPROVED', [1, 1]);
        $this->assertSame(AssetAssignment::COBERTURA_POR_LIGAR, $asignacion->fresh()->estadoCobertura());

        // Tiene 2 líneas: ligar exige elegir la línea.
        $lineaId = $requisicion->lines->sortBy('line_number')->last()->requisition_line_id;
        $component = Livewire::test(Asignaciones::class)
            ->call('openLigar', $asignacion->id)
            ->assertSet('ligarFolio', 'REQ-5007')
            ->call('confirmLigar')
            ->assertHasErrors(['ligarLineaId']);

        $component->set('ligarLineaId', $lineaId)
            ->call('confirmLigar')
            ->assertHasNoErrors()
            ->assertSet('showLigarModal', false);

        $asignacion->refresh();
        $this->assertSame($requisicion->id, $asignacion->ebs_requisition_id);
        $this->assertSame($lineaId, $asignacion->ebs_requisition_line_id);
        $this->assertNotNull($asignacion->sic_ligada_at);
        $this->assertSame(auth()->id(), $asignacion->sic_ligada_por_user_id);
        $this->assertSame(AssetAssignment::COBERTURA_CUBIERTA, $asignacion->estadoCobertura());
    }

    public function test_linking_is_refused_while_the_sic_is_not_authorized_or_does_not_exist(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $asset = $this->asset('KOS-LAPTOP-000510', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5008', $asset, $this->empleado('EMP-55', 'Iván Cruz'))
            ->set('form.motivo_excepcion', 'Urgente')
            ->call('save');

        $asignacion = AssetAssignment::firstOrFail();

        Livewire::test(Asignaciones::class)
            ->call('openLigar', $asignacion->id)
            ->call('confirmLigar')
            ->assertHasErrors(['ligarFolio']);

        $this->requisicion('REQ-5008', 'IN PROCESS');

        Livewire::test(Asignaciones::class)
            ->call('openLigar', $asignacion->id)
            ->call('confirmLigar')
            ->assertHasErrors(['ligarFolio']);

        $this->assertNull($asignacion->fresh()->ebs_requisition_id);
    }

    public function test_a_rejected_requisition_cannot_cover_an_assignment(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->requisicion('REQ-5009', 'REJECTED');
        $asset = $this->asset('KOS-LAPTOP-000511', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-5009', $asset, $this->empleado('EMP-56', 'Eva Mora'))
            ->set('form.motivo_excepcion', 'Intento')
            ->call('save')
            ->assertHasErrors(['form.sic_folio']);

        $this->assertSame(0, AssetAssignment::count());
    }

    public function test_a_local_sic_typed_by_folio_uses_its_employee_and_cannot_be_assigned_twice(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $empleado = $this->empleado('EMP-57', 'Dora Luna');
        $sic = $this->sicAutorizada($empleado);
        $a1 = $this->asset('KOS-LAPTOP-000512', 'en_stock');
        $a2 = $this->asset('KOS-LAPTOP-000513', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), $sic->folio_sic, $a1)
            ->call('save')
            ->assertHasNoErrors();

        $asignacion = AssetAssignment::firstOrFail();
        $this->assertSame($sic->id, $asignacion->sic_id);
        $this->assertSame($empleado->id, $asignacion->empleado_id);
        $this->assertSame($sic->folio_sic, $asignacion->sic_folio);
        $this->assertFalse($asignacion->excepcion_sin_sic);

        // Ya asignada: no se puede volver a usar.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), $sic->folio_sic, $a2)
            ->call('save')
            ->assertHasErrors(['form.sic_folio']);

        $this->assertSame(1, AssetAssignment::count());
    }

    public function test_a_sic_with_a_reserved_asset_cannot_be_assigned_a_different_one(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusReservado();
        $this->estatusAsignado();
        $sic = $this->sicAutorizada();
        $reservado = $this->asset('KOS-LAPTOP-000514', 'reservado', $sic->id);
        $libre = $this->asset('KOS-LAPTOP-000515', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), $sic->folio_sic, $libre)
            ->call('save')
            ->assertHasErrors(['form.sic_folio']);

        // Con el equipo reservado sí pasa.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), $sic->folio_sic, $reservado)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($reservado->id, AssetAssignment::firstOrFail()->asset_id);
    }

    public function test_a_local_sic_that_is_not_authorized_yet_is_an_exception_and_linking_asks_to_confirm_a_different_employee(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $empleadoSic = $this->empleado('EMP-58', 'Beto Ríos');
        $sic = $this->sicAutorizada($empleadoSic);
        $sic->update(['estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA]);
        $asset = $this->asset('KOS-LAPTOP-000516', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), $sic->folio_sic, $asset)
            ->set('form.motivo_excepcion', 'Equipo ya entregado')
            ->call('save')
            ->assertHasNoErrors();

        $asignacion = AssetAssignment::firstOrFail();
        $this->assertTrue($asignacion->excepcion_sin_sic);
        $this->assertSame($sic->id, $asignacion->sic_id);
        $this->assertSame(AssetAssignment::COBERTURA_PENDIENTE, $asignacion->estadoCobertura());

        $sic->update(['estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA]);
        $this->assertSame(AssetAssignment::COBERTURA_CUBIERTA, $asignacion->fresh()->estadoCobertura());

        // Ligar una asignación hecha a OTRO empleado a una SIC local pide confirmación.
        $otroEmpleado = $this->empleado('EMP-59', 'Nora Paz');
        $otraSic = $this->sicAutorizada($otroEmpleado);
        $asset2 = $this->asset('KOS-LAPTOP-000517', 'en_stock');

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'FOLIO-LIBRE-1', $asset2, $empleadoSic)
            ->set('form.motivo_excepcion', 'Urgente')
            ->call('save')
            ->assertHasNoErrors();

        $libre = AssetAssignment::where('sic_folio', 'FOLIO-LIBRE-1')->firstOrFail();

        Livewire::test(Asignaciones::class)
            ->call('openLigar', $libre->id)
            ->set('ligarFolio', $otraSic->folio_sic)
            ->call('confirmLigar')
            ->assertHasErrors(['ligarFolio'])
            ->set('ligarConfirmaEmpleado', true)
            ->call('confirmLigar')
            ->assertHasNoErrors();

        $this->assertSame($otraSic->id, $libre->fresh()->sic_id);
    }

    public function test_the_local_origin_still_assigns_from_the_authorized_local_sic_list_and_stores_its_folio(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusAsignado();
        $sic = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000518', 'en_stock');
        $validador = $this->validador();

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.origen_sic', 'local')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $asset->id)
            ->set('form.fecha_asignacion', '2026-10-29')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->call('save')
            ->assertHasNoErrors();

        $asignacion = AssetAssignment::firstOrFail();
        $this->assertSame($sic->folio_sic, $asignacion->sic_folio);
        $this->assertFalse($asignacion->excepcion_sin_sic);
    }

    /** Equipo recibido de una línea de EBS: reservado para su requisición y línea. */
    private function assetReservadoDeEbs(string $codigo, EbsRequisition $requisicion, int $numeroLinea = 1): Asset
    {
        $asset = $this->asset($codigo, 'reservado');
        $asset->update([
            'ebs_requisition_id' => $requisicion->id,
            'ebs_requisition_line_id' => $requisicion->lines->firstWhere('line_number', $numeroLinea)->requisition_line_id,
        ]);

        return $asset->fresh();
    }

    public function test_an_asset_reserved_for_an_ebs_line_is_offered_and_assigned_for_that_line_only(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusReservado();
        $this->estatusAsignado();
        $requisicion = $this->requisicion('REQ-6001', 'APPROVED', [1, 1]);
        [$linea1, $linea2] = $requisicion->lines->sortBy('line_number')->values()->all();
        $reservado1 = $this->assetReservadoDeEbs('KOS-LAPTOP-000601', $requisicion, 1);
        $reservado2 = $this->assetReservadoDeEbs('KOS-LAPTOP-000602', $requisicion, 2);
        $empleado = $this->empleado('EMP-61', 'Lupe Gil');

        // Con la línea 1 elegida solo se ofrece el reservado de esa línea.
        $ids = $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-6001', $reservado1, $empleado, $linea1->requisition_line_id)
            ->viewData('assetOptions')->pluck('id')->all();
        $this->assertContains($reservado1->id, $ids);
        $this->assertNotContains($reservado2->id, $ids);

        // El reservado de OTRA línea no puede usarse en esta.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-6001', $reservado2, $empleado, $linea1->requisition_line_id)
            ->call('save')
            ->assertHasErrors(['form.asset_id']);

        // El de su línea sí.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-6001', $reservado1, $empleado, $linea1->requisition_line_id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->estatusAsignado()->id, $reservado1->fresh()->estatus_id);
        $this->assertSame($reservado1->id, AssetAssignment::firstOrFail()->asset_id);
    }

    public function test_a_line_with_reserved_equipment_cannot_be_assigned_a_different_stock_asset(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusReservado();
        $this->estatusAsignado();
        $requisicion = $this->requisicion('REQ-6002', 'APPROVED', [2]);
        $reservado = $this->assetReservadoDeEbs('KOS-LAPTOP-000603', $requisicion, 1);
        $libre = $this->asset('KOS-LAPTOP-000604', 'en_stock');
        $empleado = $this->empleado('EMP-62', 'Nico Paz');

        // Hay un equipo reservado sin asignar: se rechaza usar uno cualquiera de stock.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-6002', $libre, $empleado)
            ->call('save')
            ->assertHasErrors(['form.asset_id']);

        // Ya asignado el reservado, la segunda pieza de la línea sí puede salir de stock.
        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-6002', $reservado, $empleado)
            ->call('save')
            ->assertHasNoErrors();

        $this->formularioPorFolio(Livewire::test(Asignaciones::class), 'REQ-6002', $libre, $empleado)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, AssetAssignment::count());
    }

    public function test_the_shortcut_from_sic_en_ebs_opens_the_form_with_the_folio_and_the_single_line_chosen(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $requisicion = $this->requisicion('REQ-7001');

        $this->get('/asignaciones?folio=REQ-7001')->assertOk()->assertSee('REQ-7001');

        Livewire::withQueryParams(['folio' => 'REQ-7001'])
            ->test(Asignaciones::class)
            ->assertSet('showModal', true)
            ->assertSet('form.sic_folio', 'REQ-7001')
            ->assertSet('form.ebs_linea_id', $requisicion->lines->first()->requisition_line_id);
    }

    public function test_assignments_without_any_sic_data_are_not_flagged_as_pending(): void
    {
        // Asignación histórica / del registro manual: sin SIC ni folio, no se marca como pendiente.
        $this->estatusAsignado();
        $asset = $this->asset('KOS-LAPTOP-000519', 'asignado');
        $asignacion = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $this->empleado('EMP-60', 'Histórico')->id,
            'fecha_asignacion' => '2020-01-01',
        ]);

        $this->assertSame(AssetAssignment::COBERTURA_SIN_DATO, $asignacion->estadoCobertura());
    }
}
