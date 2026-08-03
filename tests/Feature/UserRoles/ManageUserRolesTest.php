<?php

namespace Tests\Feature\UserRoles;

use App\Livewire\UserRoles\Manage;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageUserRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Perfiles por usuario',
            'slug' => 'user-roles',
            'route_name' => 'user-roles.index',
            'permission_name' => 'screens.user-roles.manage',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_an_admin_can_assign_a_role_to_another_user(): void
    {
        $admin = $this->actingAdmin();
        $editorRole = Role::findOrCreate('Editor', 'web');
        $target = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('selectUser', $target->id)
            ->set('selectedRoles', ['Editor'])
            ->call('saveRoles');

        $this->assertTrue($target->fresh()->hasRole('Editor'));
        $this->assertFalse($target->fresh()->hasRole('Administrador'));
    }

    public function test_the_administrador_role_cannot_be_stripped_from_an_admin_via_the_component(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('selectUser', $admin->id)
            ->set('selectedRoles', [])
            ->call('saveRoles');

        $this->assertTrue($admin->fresh()->hasRole('Administrador'));
    }
}
