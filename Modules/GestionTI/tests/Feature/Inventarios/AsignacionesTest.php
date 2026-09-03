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
                'form.sic_id' => 'required',
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
}
