<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Nucleo;
use Modules\GestionTI\Models\Empresa;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NucleoTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Catálogos',
            'name' => 'Catálogos Núcleo',
            'slug' => 'gestionti-catalogos-nucleo',
            'route_name' => 'gestionti.catalogos.nucleo',
            'permission_name' => 'screens.gestionti-catalogos-nucleo.manage',
            'icon' => 'building-office',
            'order' => 10,
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

        $this->actingAs($user)->get('/catalogos/nucleo')->assertForbidden();
    }

    public function test_can_create_an_empresa(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Nucleo::class)
            ->call('create')
            ->set('form.nombre_comercial', 'Kosmos Demo')
            ->set('form.razon_social', 'Kosmos Demo S.A. de C.V.')
            ->set('form.rfc', 'KDE010101AAA')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empresas', [
            'nombre_comercial' => 'Kosmos Demo',
            'razon_social' => 'Kosmos Demo S.A. de C.V.',
        ]);
    }

    public function test_empresa_requires_nombre_comercial_and_razon_social(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Nucleo::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors(['form.nombre_comercial', 'form.razon_social']);
    }

    public function test_can_switch_tabs_and_create_a_centro_de_costo(): void
    {
        $this->actingAs($this->actingUser());

        $empresa = Empresa::create([
            'razon_social' => 'Kosmos Demo S.A. de C.V.',
            'nombre_comercial' => 'Kosmos Demo',
        ]);

        Livewire::test(Nucleo::class)
            ->call('setTab', 'centros_costo')
            ->assertSet('tab', 'centros_costo')
            ->call('create')
            ->set('form.codigo', 'CC-001')
            ->set('form.nombre', 'Centro Demo')
            ->set('form.empresa_id', $empresa->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('centros_costo', [
            'codigo' => 'CC-001',
            'empresa_id' => $empresa->id,
        ]);
    }

    public function test_can_toggle_activo(): void
    {
        $this->actingAs($this->actingUser());

        $empresa = Empresa::create([
            'razon_social' => 'Kosmos Demo S.A. de C.V.',
            'nombre_comercial' => 'Kosmos Demo',
        ]);

        Livewire::test(Nucleo::class)
            ->call('toggleActivo', $empresa->id);

        $this->assertFalse($empresa->fresh()->activo);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-catalogos-nucleo',
            'route_name' => 'gestionti.catalogos.nucleo',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-catalogos-nucleo.manage'));

        $screen = Screen::where('slug', 'gestionti-catalogos-nucleo')->first();
        $this->assertNotNull($screen);
    }
}
