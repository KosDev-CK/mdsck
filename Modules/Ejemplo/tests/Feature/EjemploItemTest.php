<?php

namespace Modules\Ejemplo\Tests\Feature;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Ejemplo\Livewire\Index;
use Modules\Ejemplo\Models\EjemploItem;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EjemploItemTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAuthorizedUser(): User
    {
        $screen = Screen::create([
            'module' => 'Ejemplo',
            'name' => 'Ejemplo',
            'slug' => 'ejemplo',
            'route_name' => 'ejemplo.index',
            'permission_name' => 'screens.ejemplo.manage',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Colaborador', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_only_users_with_the_module_permission_can_reach_it(): void
    {
        $this->actingAuthorizedUser();

        $this->get(route('ejemplo.index'))->assertRedirect(route('login'));

        $unauthorized = User::factory()->create(['is_active' => true]);
        $this->actingAs($unauthorized)->get(route('ejemplo.index'))->assertForbidden();
    }

    public function test_an_authorized_user_can_create_and_delete_items(): void
    {
        $user = $this->actingAuthorizedUser();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('title', 'Primer registro')
            ->set('description', 'Descripción de prueba')
            ->call('create')
            ->assertHasNoErrors();

        $item = EjemploItem::where('title', 'Primer registro')->first();
        $this->assertNotNull($item);
        $this->assertSame($user->id, $item->created_by);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('delete', $item->id);

        $this->assertDatabaseMissing('ejemplo_items', ['id' => $item->id]);
    }
}
