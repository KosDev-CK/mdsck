<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionTI\Models\TipoEquipo;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventarioExportTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Catálogos de Inventario',
            'slug' => 'gestionti-catalogos-inventario',
            'route_name' => 'gestionti.catalogos.inventario',
            'permission_name' => 'screens.gestionti-catalogos-inventario.manage',
            'icon' => 'archive-box',
            'order' => 30,
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
            ->get('/catalogos/inventario/exportar?tab=tipo_equipo')
            ->assertForbidden();
    }

    public function test_export_returns_an_xlsx_file_for_tipo_equipo(): void
    {
        $this->actingAs($this->actingUser());

        TipoEquipo::create(['nombre' => 'Laptop']);

        $response = $this->get('/catalogos/inventario/exportar?tab=tipo_equipo');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_returns_an_xlsx_file_for_a_rule_tab_without_search(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->get('/catalogos/inventario/exportar?tab=periodicidad_mantenimiento');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_returns_404_for_an_unknown_tab(): void
    {
        $this->actingAs($this->actingUser());

        $this->get('/catalogos/inventario/exportar?tab=no-existe')->assertNotFound();
    }
}
