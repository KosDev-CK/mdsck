<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Compras;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Proveedor;
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

        Livewire::test(Compras::class)
            ->call('setTab', 'articulos_solicitud')
            ->assertSee('Laptop')
            ->call('create')
            ->set('form.codigo', 'ART-002')
            ->set('form.descripcion', 'Laptop Dell Latitude')
            ->set('form.unidad_medida', 'pieza')
            ->set('form.categoria', 'Cómputo')
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('articulos_solicitud', [
            'codigo' => 'ART-002',
            'tipo_equipo_id' => $tipoEquipo->id,
            'categoria' => 'Cómputo',
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
}
