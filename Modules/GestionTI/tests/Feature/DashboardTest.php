<?php

namespace Modules\GestionTI\Tests\Feature;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Dashboard;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\ProyectoPresupuestoAutorizacion;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Tests\TestCase;

/**
 * Ver docs/gestionti-progreso.md (Fase 4, etapa 6) para el diseño completo
 * del Dashboard. Mismo criterio de gating por permiso real (no solo visual)
 * ya cubierto en BusquedaGlobalTest — se verifica aquí que cada
 * sección/tarjeta no calcula su dato sin el permiso de la pantalla que
 * representa (a través de `viewData(...)`, no solo `assertSee`).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const SCREENS = [
        'gestionti-dashboard' => ['group' => 'General', 'name' => 'Dashboard', 'route' => 'gestionti.dashboard.index', 'icon' => 'chart-bar', 'order' => -1],
        'gestionti-stock' => ['group' => 'Inventarios', 'name' => 'Stock', 'route' => 'gestionti.stock.index', 'icon' => 'cube', 'order' => 32],
        'gestionti-solicitudes-sic' => ['group' => 'Mesa de Servicio', 'name' => 'Solicitud de SIC', 'route' => 'gestionti.solicitudes-sic.index', 'icon' => 'document-text', 'order' => 2],
        'gestionti-solicitudes-proveedor' => ['group' => 'Compras', 'name' => 'Solicitud a Proveedores', 'route' => 'gestionti.solicitudes-proveedor.index', 'icon' => 'shopping-cart', 'order' => 21],
        'gestionti-facturas' => ['group' => 'Compras', 'name' => 'Facturación', 'route' => 'gestionti.facturas.index', 'icon' => 'document-currency-dollar', 'order' => 23],
        'gestionti-mantenimientos' => ['group' => 'Inventarios', 'name' => 'Mantenimiento', 'route' => 'gestionti.mantenimientos.index', 'icon' => 'wrench-screwdriver', 'order' => 34],
        'gestionti-presupuestos-proyecto' => ['group' => 'Presupuesto de Proyectos', 'name' => 'Presupuesto por Proyecto', 'route' => 'gestionti.presupuestos-proyecto.index', 'icon' => 'banknotes', 'order' => 1],
    ];

    /**
     * Crea un usuario activo con permiso de "Dashboard" siempre (necesario
     * para pasar la ruta), más el permiso de cada slug adicional pedido
     * (asignado directo al usuario, sin Role intermedio) — mismo patrón que
     * `BusquedaGlobalTest::userWithPermissions()`.
     */
    private function userWithPermissions(array $extraSlugs = []): User
    {
        foreach (array_unique(['gestionti-dashboard', ...$extraSlugs]) as $slug) {
            $meta = self::SCREENS[$slug];

            Screen::firstOrCreate(['slug' => $slug], [
                'module' => 'GestionTI',
                'group_label' => $meta['group'],
                'name' => $meta['name'],
                'route_name' => $meta['route'],
                'permission_name' => "screens.{$slug}.manage",
                'icon' => $meta['icon'],
                'order' => $meta['order'],
            ]);
        }

        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo(collect($extraSlugs)
            ->push('gestionti-dashboard')
            ->unique()
            ->map(fn ($slug) => "screens.{$slug}.manage")
            ->all());

        return $user;
    }

    private function makeAsset(array $overrides = [], string $estatusCodigo = 'en_stock', ?TipoEquipo $tipo = null): Asset
    {
        $tipo ??= TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
        $estatus = EstatusActivo::firstOrCreate(['codigo' => $estatusCodigo], ['nombre' => ucfirst(str_replace('_', ' ', $estatusCodigo))]);

        return Asset::create(array_merge([
            'codigo' => 'KOS-LAPTOP-'.str_pad((string) (Asset::count() + 1), 6, '0', STR_PAD_LEFT),
            'tipo_equipo_id' => $tipo->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $estatus->id,
        ], $overrides));
    }

    private function makeSolicitudSic(string $estatus): SolicitudSicBorrador
    {
        $empleado = Empleado::create(['numero_empleado' => 'EMP-SIC-'.uniqid(), 'nombre' => 'Solicitante de Prueba']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id, 'sdp_display_id' => 'SDP-'.uniqid()]);
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
        $empresa = Empresa::create(['razon_social' => 'Empresa de Prueba '.uniqid(), 'nombre_comercial' => 'Empresa de Prueba']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-'.uniqid(), 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        return SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $tipoEquipo->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => $estatus,
        ]);
    }

    private function proveedor(): Proveedor
    {
        return Proveedor::create([
            'razon_social' => 'Distribuidora de Prueba '.uniqid().' S.A. de C.V.',
            'nombre_comercial' => 'Distribuidora de Prueba',
        ]);
    }

    private function makeSolicitudProveedor(string $estatus): SolicitudProveedor
    {
        return SolicitudProveedor::create([
            'folio' => 'SP-'.uniqid(),
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
            'estatus' => $estatus,
        ]);
    }

    private function makeInvoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'folio_factura' => 'FAC-'.uniqid(),
            'vendor_id' => $this->proveedor()->id,
            'fecha_recepcion' => '2026-08-10',
            'monto_total' => 15000,
        ], $overrides));
    }

    private function makeMantenimientoProximo(): Mantenimiento
    {
        return Mantenimiento::create([
            'asset_id' => $this->makeAsset(['numero_serie' => 'MANT-GATE-'.uniqid()])->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(2)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);
    }

    private function proyecto(array $overrides = []): ProyectoPresupuesto
    {
        $empresa = Empresa::create(['razon_social' => 'Kosmos S.A. de C.V. '.uniqid(), 'nombre_comercial' => 'Kosmos']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-'.uniqid(), 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $area = Area::create(['nombre' => 'Operaciones '.uniqid()]);
        $pm = Empleado::create(['numero_empleado' => 'EMP-PM-'.uniqid(), 'nombre' => 'PM de Prueba']);

        return ProyectoPresupuesto::create(array_merge([
            'nombre_proyecto' => 'Proyecto de Prueba',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => $centroCosto->id,
            'direccion_centro' => 'Av. Siempre Viva 123',
            'area_operativa_solicitante_id' => $area->id,
            'pm_responsable_id' => $pm->id,
            'fecha_solicitud' => '2026-09-01',
            'fecha_limite_captura' => '2026-09-15',
        ], $overrides));
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/gestionti-dashboard')->assertForbidden();
    }

    public function test_sections_are_gated_by_their_own_permission(): void
    {
        // Datos mínimos de cada métrica, para confirmar que existen en BD
        // pero que el usuario limitado nunca los ve.
        $this->makeAsset(['numero_serie' => 'SN-GATE-1']);
        $this->makeSolicitudSic(SolicitudSicBorrador::ESTATUS_CAPTURADO);
        $this->makeSolicitudProveedor(SolicitudProveedor::ESTATUS_SOLICITADA);
        $this->makeInvoice(['estatus' => Invoice::ESTATUS_RECIBIDA, 'diferencia_a_revisar' => true]);
        $this->makeMantenimientoProximo();

        $fullUser = $this->userWithPermissions([
            'gestionti-stock',
            'gestionti-solicitudes-sic',
            'gestionti-solicitudes-proveedor',
            'gestionti-facturas',
            'gestionti-mantenimientos',
            'gestionti-presupuestos-proyecto',
        ]);

        $component = Livewire::actingAs($fullUser)->test(Dashboard::class);

        $this->assertNotNull($component->viewData('activosPorEstatus'));
        $this->assertNotNull($component->viewData('stockPorTipo'));
        $this->assertNotNull($component->viewData('stockBajoMinimo'));
        $this->assertNotNull($component->viewData('sicsEnCaptura'));
        $this->assertNotNull($component->viewData('solicitudesProveedorPendientes'));
        $this->assertNotNull($component->viewData('facturasPendientes'));
        $this->assertNotNull($component->viewData('facturasDiferencia'));
        $this->assertNotNull($component->viewData('mantenimientosProximos'));

        $limitedUser = $this->userWithPermissions();

        $limitedComponent = Livewire::actingAs($limitedUser)->test(Dashboard::class);

        $this->assertNull($limitedComponent->viewData('activosPorEstatus'));
        $this->assertNull($limitedComponent->viewData('stockPorTipo'));
        $this->assertNull($limitedComponent->viewData('stockBajoMinimo'));
        $this->assertNull($limitedComponent->viewData('sicsEnCaptura'));
        $this->assertNull($limitedComponent->viewData('solicitudesProveedorPendientes'));
        $this->assertNull($limitedComponent->viewData('facturasPendientes'));
        $this->assertNull($limitedComponent->viewData('facturasDiferencia'));
        $this->assertNull($limitedComponent->viewData('mantenimientosProximos'));

        $limitedComponent
            ->assertDontSee('SICs en captura')
            ->assertDontSee('Solicitudes a proveedor pendientes')
            ->assertDontSee('Facturas pendientes de pago')
            ->assertDontSee('Diferencias a revisar')
            ->assertDontSee('Alertas de stock bajo mínimo')
            ->assertDontSee('Mantenimientos próximos')
            ->assertDontSee('Activos por estatus')
            ->assertDontSee('Stock disponible por tipo');
    }

    public function test_activos_por_estatus_counts_are_correct(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-stock']));

        $this->makeAsset(['numero_serie' => 'A1'], 'en_stock');
        $this->makeAsset(['numero_serie' => 'A2'], 'en_stock');
        $this->makeAsset(['numero_serie' => 'A3'], 'asignado');

        $result = Livewire::test(Dashboard::class)->viewData('activosPorEstatus');

        $byCodigo = $result->keyBy('codigo');
        $this->assertSame(2, (int) $byCodigo['en_stock']->cantidad);
        $this->assertSame(1, (int) $byCodigo['asignado']->cantidad);
    }

    public function test_sics_en_captura_counts_only_capturado_and_sic_creada(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-solicitudes-sic']));

        $this->makeSolicitudSic(SolicitudSicBorrador::ESTATUS_CAPTURADO);
        $this->makeSolicitudSic(SolicitudSicBorrador::ESTATUS_SIC_CREADA);
        $this->makeSolicitudSic(SolicitudSicBorrador::ESTATUS_AUTORIZADA);
        $this->makeSolicitudSic(SolicitudSicBorrador::ESTATUS_RECHAZADA);

        $count = Livewire::test(Dashboard::class)->viewData('sicsEnCaptura');

        $this->assertSame(2, $count);
    }

    public function test_solicitudes_proveedor_pendientes_counts_only_solicitada_and_parcial(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-solicitudes-proveedor']));

        $this->makeSolicitudProveedor(SolicitudProveedor::ESTATUS_SOLICITADA);
        $this->makeSolicitudProveedor(SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA);
        $this->makeSolicitudProveedor(SolicitudProveedor::ESTATUS_RECIBIDA);
        $this->makeSolicitudProveedor(SolicitudProveedor::ESTATUS_CANCELADA);

        $count = Livewire::test(Dashboard::class)->viewData('solicitudesProveedorPendientes');

        $this->assertSame(2, $count);
    }

    public function test_facturas_pendientes_and_diferencia_counts_are_correct(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-facturas']));

        $this->makeInvoice(['estatus' => Invoice::ESTATUS_RECIBIDA, 'diferencia_a_revisar' => false]);
        $this->makeInvoice(['estatus' => Invoice::ESTATUS_REGISTRADA, 'diferencia_a_revisar' => true]);
        $this->makeInvoice(['estatus' => Invoice::ESTATUS_PAGADA, 'diferencia_a_revisar' => true]);

        $component = Livewire::test(Dashboard::class);

        // Pendientes de pago: cualquier estatus != pagada -> 2 de las 3.
        $this->assertSame(2, $component->viewData('facturasPendientes'));
        // Diferencia a revisar: sin filtrar por estatus -> 2 de las 3.
        $this->assertSame(2, $component->viewData('facturasDiferencia'));
    }

    public function test_stock_bajo_minimo_reuses_stock_minimo_en_breach(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-stock']));

        $tipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
        $ubicacion = Ubicacion::create(['nombre' => 'Almacén Central']);

        StockMinimo::create([
            'tipo_equipo_id' => $tipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 5,
            'activo' => true,
        ]);

        // Solo 2 en_stock para ese tipo/ubicación, contra un mínimo de 5 ->
        // en breach.
        $this->makeAsset(['numero_serie' => 'STK-1', 'ubicacion_actual_id' => $ubicacion->id], 'en_stock', $tipo);
        $this->makeAsset(['numero_serie' => 'STK-2', 'ubicacion_actual_id' => $ubicacion->id], 'en_stock', $tipo);

        $breach = Livewire::test(Dashboard::class)->viewData('stockBajoMinimo');

        $this->assertCount(1, $breach);
        $this->assertSame(2, $breach->first()['stock_actual']);
    }

    public function test_mis_pendientes_is_omitted_without_a_linked_empleado(): void
    {
        $user = $this->userWithPermissions(['gestionti-presupuestos-proyecto']);
        $this->actingAs($user);

        // Ningún Empleado con correo == $user->email.
        $component = Livewire::test(Dashboard::class);

        $this->assertNull($component->viewData('empleado'));
        $this->assertNull($component->viewData('misPendientes'));
        $component->assertSee('No tienes un registro de empleado vinculado a tu cuenta');
    }

    public function test_mis_pendientes_shows_correct_counts_when_empleado_is_linked(): void
    {
        $user = $this->userWithPermissions(['gestionti-presupuestos-proyecto']);
        $empleado = Empleado::create(['numero_empleado' => 'EMP-DASH-1', 'nombre' => 'Ana Torres', 'correo' => $user->email]);
        $this->actingAs($user);

        $proyecto = $this->proyecto();

        // 2 pendientes de captura para este empleado, 1 ya capturado (no
        // debe contar).
        $proyecto->articulos()->create([
            'categoria' => 'laptops_desktops',
            'descripcion' => 'Laptop 1',
            'cantidad' => 1,
            'responsable_costo_id' => $empleado->id,
            'estatus_captura' => ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_PENDIENTE,
        ]);
        $proyecto->articulos()->create([
            'categoria' => 'laptops_desktops',
            'descripcion' => 'Laptop 2',
            'cantidad' => 1,
            'responsable_costo_id' => $empleado->id,
            'estatus_captura' => ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_PENDIENTE,
        ]);
        $proyecto->articulos()->create([
            'categoria' => 'laptops_desktops',
            'descripcion' => 'Laptop 3',
            'cantidad' => 1,
            'responsable_costo_id' => $empleado->id,
            'estatus_captura' => ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_CAPTURADO,
            'fecha_captura' => now(),
        ]);

        $component = Livewire::test(Dashboard::class);
        $this->assertSame(2, $component->viewData('misPendientes')['costos_pendientes']);

        // Autorizaciones: nivel 1 pendiente (otro aprobador), nivel 2
        // pendiente asignado a $empleado — mismo escenario que
        // PresupuestoProyectos\ShowTest: nivel 2 NO es accionable mientras
        // nivel 1 siga pendiente.
        $otroAprobador = Empleado::create(['numero_empleado' => 'EMP-AP-OTRO', 'nombre' => 'Otro Aprobador']);
        $proyecto->autorizaciones()->create(['nivel' => 1, 'aprobador_id' => $otroAprobador->id]);
        $proyecto->autorizaciones()->create(['nivel' => 2, 'aprobador_id' => $empleado->id]);

        $component = Livewire::test(Dashboard::class);
        $this->assertSame(0, $component->viewData('misPendientes')['autorizaciones_accionables']);

        // Al aprobar el nivel 1, el nivel 2 (de $empleado) se vuelve
        // accionable.
        $proyecto->autorizaciones()->where('nivel', 1)->update([
            'estatus' => ProyectoPresupuestoAutorizacion::ESTATUS_APROBADO,
        ]);

        $component = Livewire::test(Dashboard::class);
        $this->assertSame(1, $component->viewData('misPendientes')['autorizaciones_accionables']);

        $this->assertSame(
            $user->unreadNotifications()->count(),
            $component->viewData('misPendientes')['notificaciones_sin_leer']
        );
    }

    public function test_mantenimientos_proximos_counts_only_within_the_seven_day_window(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-mantenimientos']));

        $asset = $this->makeAsset(['numero_serie' => 'MANT-1']);

        // Dentro de la ventana [hoy, hoy+7], estatus vigente -> cuentan.
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(3)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(7)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_REPROGRAMADO,
        ]);

        // Fuera de la ventana (más de 7 días) -> no cuenta.
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(8)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        // Ya vencido (antes de hoy) -> no cuenta como "próximo".
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->subDay()->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        // Dentro de la ventana pero ya no en un estatus programable -> no
        // cuenta.
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(2)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_REALIZADO,
        ]);

        $count = Livewire::test(Dashboard::class)->viewData('mantenimientosProximos');

        $this->assertSame(2, $count);
    }
}
