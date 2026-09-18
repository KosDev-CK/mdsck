<?php

namespace Modules\MesaServicio\Tests\Feature\Dashboards;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Dashboards\Analitico;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnaliticoTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Dashboards',
            'name' => 'Dashboard Analítico',
            'slug' => 'mesaservicio-dashboard-analitico',
            'route_name' => 'mesaservicio.dashboards.analitico',
            'permission_name' => 'screens.mesaservicio-dashboard-analitico.manage',
            'icon' => 'chart-pie',
            'order' => 8,
        ]);

        $role = Role::findOrCreate('Rol Dashboard Analitico test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/dashboards/analitico')->assertForbidden();
    }

    public function test_a_user_with_the_permission_can_see_the_screen(): void
    {
        $this->actingAs($this->actingUser());

        $this->get('/mesa-servicio/dashboards/analitico')->assertOk();

        Livewire::test(Analitico::class)->assertOk();
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Analitico::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'dashboard-analitico'), escape: false);
    }
}
