<?php

namespace Modules\GestionTI\Tests\Feature\Compras;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Compras\SolicitudesProveedor;
use Modules\GestionTI\Mail\SolicitudProveedorMail;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\CategoriaArticulo;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\EbsArticulo;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudesProveedorTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Compras',
            'name' => 'Solicitud a Proveedores',
            'slug' => 'gestionti-solicitudes-proveedor',
            'route_name' => 'gestionti.solicitudes-proveedor.index',
            'permission_name' => 'screens.gestionti-solicitudes-proveedor.manage',
            'icon' => 'shopping-cart',
            'order' => 21,
        ]);

        $role = Role::findOrCreate('Compras', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function proveedor(): Proveedor
    {
        return Proveedor::create([
            'razon_social' => 'Distribuidora Kosmos S.A. de C.V.',
            'nombre_comercial' => 'Distribuidora Kosmos',
        ]);
    }

    private function articulo(): ArticuloSolicitud
    {
        return ArticuloSolicitud::create([
            'codigo' => 'ART-100',
            'descripcion' => 'Laptop estándar',
            'unidad_medida' => 'Pieza',
        ]);
    }

    /**
     * Arma un ProyectoPresupuesto (+1 artículo `laptops_desktops`) en el
     * estatus indicado — usado por los tests del select "Artículo de
     * Proyecto de Presupuesto".
     */
    private function proyectoPresupuestoArticulo(string $estatusProyecto = ProyectoPresupuesto::ESTATUS_AUTORIZADO): ProyectoPresupuestoArticulo
    {
        $empresa = Empresa::create(['razon_social' => 'Kosmos', 'nombre_comercial' => 'Kosmos']);
        $empleado = Empleado::create(['numero_empleado' => 'EMP-PM', 'nombre' => 'PM de Prueba']);

        $proyecto = ProyectoPresupuesto::create([
            'nombre_proyecto' => 'Nuevo Centro Guadalajara',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => CentroCosto::create(['codigo' => 'CC-PP', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id])->id,
            'direccion_centro' => 'Av. Siempre Viva 123',
            'area_operativa_solicitante_id' => Area::create(['nombre' => 'Operaciones'])->id,
            'pm_responsable_id' => $empleado->id,
            'fecha_solicitud' => '2026-08-01',
            'fecha_limite_captura' => '2026-08-15',
            'estatus' => $estatusProyecto,
        ]);

        return $proyecto->articulos()->create([
            'categoria_id' => CategoriaArticulo::where('slug', 'laptops_desktops')->value('id'),
            'descripcion' => 'Laptop para gerente de centro',
            'cantidad' => 2,
            'responsable_costo_id' => $empleado->id,
        ]);
    }

    /**
     * Marca una categoría real (sembrada por la migración
     * `2026_09_30_000001_convert_categoria_articulo_to_real_catalog.php`,
     * ver `Modules\GestionTI\Models\CategoriaArticulo`) como "Va a Compras"
     * — reemplaza el viejo `ConfiguracionCategorias::current()->update(...)`.
     */
    private function marcarCategoriaComoCompra(string $slug = 'laptops_desktops'): void
    {
        CategoriaArticulo::where('slug', $slug)->update(['es_compra' => true]);
    }

    /**
     * Claves (`s{sic_id}` / `e{ebs_requisition_id}`) de TODO el pool de
     * SICs/EBS elegibles tal como lo calcula el componente
     * (`SolicitudesProveedor::pool()`, computed — el pool ya no vive en una
     * propiedad pública, ver docs/gestionti-progreso.md).
     */
    private function clavesDelPool($component): array
    {
        return $component->instance()->pool->keys()->all();
    }

    private function filaDelPool($component, string $clave): ?array
    {
        return $component->instance()->pool->get($clave);
    }

    /**
     * Crea `$n` SICs elegibles con fechas distintas y decrecientes: la
     * posición 0 es la más reciente (primera de la primera página) y la
     * última la más antigua (última página), así el reparto en páginas del
     * pool es determinista.
     *
     * @return array<int, SolicitudSicBorrador>
     */
    private function crearSicsEnLote(int $n): array
    {
        $this->marcarCategoriaComoCompra();
        $sics = [];

        for ($i = 0; $i < $n; $i++) {
            $sics[] = $this->crearSicAutorizada('laptops_desktops', [
                'fecha_solicitud' => now()->subDays($i + 1)->format('Y-m-d'),
                'folio_sic' => sprintf('LOTE-%03d', $i),
            ]);
        }

        return $sics;
    }

    private function formularioBasico($component, string $folio, int $vendorId)
    {
        return $component
            ->set('form.folio', $folio)
            ->set('form.vendor_id', $vendorId)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular');
    }

    /**
     * Arma una SIC local autorizada con un Artículo de la categoría dada
     * (siempre inventariable) — mismo criterio del picker de "SICs
     * autorizadas y disponibles" (`SolicitudesProveedor::sicPickerOptions()`):
     * solo aparecen SICs con `articulo_id` resuelto.
     */
    private function crearSicAutorizada(string $categoria = 'laptops_desktops', array $overrides = []): SolicitudSicBorrador
    {
        static $n = 0;
        $n++;

        $empresa = Empresa::create(['razon_social' => "Kosmos SIC $n", 'nombre_comercial' => "Kosmos SIC $n"]);
        $centroCosto = CentroCosto::create(['codigo' => "CC-SIC-$n", 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $empleado = Empleado::create(['numero_empleado' => "EMP-SIC-$n", 'nombre' => "Solicitante SIC $n"]);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);

        $articulo = ArticuloSolicitud::create([
            'codigo' => "ART-SIC-$n",
            'descripcion' => "Laptop SIC $n",
            'unidad_medida' => 'Pieza',
            'categoria_id' => CategoriaArticulo::where('slug', $categoria)->value('id'),
            'es_inventariable' => true,
        ]);

        return SolicitudSicBorrador::create(array_merge([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $tipoEquipo->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => "SIC-$n",
            'articulo_id' => $articulo->id,
        ], $overrides));
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/solicitudes-proveedor')->assertForbidden();
    }

    public function test_can_create_a_solicitud_with_a_catalog_line_and_a_free_text_line(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-TEST-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.articulo_id', $articulo->id)
            ->set('lineasManuales.0.cantidad_solicitada', 3)
            ->set('lineasManuales.0.precio_unitario_cotizado', 150.50)
            ->call('addLinea')
            ->set('lineasManuales.1.descripcion_libre', 'Cable HDMI especial')
            ->set('lineasManuales.1.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_proveedor', [
            'folio' => 'SP-TEST-001',
            'vendor_id' => $vendor->id,
            'tipo_solicitud' => 'regular',
            'estatus' => SolicitudProveedor::ESTATUS_SOLICITADA,
        ]);

        $solicitud = SolicitudProveedor::where('folio', 'SP-TEST-001')->firstOrFail();
        $this->assertCount(2, $solicitud->lineas);

        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 3,
        ]);

        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'descripcion_libre' => 'Cable HDMI especial',
            'cantidad_solicitada' => 1,
            'es_activo_inventariable' => false,
        ]);
    }

    public function test_es_activo_inventariable_is_derived_from_the_selected_articulo(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $inventariable = ArticuloSolicitud::create(['codigo' => 'ART-INV', 'descripcion' => 'Laptop inv', 'unidad_medida' => 'Pieza', 'es_inventariable' => true]);
        $consumible = ArticuloSolicitud::create(['codigo' => 'ART-CON', 'descripcion' => 'Cable', 'unidad_medida' => 'Pieza', 'es_inventariable' => false]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->call('addLinea')
            ->set('form.folio', 'SP-INV-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.articulo_id', $inventariable->id)
            ->set('lineasManuales.1.articulo_id', $consumible->id)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-INV-001')->firstOrFail();
        $this->assertTrue($solicitud->lineas()->where('articulo_id', $inventariable->id)->firstOrFail()->es_activo_inventariable);
        $this->assertFalse($solicitud->lineas()->where('articulo_id', $consumible->id)->firstOrFail()->es_activo_inventariable);
    }

    public function test_selecting_an_ebs_row_prefills_the_cantidad_from_the_ebs_line(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('CANT-EBS-1', 7300);
        $ebsRequisicion->lines()->update(['quantity' => 4]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 'e'.$ebsRequisicion->id)
            ->assertSet('seleccion.e'.$ebsRequisicion->id.'.cantidad_solicitada', 4);
    }

    public function test_folio_is_suggested_when_creating(): void
    {
        $this->actingAs($this->actingUser());

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertStringStartsWith('SP-', $component->get('form.folio'));
    }

    /**
     * Formato SP-YYMMDD-### (año a 2 dígitos) — cambio del rediseño, ver
     * docs/gestionti-progreso.md. Antes era SP-YYYYMMDD-###.
     */
    public function test_suggested_folio_uses_a_2_digit_year(): void
    {
        $this->actingAs($this->actingUser());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01'));

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertSame('SP-261001-001', $component->get('form.folio'));
    }

    /** El conteo de secuencia por día sigue funcionando con el prefijo de 2 dígitos. */
    public function test_suggested_folio_sequence_increments_per_day_with_the_new_format(): void
    {
        $this->actingAs($this->actingUser());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-01'));
        $vendor = $this->proveedor();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-10-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.descripcion_libre', 'Línea 1')
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasNoErrors();

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertSame('SP-261001-002', $component->get('form.folio'));
    }

    public function test_line_with_both_articulo_and_descripcion_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-TEST-002')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.articulo_id', $articulo->id)
            ->set('lineasManuales.0.descripcion_libre', 'Descripción libre también capturada')
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasErrors(['lineasManuales.0.articulo_id']);

        $this->assertDatabaseMissing('solicitudes_proveedor', ['folio' => 'SP-TEST-002']);
    }

    public function test_line_with_neither_articulo_nor_descripcion_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-TEST-003')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->set('lineasManuales.0.observaciones_especificaciones', 'Solo una nota, sin artículo ni descripción')
            ->call('save')
            ->assertHasErrors(['lineasManuales.0.articulo_id']);
    }

    public function test_zero_lines_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-TEST-004')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->call('addLinea')
            ->call('removeLinea', 0)
            ->call('save')
            ->assertHasErrors(['lineas']);
    }

    public function test_sic_and_proyecto_presupuesto_articulo_cannot_both_be_set(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');
        $proyectoArticulo = $this->proyectoPresupuestoArticulo();

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-TEST-005')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 's'.$sic->id)
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id);

        $component->call('save')->assertHasErrors(['origen', 'form.proyecto_presupuesto_articulo_id']);
    }

    public function test_can_edit_an_existing_solicitud_and_its_lines(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-EDIT-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 2,
        ]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('edit', $solicitud->id)
            ->assertSet('form.folio', 'SP-EDIT-001')
            ->set('lineasManuales.0.cantidad_solicitada', 5)
            ->call('addLinea')
            ->set('lineasManuales.1.descripcion_libre', 'Extra')
            ->set('lineasManuales.1.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertCount(2, $solicitud->lineas);
        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 5,
        ]);
    }

    public function test_cancelar_only_available_when_solicitada(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $solicitada = SolicitudProveedor::create([
            'folio' => 'SP-CANCEL-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);

        $recibida = SolicitudProveedor::create([
            'folio' => 'SP-CANCEL-002',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
            'estatus' => SolicitudProveedor::ESTATUS_RECIBIDA,
        ]);

        Livewire::test(SolicitudesProveedor::class)->call('cancelarSolicitud', $solicitada->id);
        $this->assertSame(SolicitudProveedor::ESTATUS_CANCELADA, $solicitada->fresh()->estatus);

        Livewire::test(SolicitudesProveedor::class)->call('cancelarSolicitud', $recibida->id);
        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $recibida->fresh()->estatus);
    }

    public function test_search_and_estatus_filter(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $uno = SolicitudProveedor::create([
            'folio' => 'SP-SEARCH-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);

        $dos = SolicitudProveedor::create([
            'folio' => 'SP-OTRO-002',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-02',
            'tipo_solicitud' => 'regular',
            'estatus' => SolicitudProveedor::ESTATUS_CANCELADA,
        ]);

        $component = Livewire::test(SolicitudesProveedor::class)->set('search', 'SEARCH');
        $folios = $component->viewData('records')->pluck('folio')->all();
        $this->assertContains('SP-SEARCH-001', $folios);
        $this->assertNotContains('SP-OTRO-002', $folios);

        $component = Livewire::test(SolicitudesProveedor::class)->set('estatusFilter', SolicitudProveedor::ESTATUS_CANCELADA);
        $folios = $component->viewData('records')->pluck('folio')->all();
        $this->assertContains('SP-OTRO-002', $folios);
        $this->assertNotContains('SP-SEARCH-001', $folios);
    }

    public function test_can_create_a_solicitud_choosing_a_proyecto_presupuesto_articulo_without_sic(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $proyectoArticulo = $this->proyectoPresupuestoArticulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-PROYECTO-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id)
            ->set('lineasManuales.0.descripcion_libre', 'Laptop para gerente de centro')
            ->set('lineasManuales.0.cantidad_solicitada', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_proveedor', [
            'folio' => 'SP-PROYECTO-001',
            'proyecto_presupuesto_articulo_id' => $proyectoArticulo->id,
        ]);
    }

    public function test_articulo_from_a_non_authorized_proyecto_does_not_appear_in_options(): void
    {
        $this->actingAs($this->actingUser());
        $this->proyectoPresupuestoArticulo(ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertCount(0, $component->viewData('proyectoArticuloOptions'));
    }

    public function test_articulo_already_picked_by_another_solicitud_disappears_from_options(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $proyectoArticulo = $this->proyectoPresupuestoArticulo();

        SolicitudProveedor::create([
            'folio' => 'SP-PROYECTO-002',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
            'proyecto_presupuesto_articulo_id' => $proyectoArticulo->id,
        ]);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertCount(0, $component->viewData('proyectoArticuloOptions'));
    }

    public function test_sic_and_proyecto_presupuesto_articulo_selected_together_via_ui_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $proyectoArticulo = $this->proyectoPresupuestoArticulo();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-PROYECTO-003')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 's'.$sic->id)
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id)
            ->set("seleccion.s{$sic->id}.cantidad_solicitada", 2)
            ->call('save')
            ->assertHasErrors(['origen', 'form.proyecto_presupuesto_articulo_id']);

        $this->assertDatabaseMissing('solicitudes_proveedor', ['folio' => 'SP-PROYECTO-003']);
    }

    public function test_sic_autorizada_with_categoria_marked_as_compra_appears_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertContains('s'.$sic->id, $this->clavesDelPool($component));
    }

    public function test_sic_with_category_not_marked_as_compra_does_not_appear_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('telefonia_fija');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertNotContains('s'.$sic->id, $this->clavesDelPool($component));
    }

    public function test_sic_without_articulo_does_not_appear_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();

        $empresa = Empresa::create(['razon_social' => 'Kosmos Sin Art', 'nombre_comercial' => 'Kosmos Sin Art']);
        $empleado = Empleado::create(['numero_empleado' => 'EMP-SINART', 'nombre' => 'Sin Artículo']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);

        $sic = SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => TipoEquipo::create(['nombre' => 'Laptop Sin Art'])->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => CentroCosto::create(['codigo' => 'CC-SINART', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id])->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'SIC-SINART',
            // sin articulo_id — nunca clasificada, típico de una SIC recién
            // sincronizada de EBS.
        ]);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertNotContains('s'.$sic->id, $this->clavesDelPool($component));
    }

    public function test_sic_already_used_by_another_solicitud_does_not_appear_again(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-SIC-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 's'.$sic->id)
            ->call('save')
            ->assertHasNoErrors();

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->assertNotContains('s'.$sic->id, $this->clavesDelPool($component));
    }

    public function test_toggling_sics_adds_them_to_the_seleccion_and_untoggling_removes_them(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sicUno = $this->crearSicAutorizada('laptops_desktops');
        $sicDos = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $component->assertSet('seleccion', [])
            ->call('toggleSeleccion', 's'.$sicUno->id)
            ->call('toggleSeleccion', 's'.$sicDos->id);

        $this->assertEqualsCanonicalizing(['s'.$sicUno->id, 's'.$sicDos->id], array_keys($component->get('seleccion')));
        $this->assertSame($sicUno->id, $component->get('seleccion.s'.$sicUno->id.'.sic_id'));
        $this->assertSame(1, $component->get('seleccion.s'.$sicUno->id.'.cantidad_solicitada'));
        $this->assertSame($sicUno->articulo_id, $component->get('seleccion.s'.$sicUno->id.'.articulo_id'));

        $component->call('toggleSeleccion', 's'.$sicDos->id);

        $this->assertSame(['s'.$sicUno->id], array_keys($component->get('seleccion')));

        // Desmarcar no saca la fila del pool, solo de la selección.
        $this->assertEqualsCanonicalizing(['s'.$sicUno->id, 's'.$sicDos->id], $this->clavesDelPool($component));
    }

    public function test_toggling_a_key_that_is_not_in_the_pool_is_ignored(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 's999999')
            ->call('toggleSeleccion', 'basura')
            ->assertSet('seleccion', []);
    }

    public function test_saving_with_multiple_selected_sics_creates_one_line_per_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sicUno = $this->crearSicAutorizada('laptops_desktops');
        $sicDos = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-SIC-MULTI')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 's'.$sicUno->id)
            ->call('toggleSeleccion', 's'.$sicDos->id)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-SIC-MULTI')->firstOrFail();
        $this->assertCount(2, $solicitud->lineas);
        $this->assertDatabaseHas('solicitud_proveedor_lineas', ['solicitud_id' => $solicitud->id, 'sic_id' => $sicUno->id]);
        $this->assertDatabaseHas('solicitud_proveedor_lineas', ['solicitud_id' => $solicitud->id, 'sic_id' => $sicDos->id]);
    }

    public function test_manual_line_with_folio_sic_manual_works_without_a_real_sic(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-SIC-MANUAL')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.folio_sic_manual', 'SIC-A-MANO-001')
            ->set('lineasManuales.0.descripcion_libre', 'Laptop capturada a mano, SIC aún sin registro')
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-SIC-MANUAL')->firstOrFail();
        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'folio_sic_manual' => 'SIC-A-MANO-001',
            'sic_id' => null,
            'descripcion_libre' => 'Laptop capturada a mano, SIC aún sin registro',
        ]);
    }

    public function test_editing_keeps_its_own_already_linked_sic_visible_and_selected_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $creacion = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-SIC-EDIT')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 's'.$sic->id)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-SIC-EDIT')->firstOrFail();

        $component = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $this->assertContains('s'.$sic->id, $this->clavesDelPool($component));
        $this->assertArrayHasKey('s'.$sic->id, $component->get('seleccion'));
        $this->assertNotNull($component->get('seleccion.s'.$sic->id.'.id'));
        $this->assertSame([], $component->get('lineasManuales'));
    }

    private function administradorUser(): User
    {
        Role::findOrCreate('Administrador', 'web');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Administrador');

        return $user;
    }

    /**
     * SIC originada en una requisición de EBS (`ebs_requisition_id` no
     * nulo), con una línea real para poder resolver la descripción de EBS
     * de referencia — usada por los tests del punto 7 (`mount()` con
     * `crear_desde_sics`) y punto 8 (descripción EBS por línea).
     */
    private function crearSicDeEbs(string $folio, string $itemDescription = 'LAPTOP ITEM EBS ORIGINAL'): SolicitudSicBorrador
    {
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => $folio]);

        $ebsRequisicion = EbsRequisition::create(['requisition_header_id' => random_int(100000, 999999), 'code' => $folio]);
        $ebsRequisicion->lines()->create([
            'requisition_line_id' => random_int(100000, 999999),
            'line_number' => 1,
            'item_description' => $itemDescription,
            'quantity' => 1,
        ]);

        $sic->update(['ebs_requisition_id' => $ebsRequisicion->id]);

        return $sic->fresh();
    }

    public function test_mount_with_crear_desde_sics_query_param_preloads_the_creation_form(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::withQueryParams(['crear_desde_sics' => (string) $sic->id])->test(SolicitudesProveedor::class);

        $component->assertSet('showForm', true);
        $this->assertSame(['s'.$sic->id], array_keys($component->get('seleccion')));
    }

    public function test_mount_without_crear_desde_sics_opens_normally(): void
    {
        $this->actingAs($this->actingUser());

        $component = Livewire::test(SolicitudesProveedor::class);

        $component->assertSet('showForm', false);
    }

    public function test_line_from_an_ebs_sic_shows_the_original_ebs_description_for_reference(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $sic = $this->crearSicDeEbs('SIC-EBS-REF');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.vendor_id', $vendor->id);

        $fila = $this->filaDelPool($component, 's'.$sic->id);

        $this->assertSame('LAPTOP ITEM EBS ORIGINAL', $fila['ebs_item_description']);
        $component->assertSee('LAPTOP ITEM EBS ORIGINAL');
    }

    public function test_line_from_a_local_sic_has_no_ebs_description(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create');

        $fila = $this->filaDelPool($component, 's'.$sic->id);

        $this->assertNull($fila['ebs_item_description']);
    }

    public function test_observaciones_especificaciones_is_saved_and_retrieved_per_line(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-OBS-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.articulo_id', $articulo->id)
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->set('lineasManuales.0.observaciones_especificaciones', 'Con teclado en español')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'observaciones_especificaciones' => 'Con teclado en español',
        ]);

        $solicitud = SolicitudProveedor::where('folio', 'SP-OBS-001')->firstOrFail();
        $component = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $this->assertSame('Con teclado en español', $component->get('lineasManuales.0.observaciones_especificaciones'));
    }

    public function test_create_writes_creado_por_user_id(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user);
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-CREADOPOR-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.articulo_id', $articulo->id)
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitudes_proveedor', [
            'folio' => 'SP-CREADOPOR-001',
            'creado_por_user_id' => $user->id,
        ]);
    }

    public function test_enviar_a_proveedor_sends_the_mail_and_writes_enviada_at_and_ultimo_envio_at(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $vendor = Proveedor::create([
            'razon_social' => 'Proveedor Con Correo',
            'nombre_comercial' => 'Proveedor Con Correo',
            'contacto_correo' => 'compras@proveedor-test.com',
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-ENVIO-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('enviarAProveedor', $solicitud->id);

        Mail::assertSent(SolicitudProveedorMail::class, function (SolicitudProveedorMail $mail) use ($vendor, $solicitud) {
            return $mail->hasTo($vendor->contacto_correo) && $mail->solicitud->id === $solicitud->id;
        });

        $solicitud->refresh();
        $this->assertNotNull($solicitud->enviada_at);
        $this->assertNotNull($solicitud->ultimo_envio_at);
    }

    public function test_enviar_a_proveedor_without_contacto_correo_sends_nothing_and_shows_an_error(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $vendor = Proveedor::create([
            'razon_social' => 'Proveedor Sin Correo',
            'nombre_comercial' => 'Proveedor Sin Correo',
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-SIN-CORREO-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('enviarAProveedor', $solicitud->id);

        Mail::assertNothingSent();
        $this->assertNull($solicitud->fresh()->enviada_at);
    }

    public function test_reenviar_updates_ultimo_envio_at_without_touching_enviada_at_and_sends_mail_again(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $vendor = Proveedor::create([
            'razon_social' => 'Proveedor Reenvio',
            'nombre_comercial' => 'Proveedor Reenvio',
            'contacto_correo' => 'reenvio@proveedor-test.com',
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REENVIO-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);

        \Illuminate\Support\Carbon::setTestNow('2026-09-01 10:00:00');
        Livewire::test(SolicitudesProveedor::class)->call('enviarAProveedor', $solicitud->id);
        $primerEnvio = $solicitud->fresh()->enviada_at;

        \Illuminate\Support\Carbon::setTestNow('2026-09-02 11:00:00');
        Livewire::test(SolicitudesProveedor::class)->call('enviarAProveedor', $solicitud->id);
        \Illuminate\Support\Carbon::setTestNow();

        $solicitud->refresh();
        $this->assertEquals($primerEnvio, $solicitud->enviada_at);
        $this->assertNotEquals($primerEnvio, $solicitud->ultimo_envio_at);
        Mail::assertSent(SolicitudProveedorMail::class, 2);
    }

    public function test_after_enviada_save_and_cancelar_are_blocked_for_a_normal_user(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-BLOQUEO-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
            'enviada_at' => now(),
        ]);

        // edit() no-opea: el modal no se abre.
        Livewire::test(SolicitudesProveedor::class)
            ->call('edit', $solicitud->id)
            ->assertSet('showForm', false);

        // save() no-opea si se fuerza editingId a mano — ni siquiera llega a
        // validar, así que un folio distinto tampoco se guarda.
        Livewire::test(SolicitudesProveedor::class)
            ->set('editingId', $solicitud->id)
            ->set('form.folio', 'SP-BLOQUEO-CAMBIO')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('save');
        $this->assertSame('SP-BLOQUEO-001', $solicitud->fresh()->folio);

        Livewire::test(SolicitudesProveedor::class)->call('cancelarSolicitud', $solicitud->id);
        $this->assertSame(SolicitudProveedor::ESTATUS_SOLICITADA, $solicitud->fresh()->estatus);
    }

    public function test_after_enviada_an_administrador_can_still_edit(): void
    {
        $this->actingAs($this->administradorUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-ADMIN-EDIT-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
            'enviada_at' => now(),
        ]);
        $solicitud->lineas()->create(['articulo_id' => $articulo->id, 'cantidad_solicitada' => 1]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('edit', $solicitud->id)
            ->assertSet('showForm', true)
            ->set('lineasManuales.0.cantidad_solicitada', 9)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'cantidad_solicitada' => 9,
        ]);

        Livewire::test(SolicitudesProveedor::class)->call('cancelarSolicitud', $solicitud->id);
        $this->assertSame(SolicitudProveedor::ESTATUS_CANCELADA, $solicitud->fresh()->estatus);
    }

    public function test_pdf_route_responds_with_a_pdf_regardless_of_enviada_at(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $sinEnviar = SolicitudProveedor::create([
            'folio' => 'SP-PDF-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);

        $enviada = SolicitudProveedor::create([
            'folio' => 'SP-PDF-002',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
            'enviada_at' => now(),
        ]);

        foreach ([$sinEnviar, $enviada] as $solicitud) {
            $response = $this->get(route('gestionti.solicitudes-proveedor.pdf', $solicitud));
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'));
        }
    }

    public function test_pdf_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $vendor = $this->proveedor();
        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-PDF-003',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);

        $this->actingAs($user)->get(route('gestionti.solicitudes-proveedor.pdf', $solicitud))->assertForbidden();
    }

    public function test_pdf_shows_the_sic_folio_per_line_for_both_origins(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $sicLocal = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => 'SIC-PDF-LOCAL']);
        $ebsDirecto = $this->crearEbsDirectoElegible('SIC-PDF-EBS', 7100);
        $articuloEbs = ArticuloSolicitud::where('codigo', 'ART-EBS-DIRECTO-SIC-PDF-EBS')->firstOrFail();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-PDF-SIC-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create(['sic_id' => $sicLocal->id, 'articulo_id' => $sicLocal->articulo_id, 'cantidad_solicitada' => 1]);
        $solicitud->lineas()->create(['ebs_requisition_id' => $ebsDirecto->id, 'articulo_id' => $articuloEbs->id, 'cantidad_solicitada' => 1]);

        $response = $this->get(route('gestionti.solicitudes-proveedor.pdf', $solicitud));
        $response->assertOk();

        // La ruta real pasa por Dompdf (binario, no inspeccionable como texto
        // sin una librería de parseo que este proyecto no tiene instalada) —
        // se confirma el dato correcto renderizando la MISMA vista Blade que
        // usa el controlador, con las líneas ya cargadas tal como las carga
        // `SolicitudProveedorPdfController::__invoke()`.
        $solicitud->load(['lineas.sic', 'lineas.ebsRequisition']);
        $html = view('gestionti::pdf.solicitud-proveedor', ['solicitud' => $solicitud])->render();
        $this->assertStringContainsString('SIC-PDF-LOCAL', $html);
        $this->assertStringContainsString('SIC-PDF-EBS', $html);
    }

    public function test_enviar_a_proveedor_attaches_the_pdf(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $vendor = Proveedor::create([
            'razon_social' => 'Proveedor Con Adjunto',
            'nombre_comercial' => 'Proveedor Con Adjunto',
            'contacto_correo' => 'adjunto@proveedor-test.com',
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-ADJUNTO-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('enviarAProveedor', $solicitud->id);

        Mail::assertSent(SolicitudProveedorMail::class, function (SolicitudProveedorMail $mail) use ($solicitud) {
            $attachments = $mail->attachments();

            return count($attachments) === 1
                && $attachments[0]->as === "solicitud-proveedor-{$solicitud->folio}.pdf"
                && $attachments[0]->mime === 'application/pdf';
        });
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-solicitudes-proveedor',
            'route_name' => 'gestionti.solicitudes-proveedor.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-solicitudes-proveedor.manage'));
    }

    // --- Tercer origen de línea: EBS directo, sin SIC local -----------------

    /**
     * Requisición de EBS que NUNCA tuvo SIC local, aprobada, con su artículo
     * (vía `EbsArticulo`) mapeado a una categoría "va a Compra" — elegible
     * por el camino directo (ver `EbsRequisition::scopeElegibleDirectoSinSic()`/
     * `articuloMapeadoDeCompra()`).
     */
    private function crearEbsDirectoElegible(string $code, int $itemId, string $itemDescription = 'Item EBS directo'): EbsRequisition
    {
        $this->marcarCategoriaComoCompra();

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-EBS-DIRECTO-'.$code,
            'descripcion' => 'Laptop EBS directo '.$code,
            'unidad_medida' => 'Pieza',
            'categoria_id' => CategoriaArticulo::where('slug', 'laptops_desktops')->value('id'),
            'es_inventariable' => true,
        ]);

        EbsArticulo::create(['ebs_item_id' => $itemId, 'articulo_id' => $articulo->id]);

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => random_int(1000000, 9999999),
            'code' => $code,
            'status' => 'APPROVED',
        ]);

        $ebsRequisicion->lines()->create([
            'requisition_line_id' => random_int(1000000, 9999999),
            'line_number' => 1,
            'item_id' => $itemId,
            'item_description' => $itemDescription,
            'quantity' => 1,
        ]);

        return $ebsRequisicion->fresh();
    }

    public function test_ebs_direct_requisition_without_local_sic_appears_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('PICKER-EBS-1', 7001);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertContains('e'.$ebsRequisicion->id, $this->clavesDelPool($component));
    }

    public function test_ebs_requisition_with_an_existing_local_sic_does_not_appear_in_the_direct_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => 'CON-SIC-LOCAL']);
        $ebsRequisicion = EbsRequisition::create(['requisition_header_id' => random_int(1000000, 9999999), 'code' => 'CON-SIC-LOCAL', 'status' => 'APPROVED']);
        $sic->update(['ebs_requisition_id' => $ebsRequisicion->id]);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertNotContains('e'.$ebsRequisicion->id, $this->clavesDelPool($component));
    }

    public function test_selecting_an_ebs_direct_requisition_creates_a_line_with_the_mapped_articulo(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('PICKER-EBS-2', 7002);
        $articulo = ArticuloSolicitud::where('codigo', 'ART-EBS-DIRECTO-PICKER-EBS-2')->firstOrFail();

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 'e'.$ebsRequisicion->id);

        $linea = $component->get('seleccion.e'.$ebsRequisicion->id);
        $this->assertNotNull($linea);
        $this->assertSame($articulo->id, $linea['articulo_id']);
        $this->assertNull($linea['sic_id']);
        $this->assertSame($ebsRequisicion->id, $linea['ebs_requisition_id']);
    }

    public function test_saving_a_line_from_an_ebs_direct_requisition_persists_ebs_requisition_id_and_null_sic_id(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $ebsRequisicion = $this->crearEbsDirectoElegible('SAVE-EBS-1', 7003);

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-EBS-DIRECTO-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 'e'.$ebsRequisicion->id)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-EBS-DIRECTO-001')->firstOrFail();
        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'ebs_requisition_id' => $ebsRequisicion->id,
            'sic_id' => null,
        ]);
    }

    public function test_line_from_an_ebs_direct_requisition_shows_the_original_ebs_description_for_reference(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('DESC-EBS-1', 7004, 'LAPTOP ITEM EBS DIRECTO ORIGINAL');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create');

        $fila = $this->filaDelPool($component, 'e'.$ebsRequisicion->id);
        $this->assertSame('LAPTOP ITEM EBS DIRECTO ORIGINAL', $fila['ebs_item_description']);
        $component->assertSee('LAPTOP ITEM EBS DIRECTO ORIGINAL');
    }

    public function test_mount_with_crear_desde_ebs_query_param_preloads_the_creation_form(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('MOUNT-EBS-1', 7005);

        $component = Livewire::withQueryParams(['crear_desde_ebs' => (string) $ebsRequisicion->id])->test(SolicitudesProveedor::class);

        $component->assertSet('showForm', true);
        $this->assertSame(['e'.$ebsRequisicion->id], array_keys($component->get('seleccion')));
    }

    /** `crear_desde_sics` y `crear_desde_ebs` pueden venir mezclados a la vez. */
    public function test_mount_with_both_query_params_preloads_a_mixed_selection(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');
        $ebsRequisicion = $this->crearEbsDirectoElegible('MOUNT-MIX-1', 7006);

        $component = Livewire::withQueryParams([
            'crear_desde_sics' => (string) $sic->id,
            'crear_desde_ebs' => (string) $ebsRequisicion->id,
        ])->test(SolicitudesProveedor::class);

        $component->assertSet('showForm', true);
        $this->assertEqualsCanonicalizing(
            ['s'.$sic->id, 'e'.$ebsRequisicion->id],
            array_keys($component->get('seleccion'))
        );
    }

    public function test_editing_keeps_its_own_ebs_direct_requisition_visible_and_selected_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $ebsRequisicion = $this->crearEbsDirectoElegible('EDIT-EBS-1', 7007);

        $creacion = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-EBS-EDIT')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 'e'.$ebsRequisicion->id)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-EBS-EDIT')->firstOrFail();

        $component = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $this->assertContains('e'.$ebsRequisicion->id, $this->clavesDelPool($component));
        $this->assertArrayHasKey('e'.$ebsRequisicion->id, $component->get('seleccion'));
    }

    // --- Campo nuevo por línea: "Lugar de entrega" (catálogo real) ---------

    public function test_a_line_with_a_lugar_de_entrega_is_persisted_and_recovered_on_edit(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();
        $lugar = LugarEntrega::where('nombre', 'Zurich')->firstOrFail();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-LUGAR-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.articulo_id', $articulo->id)
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->set('lineasManuales.0.lugar_entrega_id', $lugar->id)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-LUGAR-001')->firstOrFail();
        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'lugar_entrega_id' => $lugar->id,
        ]);

        $component = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);
        $this->assertSame($lugar->id, $component->get('lineasManuales.0.lugar_entrega_id'));
    }

    public function test_a_line_without_a_lugar_de_entrega_persists_it_as_null(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('addLinea')
            ->set('form.folio', 'SP-LUGAR-002')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineasManuales.0.articulo_id', $articulo->id)
            ->set('lineasManuales.0.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-LUGAR-002')->firstOrFail();
        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'solicitud_id' => $solicitud->id,
            'lugar_entrega_id' => null,
        ]);
    }

    /** La pantalla renderiza sin error con la tabla compacta de líneas, origen SIC y proyecto. */
    public function test_create_form_renders_ok_with_the_compact_lines_table(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->assertOk();
    }

    /** `validateOrigenUnico()` también se dispara con una línea de EBS directo. */
    public function test_origen_unico_validation_also_triggers_with_an_ebs_direct_line(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $ebsRequisicion = $this->crearEbsDirectoElegible('ORIGEN-EBS-1', 7008);
        $proyectoArticulo = $this->proyectoPresupuestoArticulo();

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-ORIGEN-EBS')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->call('toggleSeleccion', 'e'.$ebsRequisicion->id)
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id)
            ->call('save')
            ->assertHasErrors(['origen', 'form.proyecto_presupuesto_articulo_id']);

        $this->assertDatabaseMissing('solicitudes_proveedor', ['folio' => 'SP-ORIGEN-EBS']);
    }

    // --- Pantalla única: pool paginado + selección que se conserva ---------

    /** 20 elegibles = 15 en la primera página y 5 en la segunda; el pool no viaja en una propiedad pública. */
    public function test_the_sics_table_is_paginated_15_per_page(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sics = $this->crearSicsEnLote(20);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $pagina1 = $component->viewData('sics');
        $this->assertSame(20, $pagina1->total());
        $this->assertSame(15, $pagina1->count());
        $this->assertSame(2, $pagina1->lastPage());
        $this->assertSame('s'.$sics[0]->id, $pagina1->first()['clave']);

        $component->call('gotoPage', 2, 'sicsPage');

        $pagina2 = $component->viewData('sics');
        $this->assertSame(2, $pagina2->currentPage());
        $this->assertSame(5, $pagina2->count());
        $this->assertSame('s'.$sics[19]->id, $pagina2->last()['clave']);

        // El pool completo no es una propiedad pública (no se serializa).
        $this->assertFalse(property_exists($component->instance(), 'lineas'));
        $component->assertSee('LOTE-019')->assertDontSee('LOTE-000');
        $component->call('gotoPage', 1, 'sicsPage')->assertSee('LOTE-000')->assertDontSee('LOTE-019');
    }

    /** La selección sobrevive a cambiar de página: marcar en una, ir a otra, marcar, volver. */
    public function test_selection_persists_across_page_changes(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sics = $this->crearSicsEnLote(20);

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 's'.$sics[0]->id)
            ->call('gotoPage', 2, 'sicsPage')
            ->call('toggleSeleccion', 's'.$sics[19]->id)
            ->call('gotoPage', 1, 'sicsPage');

        $this->assertEqualsCanonicalizing(
            ['s'.$sics[0]->id, 's'.$sics[19]->id],
            array_keys($component->get('seleccion'))
        );
        $component->assertSee('2 seleccionadas');
    }

    /** Buscar vuelve a la página 1, filtra por folio y no pierde lo ya seleccionado. */
    public function test_selection_persists_when_searching_and_search_resets_the_page(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sics = $this->crearSicsEnLote(20);

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 's'.$sics[0]->id)
            ->call('gotoPage', 2, 'sicsPage')
            ->set('sicSearch', 'LOTE-017');

        $resultado = $component->viewData('sics');
        $this->assertSame(1, $resultado->currentPage());
        $this->assertSame(['s'.$sics[17]->id], $resultado->pluck('clave')->all());

        $component->call('toggleSeleccion', 's'.$sics[17]->id);
        $component->set('sicSearch', '');

        $this->assertEqualsCanonicalizing(
            ['s'.$sics[0]->id, 's'.$sics[17]->id],
            array_keys($component->get('seleccion'))
        );
    }

    /** El buscador también filtra por la descripción del artículo mapeado y por la descripción EBS. */
    public function test_search_matches_the_mapped_articulo_and_the_ebs_description(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicDeEbs('SIC-BUSCA-1', 'MONITOR CURVO ULTRAWIDE');
        $otra = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => 'SIC-BUSCA-2']);

        $porEbs = Livewire::test(SolicitudesProveedor::class)->call('create')->set('sicSearch', 'ultrawide');
        $this->assertSame(['s'.$sic->id], $porEbs->viewData('sics')->pluck('clave')->all());

        $porArticulo = Livewire::test(SolicitudesProveedor::class)->call('create')->set('sicSearch', $otra->articulo->descripcion);
        $this->assertSame(['s'.$otra->id], $porArticulo->viewData('sics')->pluck('clave')->all());
    }

    public function test_solo_seleccionadas_shows_only_the_selected_rows_across_pages(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sics = $this->crearSicsEnLote(20);

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 's'.$sics[2]->id)
            ->call('toggleSeleccion', 's'.$sics[18]->id)
            ->set('soloSeleccionadas', true);

        $resultado = $component->viewData('sics');
        $this->assertSame(2, $resultado->total());
        $this->assertSame(1, $resultado->lastPage());
        $this->assertEqualsCanonicalizing(['s'.$sics[2]->id, 's'.$sics[18]->id], $resultado->pluck('clave')->all());

        $component->set('soloSeleccionadas', false);
        $this->assertSame(20, $component->viewData('sics')->total());
    }

    public function test_saving_with_a_selection_spread_over_two_pages_persists_all_of_it(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sics = $this->crearSicsEnLote(20);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->formularioBasico($component, 'SP-PAGINAS-001', $vendor->id)
            ->call('toggleSeleccion', 's'.$sics[1]->id)
            ->call('gotoPage', 2, 'sicsPage')
            ->call('toggleSeleccion', 's'.$sics[19]->id)
            ->set('seleccion.s'.$sics[19]->id.'.cantidad_solicitada', 4)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $solicitud = SolicitudProveedor::where('folio', 'SP-PAGINAS-001')->firstOrFail();
        $this->assertCount(2, $solicitud->lineas);
        $this->assertDatabaseHas('solicitud_proveedor_lineas', ['solicitud_id' => $solicitud->id, 'sic_id' => $sics[1]->id, 'cantidad_solicitada' => 1]);
        $this->assertDatabaseHas('solicitud_proveedor_lineas', ['solicitud_id' => $solicitud->id, 'sic_id' => $sics[19]->id, 'cantidad_solicitada' => 4]);
    }

    /** El `sic_id` guardado sale de la clave de la fila, no de lo que el cliente mande dentro del valor. */
    public function test_the_persisted_origin_is_derived_from_the_row_key_not_from_client_data(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sicReal = $this->crearSicAutorizada('laptops_desktops');
        $otra = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->formularioBasico($component, 'SP-CLAVE-001', $vendor->id)
            ->call('toggleSeleccion', 's'.$sicReal->id)
            ->set('seleccion.s'.$sicReal->id.'.sic_id', $otra->id)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-CLAVE-001')->firstOrFail();
        $this->assertSame([$sicReal->id], $solicitud->lineas()->pluck('sic_id')->all());
    }

    public function test_create_in_sic_origin_starts_without_selection_or_manual_lines_and_proyecto_starts_with_one_blank_line(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $component->assertSet('seleccion', [])->assertSet('lineasManuales', []);

        $component->set('origen', 'proyecto');
        $this->assertCount(1, $component->get('lineasManuales'));
        $component->assertSet('seleccion', []);

        $component->set('origen', 'sic');
        $component->assertSet('lineasManuales', [])->assertSet('seleccion', [])->assertSet('form.proyecto_presupuesto_articulo_id', null);
    }

    public function test_switching_to_proyecto_origin_discards_the_selection(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 's'.$sic->id)
            ->set('origen', 'proyecto')
            ->assertSet('seleccion', []);
    }

    public function test_a_blank_manual_line_is_discarded_on_save(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->formularioBasico($component, 'SP-BLANCO-001', $vendor->id)
            ->call('toggleSeleccion', 's'.$sic->id)
            ->call('addLinea')
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-BLANCO-001')->firstOrFail();
        $this->assertCount(1, $solicitud->lineas);
    }

    /** Pool + manuales en la misma solicitud: se guardan y se recuperan separados al editar. */
    public function test_edit_recovers_the_selected_pool_rows_and_the_manual_lines_separately(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');
        $ebs = $this->crearEbsDirectoElegible('MIXTA-EBS-1', 7201);
        $lugar = LugarEntrega::where('nombre', 'CEDA')->firstOrFail();

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->formularioBasico($component, 'SP-MIXTA-001', $vendor->id)
            ->call('toggleSeleccion', 's'.$sic->id)
            ->set('seleccion.s'.$sic->id.'.cantidad_solicitada', 3)
            ->set('seleccion.s'.$sic->id.'.lugar_entrega_id', $lugar->id)
            ->call('toggleSeleccion', 'e'.$ebs->id)
            ->call('addLinea')
            ->set('lineasManuales.0.folio_sic_manual', 'SIC-MANUAL-9')
            ->set('lineasManuales.0.descripcion_libre', 'Cable especial')
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-MIXTA-001')->firstOrFail();
        $this->assertCount(3, $solicitud->lineas);

        $edicion = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $edicion->assertSet('showForm', true);
        $this->assertEqualsCanonicalizing(['s'.$sic->id, 'e'.$ebs->id], array_keys($edicion->get('seleccion')));
        $this->assertSame(3, $edicion->get('seleccion.s'.$sic->id.'.cantidad_solicitada'));
        $this->assertSame($lugar->id, $edicion->get('seleccion.s'.$sic->id.'.lugar_entrega_id'));
        $this->assertNotNull($edicion->get('seleccion.s'.$sic->id.'.id'));
        $this->assertCount(1, $edicion->get('lineasManuales'));
        $this->assertSame('SIC-MANUAL-9', $edicion->get('lineasManuales.0.folio_sic_manual'));

        // Quitar una fila de la selección y guardar borra su línea.
        $edicion->call('toggleSeleccion', 'e'.$ebs->id)->call('save')->assertHasNoErrors();
        $this->assertCount(2, $solicitud->fresh()->lineas);
        $this->assertDatabaseMissing('solicitud_proveedor_lineas', ['solicitud_id' => $solicitud->id, 'ebs_requisition_id' => $ebs->id]);
    }

    /** Red de seguridad: una línea guardada que ya no es elegible se inyecta al pool y no se pierde. */
    public function test_edit_injects_a_saved_pool_line_that_is_no_longer_eligible(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->formularioBasico($component, 'SP-HUERFANA-001', $vendor->id)
            ->call('toggleSeleccion', 's'.$sic->id)
            ->call('save')
            ->assertHasNoErrors();

        // La categoría deja de ir a Compra: la SIC ya no cumple el criterio.
        CategoriaArticulo::where('slug', 'laptops_desktops')->update(['es_compra' => false]);

        $solicitud = SolicitudProveedor::where('folio', 'SP-HUERFANA-001')->firstOrFail();
        $edicion = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $this->assertContains('s'.$sic->id, $this->clavesDelPool($edicion));
        $this->assertArrayHasKey('s'.$sic->id, $edicion->get('seleccion'));
        $this->assertSame(['s'.$sic->id], $edicion->viewData('sics')->pluck('clave')->all());

        $edicion->call('save')->assertHasNoErrors();
        $this->assertCount(1, $solicitud->fresh()->lineas);
    }

    /** `crear_desde_sics` con una SIC que cae en la 2a página: queda seleccionada aunque no esté a la vista. */
    public function test_mount_preselects_rows_that_fall_on_another_page(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sics = $this->crearSicsEnLote(20);

        $component = Livewire::withQueryParams(['crear_desde_sics' => $sics[19]->id.','.$sics[0]->id])->test(SolicitudesProveedor::class);

        $component->assertSet('showForm', true);
        $this->assertEqualsCanonicalizing(
            ['s'.$sics[0]->id, 's'.$sics[19]->id],
            array_keys($component->get('seleccion'))
        );
        $this->assertNotContains('s'.$sics[19]->id, $component->viewData('sics')->pluck('clave')->all());

        $component->call('gotoPage', 2, 'sicsPage');
        $this->assertContains('s'.$sics[19]->id, $component->viewData('sics')->pluck('clave')->all());
    }

    /** Ids de query param que no son elegibles se ignoran en silencio (nunca llegan a la selección). */
    public function test_mount_ignores_preselected_ids_that_are_not_eligible(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();

        $component = Livewire::withQueryParams(['crear_desde_sics' => '999999', 'crear_desde_ebs' => '888888'])->test(SolicitudesProveedor::class);

        $component->assertSet('showForm', true)->assertSet('seleccion', []);
    }

    /** Un error en una fila seleccionada que está en OTRA página se reporta (no queda invisible). */
    public function test_validation_errors_on_a_selected_row_in_another_page_are_reported(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sics = $this->crearSicsEnLote(20);
        $clave = 's'.$sics[19]->id;

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->formularioBasico($component, 'SP-ERR-PAG-001', $vendor->id)
            ->call('toggleSeleccion', $clave)
            ->set("seleccion.$clave.cantidad_solicitada", 0)
            ->call('save')
            ->assertHasErrors(["seleccion.$clave.cantidad_solicitada"])
            ->assertSet('showForm', true)
            ->assertSee('Hay 1 línea con errores')
            ->assertSee('LOTE-019');

        $this->assertDatabaseMissing('solicitudes_proveedor', ['folio' => 'SP-ERR-PAG-001']);
    }

    public function test_a_selected_row_without_articulo_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->formularioBasico($component, 'SP-SIN-ART-001', $vendor->id)
            ->call('toggleSeleccion', 's'.$sic->id)
            ->set('seleccion.s'.$sic->id.'.articulo_id', null)
            ->call('save')
            ->assertHasErrors(['seleccion.s'.$sic->id.'.articulo_id']);
    }

    /** Elegir un artículo en una fila de pool descarta una descripción libre heredada. */
    public function test_choosing_an_articulo_on_a_pool_row_clears_a_legacy_free_description(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');
        $articulo = $this->articulo();

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 's'.$sic->id)
            ->set('seleccion.s'.$sic->id.'.descripcion_libre', 'texto heredado')
            ->set('seleccion.s'.$sic->id.'.articulo_id', $articulo->id);

        $this->assertNull($component->get('seleccion.s'.$sic->id.'.descripcion_libre'));
    }

    /** "Volver al listado" descarta el formulario, la selección y los filtros del pool. */
    public function test_cancel_returns_to_the_listing_and_discards_the_form_state(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('toggleSeleccion', 's'.$sic->id)
            ->set('sicSearch', 'algo')
            ->set('soloSeleccionadas', true)
            ->call('cancel')
            ->assertSet('showForm', false)
            ->assertSet('seleccion', [])
            ->assertSet('lineasManuales', [])
            ->assertSet('sicSearch', '')
            ->assertSet('soloSeleccionadas', false)
            ->assertSet('editingId', null);
    }

    /** El detalle de SIC sigue abriéndose como modal desde el link de la tabla (con el formulario a la vista). */
    public function test_sic_link_in_the_selection_table_opens_the_detail_modal(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => 'SIC-LINK-1']);

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->assertSee("openSicDetalle({$sic->id}, 0)", false)
            ->call('openSicDetalle', $sic->id, 0)
            ->assertSet('showDetalleModal', true)
            ->assertSet('showForm', true)
            ->assertSee('Detalle de la SIC SIC-LINK-1');
    }

    /** Las filas seleccionadas muestran controles; las demás, solo texto. */
    public function test_only_selected_rows_render_editable_controls(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $uno = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => 'SIC-CTRL-1']);
        $dos = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => 'SIC-CTRL-2']);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $component->assertDontSee("seleccion.s{$uno->id}.cantidad_solicitada", false)
            ->call('toggleSeleccion', 's'.$uno->id)
            ->assertSee("seleccion.s{$uno->id}.cantidad_solicitada", false)
            ->assertDontSee("seleccion.s{$dos->id}.cantidad_solicitada", false);
    }

    /** Armar el pool de SICs no crece en queries con la cantidad de SICs (sin N+1). */
    public function test_building_the_pool_does_not_run_queries_per_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();

        $contarQueries = function (): int {
            $component = Livewire::test(SolicitudesProveedor::class)->call('create');
            \Illuminate\Support\Facades\DB::enableQueryLog();
            \Illuminate\Support\Facades\DB::flushQueryLog();
            $component->instance()->pool;
            $total = count(\Illuminate\Support\Facades\DB::getQueryLog());
            \Illuminate\Support\Facades\DB::disableQueryLog();

            return $total;
        };

        $this->crearSicDeEbs('SIC-NPLUS-1');
        $conPocas = $contarQueries();

        foreach (range(2, 12) as $n) {
            $this->crearSicDeEbs("SIC-NPLUS-$n");
        }
        $conMuchas = $contarQueries();

        $this->assertSame($conPocas, $conMuchas);
    }

    public function test_listing_screen_is_shown_when_the_form_is_closed_and_the_form_when_open(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        SolicitudProveedor::create(['folio' => 'SP-LISTA-001', 'vendor_id' => $vendor->id, 'fecha_solicitud' => '2026-09-01', 'tipo_solicitud' => 'regular']);

        Livewire::test(SolicitudesProveedor::class)
            ->assertSee('SP-LISTA-001')
            // (El texto "Volver al listado" también aparece en la ayuda, así
            // que se distingue por el botón real que dispara `cancel`.)
            ->assertDontSee('wire:click="cancel"', false)
            ->call('create')
            ->assertSee('wire:click="cancel"', false)
            ->assertSee('Datos de la solicitud')
            ->assertSee('Líneas manuales (sin SIC real)')
            ->assertDontSee('SP-LISTA-001');
    }

    // --- Detalle de SIC al dar clic en la columna "SIC" de la tabla --------

    /**
     * Línea con `ebs_requisition_id` directo (sin SIC local) — clic en su
     * número de SIC abre el mismo detalle de requisición que "SIC en EBS",
     * vía el partial compartido `partials.ebs-requisicion-detalle`.
     */
    public function test_open_sic_detalle_for_an_ebs_direct_line_loads_the_requisicion_detail(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('DETALLE-EBS-1', 7100);

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('openSicDetalle', 0, $ebsRequisicion->id);

        $component->assertSet('showDetalleModal', true);
        $component->assertSet('detalleEbsRequisitionId', $ebsRequisicion->id);
        $component->assertSet('detalleSicLocalId', null);
        $component->assertSee($ebsRequisicion->code);
        $component->assertSee('Detalle de la requisición');
    }

    /**
     * Línea con una SIC local vinculada a EBS (`sic_id` con `ebs_requisition_id`
     * en la propia SIC) — clic en su número de SIC también resuelve y abre
     * el detalle de la requisición de EBS (no el de SIC local), aunque el
     * parámetro que llega del picker sea `sic_id`.
     */
    public function test_open_sic_detalle_for_a_local_sic_linked_to_ebs_resolves_the_requisicion_detail(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('DETALLE-EBS-2', 7101);
        $sic = $this->crearSicAutorizada(overrides: ['ebs_requisition_id' => $ebsRequisicion->id]);

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('openSicDetalle', $sic->id, 0);

        $component->assertSet('showDetalleModal', true);
        $component->assertSet('detalleEbsRequisitionId', $ebsRequisicion->id);
        $component->assertSet('detalleSicLocalId', null);
        $component->assertSee($ebsRequisicion->code);
    }

    /**
     * Línea con una SIC puramente local (nunca sincronizada de EBS) — clic
     * en su número de SIC abre en su lugar `partials.sic-local-detalle`, con
     * los datos propios de la captura local (folio, empleado, artículo).
     */
    public function test_open_sic_detalle_for_a_pure_local_sic_loads_the_sic_local_detail(): void
    {
        $this->actingAs($this->actingUser());
        $sic = $this->crearSicAutorizada(overrides: ['folio_sic' => 'SIC-DETALLE-LOCAL']);

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('openSicDetalle', $sic->id, 0);

        $component->assertSet('showDetalleModal', true);
        $component->assertSet('detalleSicLocalId', $sic->id);
        $component->assertSet('detalleEbsRequisitionId', null);
        $component->assertSee('SIC-DETALLE-LOCAL');
        $component->assertSee($sic->empleado->nombre);
    }

    /** `closeDetalle()` limpia el estado de los 2 posibles detalles. */
    public function test_close_detalle_resets_both_detail_ids(): void
    {
        $this->actingAs($this->actingUser());
        $sic = $this->crearSicAutorizada();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->call('openSicDetalle', $sic->id, 0)
            ->call('closeDetalle')
            ->assertSet('showDetalleModal', false)
            ->assertSet('detalleSicLocalId', null)
            ->assertSet('detalleEbsRequisitionId', null);
    }
}
