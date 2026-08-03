<?php

namespace Tests\Feature\Modules;

use App\Livewire\Modules\Manage;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Módulos',
            'slug' => 'modules',
            'route_name' => 'modules.index',
            'permission_name' => 'screens.modules.manage',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_the_example_module_is_listed(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->assertSee('Ejemplo');
    }

    public function test_an_admin_can_disable_and_re_enable_a_module(): void
    {
        $admin = $this->actingAdmin();

        $this->assertTrue(Module::find('Ejemplo')->isEnabled());

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('toggle', 'Ejemplo');

        $this->assertTrue(Module::find('Ejemplo')->isDisabled());

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('toggle', 'Ejemplo');

        $this->assertTrue(Module::find('Ejemplo')->isEnabled());
    }
}
