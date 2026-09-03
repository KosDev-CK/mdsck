<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionTI\Models\Proveedor;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComprasExportTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Compras',
            'name' => 'Catálogos de Compras',
            'slug' => 'gestionti-catalogos-compras',
            'route_name' => 'gestionti.catalogos.compras',
            'permission_name' => 'screens.gestionti-catalogos-compras.manage',
            'icon' => 'truck',
            'order' => 20,
        ]);

        $role = Role::findOrCreate('Compras', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_export_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/catalogos/compras/exportar?tab=proveedores')
            ->assertForbidden();
    }

    public function test_export_returns_an_xlsx_file_for_proveedores(): void
    {
        $this->actingAs($this->actingUser());

        Proveedor::create(['razon_social' => 'ProveeTI S.A. de C.V.', 'nombre_comercial' => 'ProveeTI']);

        $response = $this->get('/catalogos/compras/exportar?tab=proveedores');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_returns_an_xlsx_file_for_articulos_solicitud(): void
    {
        $this->actingAs($this->actingUser());

        $response = $this->get('/catalogos/compras/exportar?tab=articulos_solicitud');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_returns_404_for_an_unknown_tab(): void
    {
        $this->actingAs($this->actingUser());

        $this->get('/catalogos/compras/exportar?tab=no-existe')->assertNotFound();
    }
}
