<?php

namespace Modules\GestionTI\Tests\Feature;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\BusquedaGlobal;
use Modules\GestionTI\Livewire\Catalogos\Empleados as CatalogosEmpleados;
use Modules\GestionTI\Livewire\Compras\Facturas;
use Modules\GestionTI\Livewire\Compras\Recepciones;
use Modules\GestionTI\Livewire\Compras\SolicitudesProveedor;
use Modules\GestionTI\Livewire\MesaServicio\SolicitudesSic;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;
use Tests\TestCase;

class BusquedaGlobalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Metadatos reales (mismos que GestionTIDatabaseSeeder) de cada Screen
     * que este test puede necesitar crear — crear el Screen es lo que da de
     * alta el Permission correspondiente (Screen::booted() lo hace
     * automáticamente), sin necesidad de un Role intermedio: se asigna el
     * permiso directo al usuario de prueba.
     */
    private const SCREENS = [
        'gestionti-busqueda-global' => ['group' => 'General', 'name' => 'Búsqueda Global', 'route' => 'gestionti.busqueda-global.index', 'icon' => 'magnifying-glass', 'order' => 0],
        'gestionti-ficha-activo' => ['group' => 'Inventarios', 'name' => 'Ficha de Activo', 'route' => 'gestionti.ficha-activo.index', 'icon' => 'clock', 'order' => 35],
        'gestionti-catalogos-empleados' => ['group' => 'Catálogos', 'name' => 'Empleados', 'route' => 'gestionti.catalogos.empleados', 'icon' => 'users', 'order' => 11],
        'gestionti-solicitudes-sic' => ['group' => 'Mesa de Servicio', 'name' => 'Solicitud de SIC', 'route' => 'gestionti.solicitudes-sic.index', 'icon' => 'document-text', 'order' => 2],
        'gestionti-solicitudes-proveedor' => ['group' => 'Compras', 'name' => 'Solicitud a Proveedores', 'route' => 'gestionti.solicitudes-proveedor.index', 'icon' => 'shopping-cart', 'order' => 21],
        'gestionti-recepciones' => ['group' => 'Compras', 'name' => 'Recepción de Proveedor', 'route' => 'gestionti.recepciones.index', 'icon' => 'inbox-arrow-down', 'order' => 22],
        'gestionti-facturas' => ['group' => 'Compras', 'name' => 'Facturación', 'route' => 'gestionti.facturas.index', 'icon' => 'document-currency-dollar', 'order' => 23],
    ];

    /**
     * Crea un usuario activo con permiso de "Búsqueda Global" siempre, más
     * el permiso de cada slug adicional pedido (asignado directo al
     * usuario, sin Role intermedio).
     */
    private function userWithPermissions(array $extraSlugs = []): User
    {
        foreach (array_unique(['gestionti-busqueda-global', ...$extraSlugs]) as $slug) {
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
            ->push('gestionti-busqueda-global')
            ->unique()
            ->map(fn ($slug) => "screens.{$slug}.manage")
            ->all());

        return $user;
    }

    private function makeAsset(array $overrides = []): Asset
    {
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
        $estatus = EstatusActivo::firstOrCreate(['codigo' => 'en_stock'], ['nombre' => 'En stock']);

        return Asset::create(array_merge([
            'codigo' => 'KOS-LAPTOP-'.str_pad((string) (Asset::count() + 1), 6, '0', STR_PAD_LEFT),
            'tipo_equipo_id' => $tipoEquipo->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $estatus->id,
        ], $overrides));
    }

    private function makeSolicitudSic(string $folioSic): SolicitudSicBorrador
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
            'motivo' => 'Equipo nuevo para ingreso',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'folio_sic' => $folioSic,
        ]);
    }

    private function proveedor(): Proveedor
    {
        return Proveedor::create([
            'razon_social' => 'Distribuidora de Prueba S.A. de C.V.',
            'nombre_comercial' => 'Distribuidora de Prueba',
        ]);
    }

    private function makeSolicitudProveedor(string $folio): SolicitudProveedor
    {
        return SolicitudProveedor::create([
            'folio' => $folio,
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
    }

    private function makeRecepcion(string $folioRemision): Recepcion
    {
        $validador = Validador::create(['nombre' => 'Validador de Prueba']);
        $ubicacion = Ubicacion::create(['nombre' => 'Almacén Central']);
        $solicitud = $this->makeSolicitudProveedor('SP-BASE-'.uniqid());

        return Recepcion::create([
            'solicitud_proveedor_id' => $solicitud->id,
            'folio_remision' => $folioRemision,
            'fecha_recepcion' => '2026-08-05',
            'recibido_por_id' => $validador->id,
            'ubicacion_id' => $ubicacion->id,
        ]);
    }

    private function makeInvoice(string $folioFactura): Invoice
    {
        return Invoice::create([
            'folio_factura' => $folioFactura,
            'vendor_id' => $this->proveedor()->id,
            'fecha_recepcion' => '2026-08-10',
            'monto_total' => 15000,
        ]);
    }

    public function test_query_shorter_than_two_characters_does_not_search(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-ficha-activo']));

        $this->makeAsset(['numero_serie' => 'SN-XYZ-0001']);

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'S')
            ->assertSee('Escribe al menos 2 caracteres')
            ->assertDontSee('SN-XYZ-0001');
    }

    public function test_empty_query_shows_initial_state(): void
    {
        $this->actingAs($this->userWithPermissions());

        Livewire::test(BusquedaGlobal::class)
            ->assertSee('Escribe al menos 2 caracteres');
    }

    public function test_asset_search_only_visible_with_ficha_activo_permission(): void
    {
        $asset = $this->makeAsset(['numero_serie' => 'SN-COND0R-9']);

        // Con permiso: aparece con el link correcto a Ficha de Activo.
        $withPermission = $this->userWithPermissions(['gestionti-ficha-activo']);
        $this->actingAs($withPermission);

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'COND0R')
            ->assertSee($asset->codigo)
            ->assertSee(route('gestionti.ficha-activo.show', $asset), false);

        // Sin permiso: la categoría "Activos" no aparece en absoluto, ni el
        // código del activo ni el link — no solo se oculta visualmente, la
        // query ni siquiera corre (gating real, ver BusquedaGlobal::buscar()).
        $withoutPermission = $this->userWithPermissions();
        $this->actingAs($withoutPermission);

        // No se agrega assertDontSee('Activos') aparte: la modal de ayuda
        // ("?", ver AyudaCatalog::contenido('busqueda-global')) menciona
        // "Activos" como categoría buscable de forma genérica para
        // cualquiera que pueda abrir la pantalla, sin importar su permiso
        // sobre Ficha de Activo — no es una fuga de datos, es documentación.
        // assertDontSee($asset->codigo) + assertSee('Sin resultados') ya
        // prueban que la categoría real de resultados no se calculó ni se
        // renderizó (mismo criterio ya aplicado en DashboardTest).
        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'COND0R')
            ->assertDontSee($asset->codigo)
            ->assertSee('Sin resultados');
    }

    public function test_search_by_numero_empleado_returns_correct_result_and_link(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-catalogos-empleados']));

        $empleado = Empleado::create(['numero_empleado' => 'EMP-7788', 'nombre' => 'Laura Jiménez']);

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'EMP-7788')
            ->assertSee('Laura Jiménez')
            ->assertSee(route('gestionti.catalogos.empleados', ['search' => 'EMP-7788']), false);
    }

    public function test_search_by_folio_sic_returns_correct_result_and_link(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-solicitudes-sic']));

        $sic = $this->makeSolicitudSic('SIC-2026-0099');

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'SIC-2026-0099')
            ->assertSee('SIC-2026-0099')
            ->assertSee(route('gestionti.solicitudes-sic.index', ['search' => $sic->folio_sic]), false);
    }

    public function test_search_by_folio_solicitud_proveedor_returns_correct_result_and_link(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-solicitudes-proveedor']));

        $solicitud = $this->makeSolicitudProveedor('SP-2026-0055');

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'SP-2026-0055')
            ->assertSee('SP-2026-0055')
            ->assertSee(route('gestionti.solicitudes-proveedor.index', ['search' => $solicitud->folio]), false);
    }

    public function test_search_by_folio_remision_returns_correct_result_and_link(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-recepciones']));

        $recepcion = $this->makeRecepcion('REM-2026-0033');

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'REM-2026-0033')
            ->assertSee('REM-2026-0033')
            ->assertSee(route('gestionti.recepciones.index', ['search' => $recepcion->folio_remision]), false);
    }

    public function test_search_by_folio_factura_returns_correct_result_and_link(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-facturas']));

        $invoice = $this->makeInvoice('FAC-2026-0011');

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'FAC-2026-0011')
            ->assertSee('FAC-2026-0011')
            ->assertSee(route('gestionti.facturas.index', ['search' => $invoice->folio_factura]), false);
    }

    public function test_more_than_five_matches_are_limited_to_five_and_show_real_count(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-catalogos-empleados']));

        foreach (range(1, 7) as $i) {
            Empleado::create([
                'numero_empleado' => "EMP-BUSCA-{$i}",
                'nombre' => "Empleado Buscable {$i}",
            ]);
        }

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'Buscable')
            ->assertSee('Mostrando 5 de 7');
    }

    public function test_no_results_message_shows_the_searched_term(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-catalogos-empleados']));

        Livewire::test(BusquedaGlobal::class)
            ->set('query', 'NoExisteNadaAsi')
            ->assertSee('Sin resultados para')
            ->assertSee('NoExisteNadaAsi');
    }

    /**
     * Los 5 componentes destino del deep-link (?search=XXX) ya tenían una
     * propiedad `public string $search` que filtra correctamente — esta
     * entrega solo le agregó el atributo `#[Url(as: 'search')]` para que la
     * query string prellene la propiedad al montar. Mecanismo de prueba:
     * `Livewire::withQueryParams(...)->test(...)` (API real de Livewire 3
     * para simular la query string de la request antes de montar el
     * componente — no hay precedente de esto en el resto del repo, se
     * confirmó contra `LivewireManager::withQueryParams()`).
     */
    public function test_empleados_screen_prefills_search_from_query_string(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-catalogos-empleados']));

        Livewire::withQueryParams(['search' => 'EMP-7788'])
            ->test(CatalogosEmpleados::class)
            ->assertSet('search', 'EMP-7788');
    }

    public function test_solicitudes_sic_screen_prefills_search_from_query_string(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-solicitudes-sic']));

        Livewire::withQueryParams(['search' => 'SIC-2026-0099'])
            ->test(SolicitudesSic::class)
            ->assertSet('search', 'SIC-2026-0099');
    }

    public function test_solicitudes_proveedor_screen_prefills_search_from_query_string(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-solicitudes-proveedor']));

        Livewire::withQueryParams(['search' => 'SP-2026-0055'])
            ->test(SolicitudesProveedor::class)
            ->assertSet('search', 'SP-2026-0055');
    }

    public function test_recepciones_screen_prefills_search_from_query_string(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-recepciones']));

        Livewire::withQueryParams(['search' => 'REM-2026-0033'])
            ->test(Recepciones::class)
            ->assertSet('search', 'REM-2026-0033');
    }

    public function test_facturas_screen_prefills_search_from_query_string(): void
    {
        $this->actingAs($this->userWithPermissions(['gestionti-facturas']));

        Livewire::withQueryParams(['search' => 'FAC-2026-0011'])
            ->test(Facturas::class)
            ->assertSet('search', 'FAC-2026-0011');
    }
}
