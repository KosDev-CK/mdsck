<?php

namespace Modules\MesaServicio\Tests\Feature\Dashboards;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Dashboards\Ejecutivo;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EjecutivoTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Dashboards',
            'name' => 'Dashboard Ejecutivo',
            'slug' => 'mesaservicio-dashboard-ejecutivo',
            'route_name' => 'mesaservicio.dashboards.ejecutivo',
            'permission_name' => 'screens.mesaservicio-dashboard-ejecutivo.manage',
            'icon' => 'presentation-chart-line',
            'order' => 7,
        ]);

        $role = Role::findOrCreate('Rol Dashboard Ejecutivo test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/dashboards/ejecutivo')->assertForbidden();
    }

    public function test_a_user_with_the_permission_can_see_the_screen(): void
    {
        $this->actingAs($this->actingUser());

        $this->get('/mesa-servicio/dashboards/ejecutivo')->assertOk();

        Livewire::test(Ejecutivo::class)->assertOk();
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'dashboard-ejecutivo'), escape: false);
    }
}
