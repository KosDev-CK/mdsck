<?php

namespace Tests\Feature\Connections;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ButtonDomPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_nueva_conexion_button_is_inside_the_wire_id_rooted_component(): void
    {
        $screen = Screen::create([
            'name' => 'Conexiones a BD',
            'slug' => 'connections',
            'route_name' => 'connections.index',
            'permission_name' => 'screens.connections.manage',
            'icon' => 'circle-stack',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->get('/connections');
        $response->assertStatus(200);
        $html = $response->getContent();

        $wireIdPos = strpos($html, 'wire:id=');
        $buttonPos = strpos($html, 'wire:click="create"');

        $this->assertNotFalse($wireIdPos, 'Expected the Livewire component root to have a wire:id attribute.');
        $this->assertNotFalse($buttonPos, 'Expected the "Nueva conexión" button to be present in the response.');
        $this->assertGreaterThan(
            $wireIdPos,
            $buttonPos,
            'The "Nueva conexión" button must render after the component\'s wire:id attribute, i.e. inside the Livewire-managed root, or wire:click will never fire.'
        );
    }
}
