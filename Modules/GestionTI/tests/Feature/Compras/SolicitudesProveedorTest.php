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
            ->set('form.folio', 'SP-TEST-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.articulo_id', $articulo->id)
            ->set('lineas.0.cantidad_solicitada', 3)
            ->set('lineas.0.precio_unitario_cotizado', 150.50)
            ->call('addLinea')
            ->set('lineas.1.descripcion_libre', 'Cable HDMI especial')
            ->set('lineas.1.cantidad_solicitada', 1)
            ->set('lineas.1.es_activo_inventariable', true)
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
            'es_activo_inventariable' => true,
        ]);
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
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-10-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.descripcion_libre', 'Línea 1')
            ->set('lineas.0.cantidad_solicitada', 1)
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
            ->set('form.folio', 'SP-TEST-002')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.articulo_id', $articulo->id)
            ->set('lineas.0.descripcion_libre', 'Descripción libre también capturada')
            ->set('lineas.0.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasErrors(['lineas.0.articulo_id']);

        $this->assertDatabaseMissing('solicitudes_proveedor', ['folio' => 'SP-TEST-002']);
    }

    public function test_line_with_neither_articulo_nor_descripcion_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-TEST-003')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.cantidad_solicitada', 1)
            ->call('save')
            ->assertHasErrors(['lineas.0.articulo_id']);
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
            ->call('removeLinea', 0)
            ->call('save')
            ->assertHasErrors(['lineas']);
    }

    public function test_sic_and_proyecto_presupuesto_articulo_cannot_both_be_set(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        $ticket = Ticket::create([
            'fecha' => '2026-08-01',
            'empleado_id' => Empleado::create(['numero_empleado' => 'EMP-1', 'nombre' => 'Solicitante'])->id,
        ]);

        $sic = SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $ticket->empleado_id,
            'tipo_equipo_id' => TipoEquipo::create(['nombre' => 'Laptop'])->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => CentroCosto::create([
                'codigo' => 'CC-1',
                'nombre' => 'Corporativo',
                'empresa_id' => Empresa::create(['razon_social' => 'Kosmos', 'nombre_comercial' => 'Kosmos'])->id,
            ])->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => 'autorizada',
            'folio_sic' => 'SIC-1',
        ]);

        $proyectoArticulo = $this->proyectoPresupuestoArticulo();

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-TEST-005')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-08-31')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.sic_id', $sic->id)
            ->set('lineas.0.articulo_id', $articulo->id)
            ->set('lineas.0.cantidad_solicitada', 1)
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
            ->set('lineas.0.cantidad_solicitada', 5)
            ->call('addLinea')
            ->set('lineas.1.descripcion_libre', 'Extra')
            ->set('lineas.1.cantidad_solicitada', 1)
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
            ->set('form.folio', 'SP-PROYECTO-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id)
            ->set('lineas.0.descripcion_libre', 'Laptop para gerente de centro')
            ->set('lineas.0.cantidad_solicitada', 2)
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
        $vendor = $this->proveedor();
        $proyectoArticulo = $this->proyectoPresupuestoArticulo();

        $ticket = Ticket::create([
            'fecha' => '2026-08-01',
            'empleado_id' => Empleado::create(['numero_empleado' => 'EMP-2', 'nombre' => 'Solicitante 2'])->id,
        ]);

        $sic = SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $ticket->empleado_id,
            'tipo_equipo_id' => TipoEquipo::create(['nombre' => 'Laptop'])->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => CentroCosto::create([
                'codigo' => 'CC-2',
                'nombre' => 'Corporativo',
                'empresa_id' => Empresa::create(['razon_social' => 'Kosmos 2', 'nombre_comercial' => 'Kosmos 2'])->id,
            ])->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => 'autorizada',
            'folio_sic' => 'SIC-2',
        ]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-PROYECTO-003')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.sic_id', $sic->id)
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id)
            ->set('lineas.0.descripcion_libre', 'Laptop para gerente de centro')
            ->set('lineas.0.cantidad_solicitada', 2)
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

        $this->assertTrue($component->viewData('sicPickerOptions')->pluck('id')->contains($sic->id));
    }

    public function test_sic_with_category_not_marked_as_compra_does_not_appear_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('telefonia_fija');

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertFalse($component->viewData('sicPickerOptions')->pluck('id')->contains($sic->id));
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

        $this->assertFalse($component->viewData('sicPickerOptions')->pluck('id')->contains($sic->id));
    }

    public function test_sic_already_used_by_another_solicitud_does_not_appear_again(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-SIC-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('sicIdsSeleccionados', [$sic->id])
            ->call('save')
            ->assertHasNoErrors();

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');
        $this->assertFalse($component->viewData('sicPickerOptions')->pluck('id')->contains($sic->id));
    }

    public function test_selecting_sics_creates_lines_and_unchecking_removes_them(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sicUno = $this->crearSicAutorizada('laptops_desktops');
        $sicDos = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('sicIdsSeleccionados', [$sicUno->id, $sicDos->id]);

        $sicIdsEnLineas = collect($component->get('lineas'))->pluck('sic_id')->filter()->values()->all();
        $this->assertEqualsCanonicalizing([$sicUno->id, $sicDos->id], $sicIdsEnLineas);

        $component->set('sicIdsSeleccionados', [$sicUno->id]);

        $sicIdsEnLineasDespues = collect($component->get('lineas'))->pluck('sic_id')->filter()->values()->all();
        $this->assertSame([$sicUno->id], $sicIdsEnLineasDespues);
    }

    public function test_saving_with_multiple_selected_sics_creates_one_line_per_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sicUno = $this->crearSicAutorizada('laptops_desktops');
        $sicDos = $this->crearSicAutorizada('laptops_desktops');

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-SIC-MULTI')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('sicIdsSeleccionados', [$sicUno->id, $sicDos->id])
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
            ->set('form.folio', 'SP-SIC-MANUAL')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.folio_sic_manual', 'SIC-A-MANO-001')
            ->set('lineas.0.descripcion_libre', 'Laptop capturada a mano, SIC aún sin registro')
            ->set('lineas.0.cantidad_solicitada', 1)
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

    public function test_editing_keeps_its_own_already_linked_sic_visible_and_checked_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $vendor = $this->proveedor();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-SIC-EDIT')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('sicIdsSeleccionados', [$sic->id])
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-SIC-EDIT')->firstOrFail();

        $component = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $this->assertTrue($component->viewData('sicPickerOptions')->pluck('id')->contains($sic->id));
        $this->assertContains($sic->id, $component->get('sicIdsSeleccionados'));
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

        $component->assertSet('showModal', true);
        $this->assertContains($sic->id, $component->get('sicIdsSeleccionados'));
        $sicIdsEnLineas = collect($component->get('lineas'))->pluck('sic_id')->filter()->values()->all();
        $this->assertContains($sic->id, $sicIdsEnLineas);
    }

    public function test_mount_without_crear_desde_sics_opens_normally(): void
    {
        $this->actingAs($this->actingUser());

        $component = Livewire::test(SolicitudesProveedor::class);

        $component->assertSet('showModal', false);
    }

    public function test_line_from_an_ebs_sic_shows_the_original_ebs_description_for_reference(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $sic = $this->crearSicDeEbs('SIC-EBS-REF');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.vendor_id', $vendor->id)
            ->set('sicIdsSeleccionados', [$sic->id]);

        $lineas = collect($component->get('lineas'));
        $linea = $lineas->firstWhere('sic_id', $sic->id);

        $this->assertSame('LAPTOP ITEM EBS ORIGINAL', $linea['ebs_item_description']);
        $component->assertSee('LAPTOP ITEM EBS ORIGINAL');
    }

    public function test_line_from_a_local_sic_has_no_ebs_description(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops');

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('sicIdsSeleccionados', [$sic->id]);

        $lineas = collect($component->get('lineas'));
        $linea = $lineas->firstWhere('sic_id', $sic->id);

        $this->assertNull($linea['ebs_item_description']);
    }

    public function test_observaciones_especificaciones_is_saved_and_retrieved_per_line(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-OBS-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.articulo_id', $articulo->id)
            ->set('lineas.0.cantidad_solicitada', 1)
            ->set('lineas.0.observaciones_especificaciones', 'Con teclado en español')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('solicitud_proveedor_lineas', [
            'observaciones_especificaciones' => 'Con teclado en español',
        ]);

        $solicitud = SolicitudProveedor::where('folio', 'SP-OBS-001')->firstOrFail();
        $component = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $this->assertSame('Con teclado en español', $component->get('lineas.0.observaciones_especificaciones'));
    }

    public function test_create_writes_creado_por_user_id(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user);
        $vendor = $this->proveedor();
        $articulo = $this->articulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-CREADOPOR-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('lineas.0.articulo_id', $articulo->id)
            ->set('lineas.0.cantidad_solicitada', 1)
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
            ->assertSet('showModal', false);

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
            ->assertSet('showModal', true)
            ->set('lineas.0.cantidad_solicitada', 9)
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

        $this->assertTrue($component->viewData('ebsPickerOptions')->pluck('id')->contains($ebsRequisicion->id));
    }

    public function test_ebs_requisition_with_an_existing_local_sic_does_not_appear_in_the_direct_picker(): void
    {
        $this->actingAs($this->actingUser());
        $this->marcarCategoriaComoCompra();
        $sic = $this->crearSicAutorizada('laptops_desktops', ['folio_sic' => 'CON-SIC-LOCAL']);
        $ebsRequisicion = EbsRequisition::create(['requisition_header_id' => random_int(1000000, 9999999), 'code' => 'CON-SIC-LOCAL', 'status' => 'APPROVED']);
        $sic->update(['ebs_requisition_id' => $ebsRequisicion->id]);

        $component = Livewire::test(SolicitudesProveedor::class)->call('create');

        $this->assertFalse($component->viewData('ebsPickerOptions')->pluck('id')->contains($ebsRequisicion->id));
    }

    public function test_selecting_an_ebs_direct_requisition_creates_a_line_with_the_mapped_articulo(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('PICKER-EBS-2', 7002);
        $articulo = ArticuloSolicitud::where('codigo', 'ART-EBS-DIRECTO-PICKER-EBS-2')->firstOrFail();

        $component = Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('ebsIdsSeleccionados', [$ebsRequisicion->id]);

        $linea = collect($component->get('lineas'))->firstWhere('ebs_requisition_id', $ebsRequisicion->id);
        $this->assertNotNull($linea);
        $this->assertSame($articulo->id, $linea['articulo_id']);
        $this->assertNull($linea['sic_id']);
    }

    public function test_saving_a_line_from_an_ebs_direct_requisition_persists_ebs_requisition_id_and_null_sic_id(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $ebsRequisicion = $this->crearEbsDirectoElegible('SAVE-EBS-1', 7003);

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-EBS-DIRECTO-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('ebsIdsSeleccionados', [$ebsRequisicion->id])
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
            ->call('create')
            ->set('ebsIdsSeleccionados', [$ebsRequisicion->id]);

        $linea = collect($component->get('lineas'))->firstWhere('ebs_requisition_id', $ebsRequisicion->id);
        $this->assertSame('LAPTOP ITEM EBS DIRECTO ORIGINAL', $linea['ebs_item_description']);
        $component->assertSee('LAPTOP ITEM EBS DIRECTO ORIGINAL');
    }

    public function test_mount_with_crear_desde_ebs_query_param_preloads_the_creation_form(): void
    {
        $this->actingAs($this->actingUser());
        $ebsRequisicion = $this->crearEbsDirectoElegible('MOUNT-EBS-1', 7005);

        $component = Livewire::withQueryParams(['crear_desde_ebs' => (string) $ebsRequisicion->id])->test(SolicitudesProveedor::class);

        $component->assertSet('showModal', true);
        $this->assertContains($ebsRequisicion->id, $component->get('ebsIdsSeleccionados'));
        $ebsIdsEnLineas = collect($component->get('lineas'))->pluck('ebs_requisition_id')->filter()->values()->all();
        $this->assertContains($ebsRequisicion->id, $ebsIdsEnLineas);
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

        $component->assertSet('showModal', true);
        $this->assertContains($sic->id, $component->get('sicIdsSeleccionados'));
        $this->assertContains($ebsRequisicion->id, $component->get('ebsIdsSeleccionados'));
    }

    public function test_editing_keeps_its_own_ebs_direct_requisition_visible_and_checked_in_the_picker(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $ebsRequisicion = $this->crearEbsDirectoElegible('EDIT-EBS-1', 7007);

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-EBS-EDIT')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('ebsIdsSeleccionados', [$ebsRequisicion->id])
            ->call('save')
            ->assertHasNoErrors();

        $solicitud = SolicitudProveedor::where('folio', 'SP-EBS-EDIT')->firstOrFail();

        $component = Livewire::test(SolicitudesProveedor::class)->call('edit', $solicitud->id);

        $this->assertTrue($component->viewData('ebsPickerOptions')->pluck('id')->contains($ebsRequisicion->id));
        $this->assertContains($ebsRequisicion->id, $component->get('ebsIdsSeleccionados'));
    }

    /** `validateOrigenUnico()` también se dispara con una línea de EBS directo. */
    public function test_origen_unico_validation_also_triggers_with_an_ebs_direct_line(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $ebsRequisicion = $this->crearEbsDirectoElegible('ORIGEN-EBS-1', 7008);
        $proyectoArticulo = $this->proyectoPresupuestoArticulo();

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-ORIGEN-EBS')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.tipo_solicitud', 'regular')
            ->set('ebsIdsSeleccionados', [$ebsRequisicion->id])
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id)
            ->call('save')
            ->assertHasErrors(['origen', 'form.proyecto_presupuesto_articulo_id']);

        $this->assertDatabaseMissing('solicitudes_proveedor', ['folio' => 'SP-ORIGEN-EBS']);
    }
}
