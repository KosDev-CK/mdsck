<?php

namespace Modules\GestionTI\Tests\Feature\Inventarios;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Inventarios\Stock;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\AssetSicReservationLog;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\StockMovement;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Stock',
            'slug' => 'gestionti-stock',
            'route_name' => 'gestionti.stock.index',
            'permission_name' => 'screens.gestionti-stock.manage',
            'icon' => 'cube',
            'order' => 32,
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

    private function tipoEquipo(string $nombre = 'Laptop'): TipoEquipo
    {
        return TipoEquipo::firstOrCreate(['nombre' => $nombre]);
    }

    private function empleado(string $numero = 'EMP-1', string $nombre = 'Juan Pérez', ?int $empresaId = null): Empleado
    {
        return Empleado::create([
            'numero_empleado' => $numero,
            'nombre' => $nombre,
            'empresa_id' => $empresaId,
        ]);
    }

    private function asset(string $codigo, string $estatusCodigo, array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'codigo' => $codigo,
            'tipo_equipo_id' => $this->tipoEquipo()->id,
            'numero_serie' => 'SN-'.$codigo,
            'origen_tipo' => 'compra',
            'estatus_id' => $this->estatus($estatusCodigo, ucfirst($estatusCodigo))->id,
        ], $overrides));
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

        $this->actingAs($user)->get('/stock')->assertForbidden();
    }

    public function test_filter_by_ubicacion(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $ubicacionA = Ubicacion::create(['nombre' => 'Guanajuato']);
        $ubicacionB = Ubicacion::create(['nombre' => 'CDMX']);

        $assetA = $this->asset('KOS-LAPTOP-000001', 'en_stock', ['ubicacion_actual_id' => $ubicacionA->id]);
        $assetB = $this->asset('KOS-LAPTOP-000002', 'en_stock', ['ubicacion_actual_id' => $ubicacionB->id]);

        $records = Livewire::test(Stock::class)->set('ubicacionFilter', $ubicacionA->id)->viewData('records');

        $this->assertTrue($records->contains($assetA));
        $this->assertFalse($records->contains($assetB));
    }

    public function test_filter_by_tipo_equipo(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $laptop = $this->tipoEquipo('Laptop');
        $monitor = $this->tipoEquipo('Monitor');

        $assetLaptop = $this->asset('KOS-LAPTOP-000003', 'en_stock', ['tipo_equipo_id' => $laptop->id]);
        $assetMonitor = $this->asset('KOS-MONITOR-000001', 'en_stock', ['tipo_equipo_id' => $monitor->id]);

        $records = Livewire::test(Stock::class)->set('tipoEquipoFilter', $monitor->id)->viewData('records');

        $this->assertTrue($records->contains($assetMonitor));
        $this->assertFalse($records->contains($assetLaptop));
    }

    public function test_filter_by_marca(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $marcaA = Marca::create(['nombre' => 'Dell']);
        $marcaB = Marca::create(['nombre' => 'HP']);

        $assetA = $this->asset('KOS-LAPTOP-000004', 'en_stock', ['marca_id' => $marcaA->id]);
        $assetB = $this->asset('KOS-LAPTOP-000005', 'en_stock', ['marca_id' => $marcaB->id]);

        $records = Livewire::test(Stock::class)->set('marcaFilter', $marcaA->id)->viewData('records');

        $this->assertTrue($records->contains($assetA));
        $this->assertFalse($records->contains($assetB));
    }

    public function test_filter_by_estatus(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');
        $reservado = $this->estatus('reservado', 'Reservado');

        $assetEnStock = $this->asset('KOS-LAPTOP-000006', 'en_stock');
        $assetReservado = $this->asset('KOS-LAPTOP-000007', 'reservado');

        $records = Livewire::test(Stock::class)->set('estatusFilter', $reservado->id)->viewData('records');

        $this->assertTrue($records->contains($assetReservado));
        $this->assertFalse($records->contains($assetEnStock));
    }

    public function test_search_by_codigo_numero_serie_and_service_tag(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $asset = $this->asset('KOS-LAPTOP-000008', 'en_stock', [
            'numero_serie' => 'SN-XYZ',
            'service_tag' => 'TAG-123',
        ]);
        $other = $this->asset('KOS-LAPTOP-000009', 'en_stock');

        $byCodigo = Livewire::test(Stock::class)->set('search', 'KOS-LAPTOP-000008')->viewData('records');
        $this->assertTrue($byCodigo->contains($asset));
        $this->assertFalse($byCodigo->contains($other));

        $bySerie = Livewire::test(Stock::class)->set('search', 'SN-XYZ')->viewData('records');
        $this->assertTrue($bySerie->contains($asset));

        $byTag = Livewire::test(Stock::class)->set('search', 'TAG-123')->viewData('records');
        $this->assertTrue($byTag->contains($asset));
    }

    /**
     * Link "Ver ficha" agregado junto al código (Fase 3 etapa 10, Ficha de
     * Activo/Trazabilidad) — apunta al detalle del Activo correcto.
     */
    public function test_ver_ficha_link_points_to_the_correct_asset(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $asset = $this->asset('KOS-LAPTOP-000099', 'en_stock');

        Livewire::test(Stock::class)
            ->assertSee(route('gestionti.ficha-activo.show', $asset->id), false);
    }

    /**
     * `Asset` no tiene `empresa_id` propio — el filtro solo tiene efecto
     * sobre activos `asignado` vía su asignación activa. Verificado además
     * que una asignación ya devuelta (`fecha_devolucion` no nulo) no cuenta.
     */
    public function test_empresa_filter_only_matches_assets_via_active_assignment(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');
        $this->estatus('asignado', 'Asignado');

        $empresaA = Empresa::create(['razon_social' => 'Empresa A', 'nombre_comercial' => 'Empresa A']);
        $empresaB = Empresa::create(['razon_social' => 'Empresa B', 'nombre_comercial' => 'Empresa B']);

        $empleadoA = $this->empleado('EMP-A', 'Empleado A', $empresaA->id);
        $empleadoB = $this->empleado('EMP-B', 'Empleado B', $empresaB->id);

        $assetAssignedToA = $this->asset('KOS-LAPTOP-000010', 'asignado');
        AssetAssignment::create([
            'asset_id' => $assetAssignedToA->id,
            'empleado_id' => $empleadoA->id,
            'fecha_asignacion' => '2026-09-01',
        ]);

        $assetAssignedToB = $this->asset('KOS-LAPTOP-000011', 'asignado');
        AssetAssignment::create([
            'asset_id' => $assetAssignedToB->id,
            'empleado_id' => $empleadoB->id,
            'fecha_asignacion' => '2026-09-01',
        ]);

        // Ya devuelto: no debe contar como asignación activa a Empresa A.
        $assetReturned = $this->asset('KOS-LAPTOP-000012', 'en_stock');
        AssetAssignment::create([
            'asset_id' => $assetReturned->id,
            'empleado_id' => $empleadoA->id,
            'fecha_asignacion' => '2026-08-01',
            'fecha_devolucion' => '2026-08-15',
        ]);

        $records = Livewire::test(Stock::class)->set('empresaFilter', $empresaA->id)->viewData('records');

        $this->assertTrue($records->contains($assetAssignedToA));
        $this->assertFalse($records->contains($assetAssignedToB));
        $this->assertFalse($records->contains($assetReturned));
    }

    public function test_stock_minimo_alert_appears_when_libre_stock_is_below_minimum(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $ubicacion = Ubicacion::create(['nombre' => 'CDMX']);
        $tipo = $this->tipoEquipo('Laptop');

        StockMinimo::create([
            'tipo_equipo_id' => $tipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 3,
            'activo' => true,
        ]);

        $this->asset('KOS-LAPTOP-000013', 'en_stock', [
            'tipo_equipo_id' => $tipo->id,
            'ubicacion_actual_id' => $ubicacion->id,
        ]);

        $alertas = Livewire::test(Stock::class)->viewData('alertasMinimos');

        $this->assertCount(1, $alertas);
        $this->assertSame(1, $alertas->first()['stock_actual']);
        $this->assertSame(3, $alertas->first()['cantidad_minima']);
    }

    public function test_stock_minimo_alert_does_not_appear_when_at_or_above_minimum(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $ubicacion = Ubicacion::create(['nombre' => 'CDMX']);
        $tipo = $this->tipoEquipo('Laptop');

        StockMinimo::create([
            'tipo_equipo_id' => $tipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 2,
            'activo' => true,
        ]);

        $this->asset('KOS-LAPTOP-000014', 'en_stock', ['tipo_equipo_id' => $tipo->id, 'ubicacion_actual_id' => $ubicacion->id]);
        $this->asset('KOS-LAPTOP-000015', 'en_stock', ['tipo_equipo_id' => $tipo->id, 'ubicacion_actual_id' => $ubicacion->id]);

        $alertas = Livewire::test(Stock::class)->viewData('alertasMinimos');

        $this->assertCount(0, $alertas);
    }

    /**
     * Spec 7.11, línea 127: stock libre = solo `en_stock`, sin contar
     * `reservado` ni `asignado`. Aquí hay 2 activos físicamente en la
     * ubicación/tipo, pero ninguno `en_stock` — la alerta debe seguir
     * disparándose con `stock_actual = 0`.
     */
    public function test_stock_minimo_alert_does_not_count_reservado_or_asignado_as_libre(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');
        $this->estatus('reservado', 'Reservado');
        $this->estatus('asignado', 'Asignado');

        $ubicacion = Ubicacion::create(['nombre' => 'CDMX']);
        $tipo = $this->tipoEquipo('Laptop');

        StockMinimo::create([
            'tipo_equipo_id' => $tipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 1,
            'activo' => true,
        ]);

        $this->asset('KOS-LAPTOP-000016', 'reservado', ['tipo_equipo_id' => $tipo->id, 'ubicacion_actual_id' => $ubicacion->id]);
        $this->asset('KOS-LAPTOP-000017', 'asignado', ['tipo_equipo_id' => $tipo->id, 'ubicacion_actual_id' => $ubicacion->id]);

        $alertas = Livewire::test(Stock::class)->viewData('alertasMinimos');

        $this->assertCount(1, $alertas);
        $this->assertSame(0, $alertas->first()['stock_actual']);
    }

    public function test_reassign_sic_only_opens_for_a_reservado_asset(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $asset = $this->asset('KOS-LAPTOP-000018', 'en_stock');

        Livewire::test(Stock::class)
            ->call('openReassign', $asset->id)
            ->assertSet('showReassignModal', false);
    }

    public function test_reassign_sic_creates_log_and_updates_asset(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('reservado', 'Reservado');

        $sicOriginal = $this->sicAutorizada();
        $sicNueva = $this->sicAutorizada($this->empleado('EMP-2', 'Empleado Dos'));
        $asset = $this->asset('KOS-LAPTOP-000019', 'reservado', ['sic_reservada_id' => $sicOriginal->id]);

        Livewire::test(Stock::class)
            ->call('openReassign', $asset->id)
            ->assertSet('showReassignModal', true)
            ->set('reassignForm.sic_nueva_id', $sicNueva->id)
            ->set('reassignForm.motivo', 'Cambio de destinatario')
            ->call('confirmReassign')
            ->assertHasNoErrors();

        $log = AssetSicReservationLog::firstOrFail();
        $this->assertSame($asset->id, $log->asset_id);
        $this->assertSame($sicOriginal->id, $log->sic_anterior_id);
        $this->assertSame($sicNueva->id, $log->sic_nueva_id);
        $this->assertSame('Cambio de destinatario', $log->motivo);

        $this->assertSame($sicNueva->id, $asset->fresh()->sic_reservada_id);
    }

    public function test_reassign_sic_direct_call_does_nothing_if_asset_is_not_reservado(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $sicNueva = $this->sicAutorizada();
        $asset = $this->asset('KOS-LAPTOP-000020', 'en_stock');

        Livewire::test(Stock::class)
            ->set('reassigningAssetId', $asset->id)
            ->set('reassignForm.sic_nueva_id', $sicNueva->id)
            ->set('reassignForm.motivo', 'Intento directo')
            ->call('confirmReassign');

        $this->assertDatabaseCount('asset_sic_reservation_logs', 0);
        $this->assertNull($asset->fresh()->sic_reservada_id);
    }

    public function test_traslado_available_on_en_stock_and_reservado_but_not_asignado(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');
        $this->estatus('reservado', 'Reservado');
        $this->estatus('asignado', 'Asignado');

        $enStock = $this->asset('KOS-LAPTOP-000021', 'en_stock');
        $reservado = $this->asset('KOS-LAPTOP-000022', 'reservado');
        $asignado = $this->asset('KOS-LAPTOP-000023', 'asignado');

        Livewire::test(Stock::class)->call('openTraslado', $enStock->id)->assertSet('showTrasladoModal', true);
        Livewire::test(Stock::class)->call('openTraslado', $reservado->id)->assertSet('showTrasladoModal', true);
        Livewire::test(Stock::class)->call('openTraslado', $asignado->id)->assertSet('showTrasladoModal', false);
    }

    public function test_traslado_creates_stock_movement_and_updates_ubicacion(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        $origen = Ubicacion::create(['nombre' => 'Guanajuato']);
        $destino = Ubicacion::create(['nombre' => 'CDMX']);

        $asset = $this->asset('KOS-LAPTOP-000024', 'en_stock', ['ubicacion_actual_id' => $origen->id]);

        Livewire::test(Stock::class)
            ->call('openTraslado', $asset->id)
            ->set('trasladoForm.ubicacion_destino_id', $destino->id)
            ->set('trasladoForm.comentarios', 'Reubicación por consolidación')
            ->call('confirmTraslado')
            ->assertHasNoErrors();

        $movimiento = StockMovement::firstOrFail();
        $this->assertSame($asset->id, $movimiento->asset_id);
        $this->assertSame(StockMovement::TIPO_TRASLADO, $movimiento->tipo);
        $this->assertSame($origen->id, $movimiento->ubicacion_origen_id);
        $this->assertSame($destino->id, $movimiento->ubicacion_destino_id);

        $this->assertSame($destino->id, $asset->fresh()->ubicacion_actual_id);
    }

    public function test_traslado_direct_call_does_nothing_if_asset_is_asignado(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('asignado', 'Asignado');

        $origen = Ubicacion::create(['nombre' => 'Guanajuato']);
        $destino = Ubicacion::create(['nombre' => 'CDMX']);

        $asset = $this->asset('KOS-LAPTOP-000025', 'asignado', ['ubicacion_actual_id' => $origen->id]);

        Livewire::test(Stock::class)
            ->set('trasladandoAssetId', $asset->id)
            ->set('trasladoForm.ubicacion_destino_id', $destino->id)
            ->call('confirmTraslado');

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame($origen->id, $asset->fresh()->ubicacion_actual_id);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-stock',
            'route_name' => 'gestionti.stock.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-stock.manage'));
    }
}
