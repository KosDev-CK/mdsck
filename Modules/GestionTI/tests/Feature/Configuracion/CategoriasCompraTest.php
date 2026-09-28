<?php

namespace Modules\GestionTI\Tests\Feature\Configuracion;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Configuracion\CategoriasCompra;
use Modules\GestionTI\Models\ConfiguracionCategorias;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoriasCompraTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'General',
            'name' => 'Categorías que van a Compra',
            'slug' => 'gestionti-categorias-compra',
            'route_name' => 'gestionti.categorias-compra.index',
            'permission_name' => 'screens.gestionti-categorias-compra.manage',
            'icon' => 'tag',
            'order' => 6,
        ]);

        $role = Role::findOrCreate('Administrador de TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/categorias-compra')->assertForbidden();
    }

    public function test_seeding_creates_the_default_configuration(): void
    {
        ConfiguracionCategorias::current();

        $this->assertDatabaseHas('configuracion_categorias', ['id' => 1]);
        // Default deliberadamente vacío — ver el docblock de
        // `ConfiguracionCategorias::DEFAULTS`: no se asume ninguna división
        // de categorías hasta que el usuario configure la pantalla.
        $this->assertSame(
            [],
            ConfiguracionCategorias::current()->categorias_compra
        );
    }

    public function test_mount_preloads_the_current_configuration(): void
    {
        $this->actingAs($this->actingUser());
        ConfiguracionCategorias::current()->update(['categorias_compra' => ['laptops_desktops', 'multifuncionales']]);

        Livewire::test(CategoriasCompra::class)
            ->assertSet('categoriasCompra', ['laptops_desktops', 'multifuncionales']);
    }

    public function test_save_updates_the_singleton(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(CategoriasCompra::class)
            ->set('categoriasCompra', ['laptops_desktops', 'redes'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['laptops_desktops', 'redes'], ConfiguracionCategorias::current()->fresh()->categorias_compra);
    }

    public function test_save_rejects_a_value_outside_the_11_known_categories(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(CategoriasCompra::class)
            ->set('categoriasCompra', ['no-es-una-categoria-valida'])
            ->call('save')
            ->assertHasErrors(['categoriasCompra.0']);
    }

    public function test_save_allows_unchecking_everything_back_to_empty(): void
    {
        $this->actingAs($this->actingUser());
        ConfiguracionCategorias::current()->update(['categorias_compra' => ['laptops_desktops']]);

        Livewire::test(CategoriasCompra::class)
            ->set('categoriasCompra', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([], ConfiguracionCategorias::current()->fresh()->categorias_compra);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-categorias-compra',
            'route_name' => 'gestionti.categorias-compra.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-categorias-compra.manage'));
    }
}
