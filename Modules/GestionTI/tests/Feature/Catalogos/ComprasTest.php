<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Compras;
use Modules\GestionTI\Models\Almacenamiento;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\CategoriaArticulo;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\EbsArticulo;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Procesador;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\Ram;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\TipoEquipo;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComprasTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Compras',
            'name' => 'Catálogos de Compras',
            'slug' => 'gestionti-catalogos-compras',
            'route_name' => 'gestionti.catalogos.compras',
            'permission_name' => 'screens.gestionti-catalogos-compras.manage',
            'icon' => 'truck',
            'order' => 20,
        ]);

        $role = Role::findOrCreate('Compras', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/catalogos/compras')->assertForbidden();
    }

    public function test_can_create_a_proveedor(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('create')
            ->set('form.nombre_comercial', 'ProveeTI')
            ->set('form.razon_social', 'ProveeTI S.A. de C.V.')
            ->set('form.rfc', 'PTI010101AAA')
            ->set('form.contacto_nombre', 'Ana López')
            ->set('form.contacto_telefono', '555-123-4567')
            ->set('form.contacto_correo', 'ana@proveeti.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('proveedores', [
            'nombre_comercial' => 'ProveeTI',
            'razon_social' => 'ProveeTI S.A. de C.V.',
            'contacto_nombre' => 'Ana López',
        ]);
    }

    public function test_proveedor_requires_nombre_comercial_and_razon_social(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors(['form.razon_social', 'form.nombre_comercial']);
    }

    public function test_proveedor_validates_contacto_correo_format(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('create')
            ->set('form.nombre_comercial', 'ProveeTI')
            ->set('form.razon_social', 'ProveeTI S.A. de C.V.')
            ->set('form.contacto_correo', 'no-es-un-correo')
            ->call('save')
            ->assertHasErrors(['form.contacto_correo']);
    }

    public function test_can_switch_tabs_and_create_an_articulo_de_solicitud(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->assertSet('tab', 'articulos_solicitud')
            ->call('create')
            ->set('form.codigo', 'ART-001')
            ->set('form.descripcion', 'Mouse óptico')
            ->set('form.unidad_medida', 'pieza')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('articulos_solicitud', [
            'codigo' => 'ART-001',
            'descripcion' => 'Mouse óptico',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => null,
        ]);
    }

    public function test_articulo_de_solicitud_requires_codigo_descripcion_and_unidad_medida(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('create')
            ->call('save')
            ->assertHasErrors(['form.codigo', 'form.descripcion', 'form.unidad_medida']);
    }

    public function test_can_create_an_articulo_de_solicitud_with_tipo_equipo(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $categoriaId = CategoriaArticulo::where('slug', 'laptops_desktops')->value('id');

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->assertSee('Laptop')
            ->call('create')
            ->set('form.codigo', 'ART-002')
            ->set('form.descripcion', 'Laptop Dell Latitude')
            ->set('form.unidad_medida', 'pieza')
            ->set('form.categoria_id', $categoriaId)
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('articulos_solicitud', [
            'codigo' => 'ART-002',
            'tipo_equipo_id' => $tipoEquipo->id,
            'categoria_id' => $categoriaId,
        ]);
    }

    public function test_articulo_de_solicitud_rejects_a_categoria_id_that_does_not_exist(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('create')
            ->set('form.codigo', 'ART-CAT-INVALIDA')
            ->set('form.descripcion', 'Artículo con categoría inválida')
            ->set('form.unidad_medida', 'pieza')
            ->set('form.categoria_id', 999999)
            ->call('save')
            ->assertHasErrors(['form.categoria_id']);
    }

    public function test_can_create_an_articulo_de_solicitud_with_ficha_tecnica_completa(): void
    {
        $this->actingAs($this->actingUser());

        $marca = Marca::create(['nombre' => 'Dell']);
        $modelo = Modelo::create(['nombre' => 'Latitude 5440', 'marca_id' => $marca->id]);
        $procesador = Procesador::create(['nombre' => 'Core i7']);
        $ram = Ram::create(['nombre' => '16GB']);
        $almacenamiento = Almacenamiento::create(['nombre' => '512GB SSD']);

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('create')
            ->set('form.codigo', 'ART-FICHA-001')
            ->set('form.descripcion', 'Laptop Core i7 16GB 512GB SSD')
            ->set('form.unidad_medida', 'pieza')
            ->set('form.categoria_id', CategoriaArticulo::where('slug', 'laptops_desktops')->value('id'))
            ->set('form.marca_id', $marca->id)
            ->set('form.modelo_id', $modelo->id)
            ->set('form.procesador_id', $procesador->id)
            ->set('form.ram_id', $ram->id)
            ->set('form.almacenamiento_id', $almacenamiento->id)
            ->set('form.es_inventariable', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('articulos_solicitud', [
            'codigo' => 'ART-FICHA-001',
            'marca_id' => $marca->id,
            'modelo_id' => $modelo->id,
            'procesador_id' => $procesador->id,
            'ram_id' => $ram->id,
            'almacenamiento_id' => $almacenamiento->id,
            'es_inventariable' => 1,
        ]);
    }

    public function test_can_edit_an_articulo_de_solicitud_ficha_tecnica(): void
    {
        $this->actingAs($this->actingUser());

        $marcaUno = Marca::create(['nombre' => 'Dell']);
        $marcaDos = Marca::create(['nombre' => 'HP']);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-FICHA-002',
            'descripcion' => 'Laptop genérica',
            'unidad_medida' => 'pieza',
            'marca_id' => $marcaUno->id,
            'es_inventariable' => false,
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('edit', $articulo->id)
            ->assertSet('form.marca_id', $marcaUno->id)
            ->set('form.marca_id', $marcaDos->id)
            ->set('form.es_inventariable', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('articulos_solicitud', [
            'id' => $articulo->id,
            'marca_id' => $marcaDos->id,
            'es_inventariable' => 1,
        ]);
    }

    public function test_can_edit_an_articulo_de_solicitud_and_reassign_tipo_equipo(): void
    {
        $this->actingAs($this->actingUser());

        $tipoUno = TipoEquipo::create(['nombre' => 'Laptop']);
        $tipoDos = TipoEquipo::create(['nombre' => 'Monitor']);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-003',
            'descripcion' => 'Equipo genérico',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $tipoUno->id,
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('edit', $articulo->id)
            ->assertSet('form.tipo_equipo_id', $tipoUno->id)
            ->set('form.tipo_equipo_id', $tipoDos->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('articulos_solicitud', [
            'id' => $articulo->id,
            'tipo_equipo_id' => $tipoDos->id,
        ]);
    }

    public function test_articulo_de_solicitud_tipo_equipo_is_optional_when_editing_to_unassigned(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-004',
            'descripcion' => 'Equipo con tipo',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('edit', $articulo->id)
            ->set('form.tipo_equipo_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('articulos_solicitud', [
            'id' => $articulo->id,
            'tipo_equipo_id' => null,
        ]);
    }

    public function test_can_toggle_activo_on_proveedor(): void
    {
        $this->actingAs($this->actingUser());

        $proveedor = Proveedor::create([
            'razon_social' => 'ProveeTI S.A. de C.V.',
            'nombre_comercial' => 'ProveeTI',
        ]);

        Livewire::test(Compras::class)
            ->call('toggleActivo', $proveedor->id);

        $this->assertFalse($proveedor->fresh()->activo);
    }

    public function test_can_toggle_activo_on_articulo_de_solicitud(): void
    {
        $this->actingAs($this->actingUser());

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-005',
            'descripcion' => 'Teclado',
            'unidad_medida' => 'pieza',
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('toggleActivo', $articulo->id);

        $this->assertFalse($articulo->fresh()->activo);
    }

    public function test_can_delete_a_proveedor_without_dependents(): void
    {
        $this->actingAs($this->actingUser());

        $proveedor = Proveedor::create(['nombre_comercial' => 'Temporal', 'razon_social' => 'Temporal S.A. de C.V.']);

        Livewire::test(Compras::class)->call('delete', $proveedor->id);

        $this->assertDatabaseMissing('proveedores', ['id' => $proveedor->id]);
    }

    public function test_cannot_delete_a_proveedor_referenced_by_an_asset(): void
    {
        $this->actingAs($this->actingUser());

        $proveedor = Proveedor::create(['nombre_comercial' => 'Con Activo', 'razon_social' => 'Con Activo S.A. de C.V.']);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $estatus = \Modules\GestionTI\Models\EstatusActivo::create(['codigo' => 'en_stock', 'nombre' => 'En stock']);

        \Modules\GestionTI\Models\Asset::create([
            'codigo' => 'KOS-TEST-000002',
            'tipo_equipo_id' => $tipoEquipo->id,
            'origen_tipo' => 'ajuste_manual',
            'estatus_id' => $estatus->id,
            'vendor_id' => $proveedor->id,
        ]);

        Livewire::test(Compras::class)
            ->call('delete', $proveedor->id)
            ->assertSee('No se puede eliminar');

        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id]);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-catalogos-compras',
            'route_name' => 'gestionti.catalogos.compras',
            'group_label' => 'Compras',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-catalogos-compras.manage'));

        $screen = Screen::where('slug', 'gestionti-catalogos-compras')->first();
        $this->assertNotNull($screen);
    }

    // ==================================================================
    // Tab "Categoría" — reemplaza la pantalla standalone "Categorías que
    // van a Compra"/`ConfiguracionCategorias` (retiradas). `slug` nunca es
    // un campo del formulario — se genera solo, ver
    // `Modules\GestionTI\Models\CategoriaArticulo`.
    // ==================================================================

    private function proyectoPresupuestoArticuloParaCategoria(int $categoriaId): ProyectoPresupuestoArticulo
    {
        $empresa = Empresa::create(['razon_social' => 'Kosmos', 'nombre_comercial' => 'Kosmos']);
        $empleado = Empleado::create(['numero_empleado' => 'EMP-CAT-'.random_int(1000, 9999), 'nombre' => 'PM de Prueba']);

        $proyecto = ProyectoPresupuesto::create([
            'nombre_proyecto' => 'Proyecto para categoría en uso',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => CentroCosto::create(['codigo' => 'CC-CAT-'.random_int(1000, 9999), 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id])->id,
            'direccion_centro' => 'Av. Siempre Viva 123',
            'area_operativa_solicitante_id' => Area::create(['nombre' => 'Operaciones '.random_int(1000, 9999)])->id,
            'pm_responsable_id' => $empleado->id,
            'fecha_solicitud' => '2026-08-01',
            'fecha_limite_captura' => '2026-08-15',
        ]);

        return $proyecto->articulos()->create([
            'categoria_id' => $categoriaId,
            'descripcion' => 'Artículo para prueba de categoría en uso',
            'cantidad' => 1,
            'responsable_costo_id' => $empleado->id,
        ]);
    }

    public function test_can_create_a_categoria_auto_generating_a_unique_slug_from_nombre(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->assertSet('tab', 'categorias')
            ->call('create')
            ->set('form.nombre', 'Monitores Externos')
            ->set('form.es_compra', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias_articulo', [
            'nombre' => 'Monitores Externos',
            'slug' => 'monitores_externos',
            'es_compra' => 1,
        ]);
    }

    public function test_creating_a_categoria_whose_slug_would_collide_gets_a_numeric_suffix(): void
    {
        $this->actingAs($this->actingUser());

        // La migración ya sembró 'Multifuncionales' con slug
        // 'multifuncionales' — crear otra con el mismo nombre debe generar
        // un slug distinto, nunca chocar.
        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('create')
            ->set('form.nombre', 'Multifuncionales')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias_articulo', [
            'nombre' => 'Multifuncionales',
            'slug' => 'multifuncionales_2',
        ]);
    }

    public function test_categoria_requires_nombre(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('create')
            ->call('save')
            ->assertHasErrors(['form.nombre']);
    }

    public function test_can_edit_a_categoria_name_without_touching_its_slug(): void
    {
        $this->actingAs($this->actingUser());

        $categoria = CategoriaArticulo::where('slug', 'laptops_desktops')->firstOrFail();

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('edit', $categoria->id)
            ->assertSet('form.nombre', 'Laptops/Desktops')
            ->set('form.nombre', 'Laptops y Desktops')
            ->call('save')
            ->assertHasNoErrors();

        $categoria->refresh();
        $this->assertSame('Laptops y Desktops', $categoria->nombre);
        // El slug protegido nunca se toca al editar, aunque el nombre
        // cambie por completo.
        $this->assertSame('laptops_desktops', $categoria->slug);
    }

    public function test_can_toggle_es_compra_on_a_categoria(): void
    {
        $this->actingAs($this->actingUser());

        $categoria = CategoriaArticulo::where('slug', 'telefonia_fija')->firstOrFail();
        $this->assertFalse($categoria->es_compra);

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('edit', $categoria->id)
            ->set('form.es_compra', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($categoria->fresh()->es_compra);
    }

    public function test_can_toggle_activo_on_a_categoria(): void
    {
        $this->actingAs($this->actingUser());

        $categoria = CategoriaArticulo::where('slug', 'antivirus')->firstOrFail();

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('toggleActivo', $categoria->id);

        $this->assertFalse($categoria->fresh()->activo);
    }

    public function test_cannot_delete_a_categoria_referenced_by_an_articulo_de_solicitud(): void
    {
        $this->actingAs($this->actingUser());

        $categoria = CategoriaArticulo::where('slug', 'redes')->firstOrFail();

        ArticuloSolicitud::create([
            'codigo' => 'ART-CAT-USO',
            'descripcion' => 'Switch de red',
            'unidad_medida' => 'pieza',
            'categoria_id' => $categoria->id,
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('delete', $categoria->id)
            ->assertSee('No se puede eliminar');

        $this->assertDatabaseHas('categorias_articulo', ['id' => $categoria->id]);
    }

    public function test_cannot_delete_a_categoria_referenced_by_a_proyecto_presupuesto_articulo(): void
    {
        $this->actingAs($this->actingUser());

        $categoria = CategoriaArticulo::where('slug', 'vpn')->firstOrFail();
        $this->proyectoPresupuestoArticuloParaCategoria($categoria->id);

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('delete', $categoria->id)
            ->assertSee('No se puede eliminar');

        $this->assertDatabaseHas('categorias_articulo', ['id' => $categoria->id]);
    }

    public function test_can_delete_a_categoria_without_dependents(): void
    {
        $this->actingAs($this->actingUser());

        $categoria = CategoriaArticulo::create(['nombre' => 'Temporal', 'slug' => 'temporal']);

        Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->call('delete', $categoria->id);

        $this->assertDatabaseMissing('categorias_articulo', ['id' => $categoria->id]);
    }

    public function test_categorias_tab_search_filters_by_nombre(): void
    {
        $this->actingAs($this->actingUser());

        $component = Livewire::test(Compras::class)
            ->call('setTab', 'categorias')
            ->set('search', 'Antivirus');

        $nombres = $component->viewData('records')->pluck('nombre')->all();
        $this->assertContains('Antivirus', $nombres);
        $this->assertNotContains('Redes', $nombres);
    }

    // --- tab "Artículos EBS" (mapeo EBS -> artículo estándar) ---------------

    public function test_ebs_articulos_tab_lists_seeded_rows_including_unmapped_ones(): void
    {
        $this->actingAs($this->actingUser());

        EbsArticulo::create(['ebs_item_id' => 6962, 'ebs_item_description' => 'LAPTOP EJECUTIVO CI7']);

        Livewire::test(Compras::class)
            ->call('setTab', 'ebs_articulos')
            ->assertSee('6962')
            ->assertSee('LAPTOP EJECUTIVO CI7')
            ->assertSee('Sin mapear');
    }

    public function test_can_edit_the_mapped_articulo_of_an_ebs_articulo(): void
    {
        $this->actingAs($this->actingUser());
        $ebsArticulo = EbsArticulo::create(['ebs_item_id' => 7001, 'ebs_item_description' => 'ITEM SIN MAPEAR']);
        $articulo = ArticuloSolicitud::create(['codigo' => 'ART-MAP-1', 'descripcion' => 'Laptop Ejecutiva', 'unidad_medida' => 'Pieza']);

        Livewire::test(Compras::class)
            ->call('setTab', 'ebs_articulos')
            ->call('edit', $ebsArticulo->id)
            ->set('form.articulo_id', $articulo->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ebs_articulos', [
            'id' => $ebsArticulo->id,
            'articulo_id' => $articulo->id,
        ]);
    }

    public function test_create_does_nothing_for_the_ebs_articulos_tab(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'ebs_articulos')
            ->call('create')
            ->assertSet('showModal', false);

        $this->assertSame(0, EbsArticulo::count());
    }

    public function test_delete_does_nothing_for_the_ebs_articulos_tab(): void
    {
        $this->actingAs($this->actingUser());
        $ebsArticulo = EbsArticulo::create(['ebs_item_id' => 7002]);

        Livewire::test(Compras::class)
            ->call('setTab', 'ebs_articulos')
            ->call('delete', $ebsArticulo->id);

        $this->assertDatabaseHas('ebs_articulos', ['id' => $ebsArticulo->id]);
    }

    public function test_ebs_articulos_tab_search_filters_by_item_id_or_description(): void
    {
        $this->actingAs($this->actingUser());
        EbsArticulo::create(['ebs_item_id' => 8001, 'ebs_item_description' => 'MONITOR 24 PULGADAS']);
        EbsArticulo::create(['ebs_item_id' => 8002, 'ebs_item_description' => 'TECLADO INALAMBRICO']);

        $component = Livewire::test(Compras::class)
            ->call('setTab', 'ebs_articulos')
            ->set('search', 'MONITOR');

        $descripciones = $component->viewData('records')->pluck('ebs_item_description')->all();
        $this->assertContains('MONITOR 24 PULGADAS', $descripciones);
        $this->assertNotContains('TECLADO INALAMBRICO', $descripciones);
    }

    // --- tab "Lugar de entrega" ----------------------------------------
    // Catálogo nuevo (ver docs/gestionti-progreso.md, tabla compacta de
    // "Líneas del pedido" en Solicitud a Proveedores) — sembrado
    // incondicionalmente por la migración con Zurich/CEDA/Sotelo, mismo
    // patrón `mergeReferences` que el tab "categorias".

    public function test_lugares_entrega_tab_lists_the_seeded_rows(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->assertSet('tab', 'lugares_entrega')
            ->assertSee('Zurich')
            ->assertSee('CEDA')
            ->assertSee('Sotelo');
    }

    public function test_can_create_a_lugar_de_entrega(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('create')
            ->set('form.nombre', 'Bodega Norte')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lugares_entrega', ['nombre' => 'Bodega Norte']);
    }

    public function test_lugar_de_entrega_requires_nombre(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('create')
            ->call('save')
            ->assertHasErrors(['form.nombre']);
    }

    public function test_can_edit_a_lugar_de_entrega(): void
    {
        $this->actingAs($this->actingUser());

        $lugar = LugarEntrega::where('nombre', 'CEDA')->firstOrFail();

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('edit', $lugar->id)
            ->assertSet('form.nombre', 'CEDA')
            ->set('form.nombre', 'CEDA Corregido')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('CEDA Corregido', $lugar->fresh()->nombre);
    }

    public function test_a_lugar_de_entrega_can_be_mapped_to_an_inventory_location(): void
    {
        $this->actingAs($this->actingUser());
        $ubicacion = \Modules\GestionTI\Models\Ubicacion::create(['nombre' => 'CEDA BODEGA A30']);
        $lugar = LugarEntrega::where('nombre', 'CEDA')->firstOrFail();

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('edit', $lugar->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($ubicacion->id, $lugar->fresh()->ubicacion_id);

        // "Sin ubicación" (select vacío) vuelve a null en vez de reventar la FK.
        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('edit', $lugar->id)
            ->set('form.ubicacion_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($lugar->fresh()->ubicacion_id);
    }

    public function test_can_toggle_activo_on_a_lugar_de_entrega(): void
    {
        $this->actingAs($this->actingUser());

        $lugar = LugarEntrega::where('nombre', 'Sotelo')->firstOrFail();

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('toggleActivo', $lugar->id);

        $this->assertFalse($lugar->fresh()->activo);
    }

    public function test_can_delete_a_lugar_de_entrega_without_dependents(): void
    {
        $this->actingAs($this->actingUser());

        $lugar = LugarEntrega::create(['nombre' => 'Temporal']);

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('delete', $lugar->id);

        $this->assertDatabaseMissing('lugares_entrega', ['id' => $lugar->id]);
    }

    public function test_cannot_delete_a_lugar_de_entrega_referenced_by_a_solicitud_proveedor_linea(): void
    {
        $this->actingAs($this->actingUser());

        $lugar = LugarEntrega::where('nombre', 'Zurich')->firstOrFail();

        $vendor = Proveedor::create(['nombre_comercial' => 'Proveedor Lugar', 'razon_social' => 'Proveedor Lugar S.A. de C.V.']);
        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-LUGAR-001',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'descripcion_libre' => 'Artículo para prueba de lugar en uso',
            'cantidad_solicitada' => 1,
            'lugar_entrega_id' => $lugar->id,
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'lugares_entrega')
            ->call('delete', $lugar->id)
            ->assertSee('No se puede eliminar');

        $this->assertDatabaseHas('lugares_entrega', ['id' => $lugar->id]);
    }

    // --- tab "Artículos" — referencias al eliminar/fusionar -------------
    // Antes `mergeReferences` estaba vacío: eliminar o fusionar dejaba en
    // blanco (nullOnDelete) Assets/SICs/líneas/recepciones/mapeos EBS.

    private function articuloEnUso(string $codigo): array
    {
        $articulo = ArticuloSolicitud::create([
            'codigo' => $codigo,
            'descripcion' => "Artículo {$codigo}",
            'unidad_medida' => 'pieza',
        ]);

        $vendor = Proveedor::create(['nombre_comercial' => "Prov {$codigo}", 'razon_social' => "Prov {$codigo} S.A."]);
        $solicitud = SolicitudProveedor::create([
            'folio' => "SP-{$codigo}",
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);
        $linea = $solicitud->lineas()->create([
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 1,
        ]);
        $ebs = EbsArticulo::create(['ebs_item_id' => random_int(100000, 999999), 'articulo_id' => $articulo->id]);

        return [$articulo, $linea, $ebs];
    }

    public function test_cannot_delete_an_articulo_referenced_elsewhere(): void
    {
        $this->actingAs($this->actingUser());

        [$articulo, $linea, $ebs] = $this->articuloEnUso('ART-EN-USO');

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('delete', $articulo->id)
            ->assertSee('No se puede eliminar');

        $this->assertDatabaseHas('articulos_solicitud', ['id' => $articulo->id]);
        $this->assertSame($articulo->id, $linea->fresh()->articulo_id);
        $this->assertSame($articulo->id, $ebs->fresh()->articulo_id);
    }

    public function test_can_delete_an_articulo_without_dependents(): void
    {
        $this->actingAs($this->actingUser());

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-LIBRE',
            'descripcion' => 'Artículo sin uso',
            'unidad_medida' => 'pieza',
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->call('delete', $articulo->id);

        $this->assertDatabaseMissing('articulos_solicitud', ['id' => $articulo->id]);
    }

    public function test_merging_articulos_repoints_every_reference_to_the_kept_one(): void
    {
        $this->actingAs($this->actingUser());

        [$duplicado, $linea, $ebs] = $this->articuloEnUso('ART-DUP');
        $conservado = ArticuloSolicitud::create([
            'codigo' => 'ART-KEEP',
            'descripcion' => 'Artículo que se conserva',
            'unidad_medida' => 'pieza',
        ]);

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->set('mergeDeleteId', $duplicado->id)
            ->set('mergeKeepId', $conservado->id)
            ->call('confirmMerge')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('articulos_solicitud', ['id' => $duplicado->id]);
        $this->assertSame($conservado->id, $linea->fresh()->articulo_id);
        $this->assertSame($conservado->id, $ebs->fresh()->articulo_id);
    }
}
