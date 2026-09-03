<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Inventario;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\PeriodicidadMantenimiento;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Catálogos de Inventario',
            'slug' => 'gestionti-catalogos-inventario',
            'route_name' => 'gestionti.catalogos.inventario',
            'permission_name' => 'screens.gestionti-catalogos-inventario.manage',
            'icon' => 'archive-box',
            'order' => 30,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/catalogos/inventario')->assertForbidden();
    }

    public function test_can_create_a_tipo_de_equipo_with_en_alcance_toggle(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Inventario::class)
            ->call('create')
            ->assertSet('form.en_alcance', true)
            ->set('form.nombre', 'Laptop')
            ->set('form.nombre_conocido', 'Lap')
            ->set('form.en_alcance', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tipos_equipo', [
            'nombre' => 'Laptop',
            'nombre_conocido' => 'Lap',
            'en_alcance' => false,
        ]);
    }

    public function test_can_create_a_marca(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Inventario::class)
            ->call('setTab', 'marcas')
            ->call('create')
            ->set('form.nombre', 'Dell')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('marcas', ['nombre' => 'Dell']);
    }

    public function test_modelo_requires_a_valid_marca_id(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Inventario::class)
            ->call('setTab', 'modelos')
            ->call('create')
            ->set('form.nombre', 'Latitude 5420')
            ->call('save')
            ->assertHasErrors(['form.marca_id']);

        $marca = Marca::create(['nombre' => 'Dell']);

        Livewire::test(Inventario::class)
            ->call('setTab', 'modelos')
            ->call('create')
            ->set('form.nombre', 'Latitude 5420')
            ->set('form.marca_id', $marca->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('modelos', [
            'nombre' => 'Latitude 5420',
            'marca_id' => $marca->id,
        ]);
    }

    public function test_estatus_de_activo_requires_a_unique_codigo(): void
    {
        $this->actingAs($this->actingUser());

        EstatusActivo::create(['codigo' => 'en_stock', 'nombre' => 'En stock']);

        Livewire::test(Inventario::class)
            ->call('setTab', 'estatus_activo')
            ->call('create')
            ->set('form.codigo', 'en_stock')
            ->set('form.nombre', 'Otro nombre')
            ->call('save')
            ->assertHasErrors(['form.codigo']);
    }

    public function test_estatus_de_activo_can_edit_without_tripping_on_its_own_codigo(): void
    {
        $this->actingAs($this->actingUser());

        $estatus = EstatusActivo::create(['codigo' => 'en_stock', 'nombre' => 'En stock']);

        Livewire::test(Inventario::class)
            ->call('setTab', 'estatus_activo')
            ->call('edit', $estatus->id)
            ->set('form.nombre', 'En stock (actualizado)')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estatus_activo', [
            'id' => $estatus->id,
            'codigo' => 'en_stock',
            'nombre' => 'En stock (actualizado)',
        ]);
    }

    public function test_seeding_creates_the_5_base_estatus_records(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        foreach (['en_stock', 'reservado', 'asignado', 'en_reparacion', 'baja'] as $codigo) {
            $this->assertDatabaseHas('estatus_activo', ['codigo' => $codigo]);
        }
    }

    public function test_can_create_a_periodicidad_de_mantenimiento(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);

        Livewire::test(Inventario::class)
            ->call('setTab', 'periodicidad_mantenimiento')
            ->call('create')
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->set('form.meses_sugeridos', 6)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('periodicidades_mantenimiento', [
            'tipo_equipo_id' => $tipoEquipo->id,
            'meses_sugeridos' => 6,
        ]);
    }

    public function test_periodicidad_de_mantenimiento_rejects_a_second_rule_for_the_same_tipo_equipo(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        PeriodicidadMantenimiento::create(['tipo_equipo_id' => $tipoEquipo->id, 'meses_sugeridos' => 6]);

        Livewire::test(Inventario::class)
            ->call('setTab', 'periodicidad_mantenimiento')
            ->call('create')
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->set('form.meses_sugeridos', 12)
            ->call('save')
            ->assertHasErrors(['form.tipo_equipo_id']);
    }

    public function test_periodicidad_de_mantenimiento_can_edit_without_tripping_on_its_own_tipo_equipo(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $periodicidad = PeriodicidadMantenimiento::create(['tipo_equipo_id' => $tipoEquipo->id, 'meses_sugeridos' => 6]);

        Livewire::test(Inventario::class)
            ->call('setTab', 'periodicidad_mantenimiento')
            ->call('edit', $periodicidad->id)
            ->set('form.meses_sugeridos', 12)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('periodicidades_mantenimiento', [
            'id' => $periodicidad->id,
            'meses_sugeridos' => 12,
        ]);
    }

    public function test_can_create_a_stock_minimo(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $ubicacion = Ubicacion::create(['nombre' => 'Guanajuato']);

        Livewire::test(Inventario::class)
            ->call('setTab', 'stock_minimo')
            ->call('create')
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('form.cantidad_minima', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks_minimos', [
            'tipo_equipo_id' => $tipoEquipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 3,
        ]);
    }

    public function test_stock_minimo_rejects_a_second_rule_for_the_same_tipo_equipo_and_ubicacion_pair(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $ubicacion = Ubicacion::create(['nombre' => 'Guanajuato']);
        \Modules\GestionTI\Models\StockMinimo::create([
            'tipo_equipo_id' => $tipoEquipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 3,
        ]);

        Livewire::test(Inventario::class)
            ->call('setTab', 'stock_minimo')
            ->call('create')
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('form.cantidad_minima', 5)
            ->call('save')
            ->assertHasErrors(['form.tipo_equipo_id']);
    }

    public function test_stock_minimo_allows_same_tipo_equipo_in_a_different_ubicacion(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $ubicacionUno = Ubicacion::create(['nombre' => 'Guanajuato']);
        $ubicacionDos = Ubicacion::create(['nombre' => 'CDMX']);
        \Modules\GestionTI\Models\StockMinimo::create([
            'tipo_equipo_id' => $tipoEquipo->id,
            'ubicacion_id' => $ubicacionUno->id,
            'cantidad_minima' => 3,
        ]);

        Livewire::test(Inventario::class)
            ->call('setTab', 'stock_minimo')
            ->call('create')
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->set('form.ubicacion_id', $ubicacionDos->id)
            ->set('form.cantidad_minima', 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stocks_minimos', [
            'tipo_equipo_id' => $tipoEquipo->id,
            'ubicacion_id' => $ubicacionDos->id,
            'cantidad_minima' => 5,
        ]);
    }

    public function test_can_toggle_activo_on_marca(): void
    {
        $this->actingAs($this->actingUser());

        $marca = Marca::create(['nombre' => 'HP']);

        Livewire::test(Inventario::class)
            ->call('setTab', 'marcas')
            ->call('toggleActivo', $marca->id);

        $this->assertFalse($marca->fresh()->activo);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-catalogos-inventario',
            'route_name' => 'gestionti.catalogos.inventario',
            'group_label' => 'Inventarios',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-catalogos-inventario.manage'));

        $screen = Screen::where('slug', 'gestionti-catalogos-inventario')->first();
        $this->assertNotNull($screen);
    }
}
