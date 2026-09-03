<?php

namespace Modules\GestionTI\Tests\Feature\Inventarios\FichaActivo;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Inventarios\FichaActivo\Show;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\AssetSicReservationLog;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\RecepcionLinea;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\StockMovement;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Ficha de Activo',
            'slug' => 'gestionti-ficha-activo',
            'route_name' => 'gestionti.ficha-activo.index',
            'permission_name' => 'screens.gestionti-ficha-activo.manage',
            'icon' => 'clock',
            'order' => 35,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function tipoEquipo(): TipoEquipo
    {
        return TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
    }

    private function estatus(string $codigo = 'en_stock', string $nombre = 'En stock'): EstatusActivo
    {
        return EstatusActivo::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);
    }

    private function asset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'codigo' => 'KOS-LAPTOP-'.random_int(100000, 999999),
            'tipo_equipo_id' => $this->tipoEquipo()->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $this->estatus()->id,
        ], $overrides));
    }

    private function empleado(string $numero = 'EMP-1', string $nombre = 'Juan Pérez'): Empleado
    {
        return Empleado::create(['numero_empleado' => $numero, 'nombre' => $nombre]);
    }

    private function titulos(Asset $asset): array
    {
        return array_column(
            Livewire::test(Show::class, ['asset' => $asset])->viewData('timeline'),
            'titulo'
        );
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $asset = $this->asset();
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get("/ficha-activo/{$asset->id}")->assertForbidden();
    }

    public function test_alta_manual_origin_shows_only_the_motivo_event(): void
    {
        $this->actingAs($this->actingUser());

        $validador = Validador::create(['nombre' => 'Ana Torres']);

        $asset = $this->asset([
            'origen_tipo' => 'alta_manual',
            'motivo_alta_manual' => 'Equipo de respaldo comprado por caja chica',
            'dado_de_alta_por_id' => $validador->id,
            'fecha_alta_stock' => '2026-07-01',
        ]);

        $timeline = Livewire::test(Show::class, ['asset' => $asset])->viewData('timeline');

        $this->assertCount(1, $timeline);
        $this->assertSame('Alta manual', $timeline[0]['titulo']);
        $this->assertStringContainsString('Equipo de respaldo comprado por caja chica', $timeline[0]['detalle']);
        $this->assertStringContainsString('Ana Torres', $timeline[0]['detalle']);
        $this->assertSame('01/07/2026', $timeline[0]['fecha']->format('d/m/Y'));
    }

    public function test_compra_origin_with_full_chain_shows_the_4_events_in_order_without_duplicating_solicitud_proveedor(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = $this->empleado();
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);

        $empresa = Empresa::create(['razon_social' => 'Kosmos S.A. de C.V.', 'nombre_comercial' => 'Kosmos']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        $sic = SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $this->tipoEquipo()->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-02',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'SIC-1',
        ]);

        $vendor = Proveedor::create(['razon_social' => 'Distribuidora Kosmos', 'nombre_comercial' => 'Distribuidora Kosmos']);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-1',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-03',
            'ticket_id' => $ticket->id,
            'sic_id' => $sic->id,
            'tipo_solicitud' => 'regular',
            'estatus' => SolicitudProveedor::ESTATUS_RECIBIDA,
        ]);

        $linea = $solicitud->lineas()->create([
            'descripcion_libre' => 'Laptop especial',
            'cantidad_solicitada' => 1,
            'cantidad_recibida' => 1,
            'precio_unitario_cotizado' => 20000,
            'es_activo_inventariable' => true,
        ]);

        $validador = Validador::create(['nombre' => 'Recepcionista']);
        $ubicacion = Ubicacion::create(['nombre' => 'Almacén Central']);

        $recepcion = Recepcion::create([
            'solicitud_proveedor_id' => $solicitud->id,
            'folio_remision' => 'REM-1',
            'fecha_recepcion' => '2026-08-04',
            'recibido_por_id' => $validador->id,
            'ubicacion_id' => $ubicacion->id,
        ]);

        $recepcionLinea = RecepcionLinea::create([
            'recepcion_id' => $recepcion->id,
            'solicitud_proveedor_linea_id' => $linea->id,
            'cantidad_recibida' => 1,
        ]);

        $asset = $this->asset([
            'origen_tipo' => 'compra',
            'recepcion_linea_id' => $recepcionLinea->id,
            'vendor_id' => $vendor->id,
        ]);

        $timeline = Livewire::test(Show::class, ['asset' => $asset])->viewData('timeline');
        $titulos = array_column($timeline, 'titulo');

        $this->assertSame(
            ['Ticket', 'Solicitud de SIC', 'Solicitud a Proveedor', 'Recepción de Proveedor'],
            $titulos
        );

        // Nunca se duplica el evento "Solicitud a Proveedor" aunque exista un
        // 2do camino de relación (`RecepcionLinea::solicitudProveedorLinea()`)
        // hacia el mismo registro — ver la nota en Show.php.
        $this->assertSame(1, count(array_filter($titulos, fn ($t) => $t === 'Solicitud a Proveedor')));
    }

    public function test_assignment_events_appear_with_and_without_devolucion(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset();
        $empleado = $this->empleado('EMP-2', 'María López');
        $validador = Validador::create(['nombre' => 'Responsable Entrega']);

        $assignment = AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'fecha_asignacion' => '2026-08-10',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
        ]);

        $titulos = $this->titulos($asset->fresh());

        $this->assertContains('Asignado a María López', $titulos);
        $this->assertNotContains('Devuelto por María López', $titulos);

        $assignment->update(['fecha_devolucion' => '2026-08-20']);

        $titulosConDevolucion = $this->titulos($asset->fresh());

        $this->assertContains('Asignado a María López', $titulosConDevolucion);
        $this->assertContains('Devuelto por María López', $titulosConDevolucion);
    }

    public function test_reasignacion_sic_event_appears_when_present(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset(['estatus_id' => $this->estatus('reservado', 'Reservado')->id]);
        $empleado = $this->empleado();
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);
        $empresa = Empresa::create(['razon_social' => 'Kosmos', 'nombre_comercial' => 'Kosmos']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-2', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        $sicNueva = SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $this->tipoEquipo()->id,
            'motivo' => 'Reasignación',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-05',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'SIC-NUEVA',
        ]);

        AssetSicReservationLog::create([
            'asset_id' => $asset->id,
            'sic_anterior_id' => null,
            'sic_nueva_id' => $sicNueva->id,
            'motivo' => 'Cambio de destinatario',
        ]);

        $titulos = $this->titulos($asset->fresh());

        $this->assertContains('Reasignación de SIC', $titulos);
    }

    public function test_traslado_event_appears_when_present(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset();
        $origen = Ubicacion::create(['nombre' => 'CDMX']);
        $destino = Ubicacion::create(['nombre' => 'Guanajuato']);

        StockMovement::create([
            'asset_id' => $asset->id,
            'tipo' => StockMovement::TIPO_TRASLADO,
            'fecha' => '2026-08-15',
            'ubicacion_origen_id' => $origen->id,
            'ubicacion_destino_id' => $destino->id,
        ]);

        $titulos = $this->titulos($asset->fresh());

        $this->assertContains('Trasladado de CDMX a Guanajuato', $titulos);
    }

    public function test_mantenimiento_event_appears_when_present(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset();

        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => '2026-09-01',
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        $titulos = $this->titulos($asset->fresh());

        $this->assertContains('Mantenimiento preventivo', $titulos);
    }

    public function test_factura_event_appears_when_present(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset();
        $vendor = Proveedor::create(['razon_social' => 'Proveedor Uno', 'nombre_comercial' => 'Proveedor Uno']);

        $invoice = Invoice::create([
            'folio_factura' => 'FAC-1',
            'vendor_id' => $vendor->id,
            'fecha_recepcion' => '2026-08-25',
            'monto_total' => 15000,
            'moneda' => 'MXN',
            'estatus' => Invoice::ESTATUS_RECIBIDA,
        ]);

        $asset->update(['invoice_id' => $invoice->id]);

        $titulos = $this->titulos($asset->fresh());

        $this->assertContains('Facturado', $titulos);
    }

    /**
     * Un Asset sin ninguna relación adicional (solo su origen de migración
     * histórica, sin nota) no debe romper el render — todas las fuentes de
     * la línea de tiempo son opcionales/defensivas con `?->`.
     */
    public function test_clean_asset_without_any_extra_relations_does_not_break_the_render(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset();

        $component = Livewire::test(Show::class, ['asset' => $asset]);

        $titulos = array_column($component->viewData('timeline'), 'titulo');

        $this->assertSame(['Alta por migración histórica'], $titulos);
        $this->assertNotContains('Asignado a', $titulos);
        $this->assertNotContains('Trasladado', $titulos);
        $this->assertNotContains('Facturado', $titulos);
    }

    public function test_export_trazabilidad_pdf_generates_without_exception_for_an_asset_with_full_history(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = $this->empleado();
        $validador = Validador::create(['nombre' => 'Responsable Entrega']);
        $asset = $this->asset();

        AssetAssignment::create([
            'asset_id' => $asset->id,
            'empleado_id' => $empleado->id,
            'fecha_asignacion' => '2026-08-10',
            'estado_equipo_entrega' => 'nuevo',
            'responsable_entrega_id' => $validador->id,
        ]);

        $origen = Ubicacion::create(['nombre' => 'CDMX']);
        $destino = Ubicacion::create(['nombre' => 'Guanajuato']);

        StockMovement::create([
            'asset_id' => $asset->id,
            'tipo' => StockMovement::TIPO_TRASLADO,
            'fecha' => '2026-08-15',
            'ubicacion_origen_id' => $origen->id,
            'ubicacion_destino_id' => $destino->id,
        ]);

        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => '2026-09-01',
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        Livewire::test(Show::class, ['asset' => $asset->fresh()])
            ->call('exportTrazabilidadPdf')
            ->assertFileDownloaded('trazabilidad-'.$asset->codigo.'.pdf');
    }

    public function test_export_trazabilidad_pdf_generates_without_exception_for_a_clean_asset(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset();

        Livewire::test(Show::class, ['asset' => $asset])
            ->call('exportTrazabilidadPdf')
            ->assertFileDownloaded('trazabilidad-'.$asset->codigo.'.pdf');
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-ficha-activo',
            'route_name' => 'gestionti.ficha-activo.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-ficha-activo.manage'));
    }
}
