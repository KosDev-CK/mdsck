<?php

namespace Modules\GestionTI\Tests\Feature\Compras;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Compras\SolicitudesProveedor;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\SolicitudProveedor;
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
     * estatus indicado — usado por los tests del select nuevo "Artículo de
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
            'categoria' => 'laptops_desktops',
            'descripcion' => 'Laptop para gerente de centro',
            'cantidad' => 2,
            'responsable_costo_id' => $empleado->id,
        ]);
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

        $ticket = \Modules\GestionTI\Models\Ticket::create([
            'fecha' => '2026-08-01',
            'empleado_id' => \Modules\GestionTI\Models\Empleado::create(['numero_empleado' => 'EMP-1', 'nombre' => 'Solicitante'])->id,
        ]);

        $sic = \Modules\GestionTI\Models\SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $ticket->empleado_id,
            'tipo_equipo_id' => \Modules\GestionTI\Models\TipoEquipo::create(['nombre' => 'Laptop'])->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => \Modules\GestionTI\Models\CentroCosto::create([
                'codigo' => 'CC-1',
                'nombre' => 'Corporativo',
                'empresa_id' => \Modules\GestionTI\Models\Empresa::create(['razon_social' => 'Kosmos', 'nombre_comercial' => 'Kosmos'])->id,
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
            ->set('form.sic_id', $sic->id)
            ->set('lineas.0.articulo_id', $articulo->id)
            ->set('lineas.0.cantidad_solicitada', 1)
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id);

        $component->call('save')->assertHasErrors(['form.sic_id', 'form.proyecto_presupuesto_articulo_id']);
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

        $ticket = \Modules\GestionTI\Models\Ticket::create([
            'fecha' => '2026-08-01',
            'empleado_id' => Empleado::create(['numero_empleado' => 'EMP-2', 'nombre' => 'Solicitante 2'])->id,
        ]);

        $sic = \Modules\GestionTI\Models\SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $ticket->empleado_id,
            'tipo_equipo_id' => \Modules\GestionTI\Models\TipoEquipo::create(['nombre' => 'Laptop'])->id,
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
            ->set('form.sic_id', $sic->id)
            ->set('form.proyecto_presupuesto_articulo_id', $proyectoArticulo->id)
            ->set('lineas.0.descripcion_libre', 'Laptop para gerente de centro')
            ->set('lineas.0.cantidad_solicitada', 2)
            ->call('save')
            ->assertHasErrors(['form.sic_id', 'form.proyecto_presupuesto_articulo_id']);

        $this->assertDatabaseMissing('solicitudes_proveedor', ['folio' => 'SP-PROYECTO-003']);
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
}
