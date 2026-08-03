<?php

namespace Tests\Feature\Roles;

use App\Livewire\Roles\Manage;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Perfiles',
            'slug' => 'roles',
            'route_name' => 'roles.index',
            'permission_name' => 'screens.roles.manage',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Administrador', 'web');
        $role->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    public function test_guests_and_unauthorized_users_cannot_reach_the_screen(): void
    {
        $this->actingAdmin();

        $this->get(route('roles.index'))->assertRedirect(route('login'));

        $plainUser = User::factory()->create(['is_active' => true]);
        $this->actingAs($plainUser)->get(route('roles.index'))->assertForbidden();
    }

    public function test_an_admin_can_create_a_role_and_assign_screens(): void
    {
        $admin = $this->actingAdmin();
        $screen = Screen::first();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->set('newRoleName', 'Supervisor')
            ->call('createRole')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'Supervisor']);

        $role = Role::where('name', 'Supervisor')->first();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('selectRole', $role->id)
            ->set('selectedPermissions', [$screen->permission_name])
            ->call('savePermissions');

        $this->assertTrue($role->fresh()->hasPermissionTo($screen->permission_name));
    }

    public function test_the_administrador_role_cannot_be_deleted(): void
    {
        $admin = $this->actingAdmin();
        $adminRole = Role::where('name', 'Administrador')->first();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('deleteRole', $adminRole->id);

        $this->assertDatabaseHas('roles', ['name' => 'Administrador']);
    }
}
