<?php

namespace Modules\MesaServicio\Tests\Feature\Dashboards;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Dashboards\Operacion;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperacionTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Dashboards',
            'name' => 'Dashboard de Operación',
            'slug' => 'mesaservicio-dashboard-operacion',
            'route_name' => 'mesaservicio.dashboards.operacion',
            'permission_name' => 'screens.mesaservicio-dashboard-operacion.manage',
            'icon' => 'wrench-screwdriver',
            'order' => 9,
        ]);

        $role = Role::findOrCreate('Rol Dashboard Operacion test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/dashboards/operacion')->assertForbidden();
    }

    public function test_a_user_with_the_permission_can_see_the_screen(): void
    {
        $this->actingAs($this->actingUser());

        $this->get('/mesa-servicio/dashboards/operacion')->assertOk();

        Livewire::test(Operacion::class)->assertOk();
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Operacion::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'dashboard-operacion'), escape: false);
    }
}
