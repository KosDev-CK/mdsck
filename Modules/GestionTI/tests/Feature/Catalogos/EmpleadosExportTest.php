<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionTI\Models\Empleado;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmpleadosExportTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Catálogos',
            'name' => 'Empleados',
            'slug' => 'gestionti-catalogos-empleados',
            'route_name' => 'gestionti.catalogos.empleados',
            'permission_name' => 'screens.gestionti-catalogos-empleados.manage',
            'icon' => 'users',
            'order' => 11,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_export_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/catalogos/empleados/exportar')
            ->assertForbidden();
    }

    public function test_export_returns_an_xlsx_file(): void
    {
        $this->actingAs($this->actingUser());

        Empleado::create(['numero_empleado' => 'E-001', 'nombre' => 'Ana López']);

        $response = $this->get('/catalogos/empleados/exportar');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
