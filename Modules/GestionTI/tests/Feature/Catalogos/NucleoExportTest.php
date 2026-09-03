<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionTI\Models\Empresa;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NucleoExportTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Catálogos',
            'name' => 'Catálogos Núcleo',
            'slug' => 'gestionti-catalogos-nucleo',
            'route_name' => 'gestionti.catalogos.nucleo',
            'permission_name' => 'screens.gestionti-catalogos-nucleo.manage',
            'icon' => 'building-office',
            'order' => 10,
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
            ->get('/catalogos/nucleo/exportar?tab=empresas')
            ->assertForbidden();
    }

    public function test_export_returns_an_xlsx_file(): void
    {
        $this->actingAs($this->actingUser());

        Empresa::create(['razon_social' => 'Kosmos Demo S.A. de C.V.', 'nombre_comercial' => 'Kosmos Demo']);

        $response = $this->get('/catalogos/nucleo/exportar?tab=empresas');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_exported_rows_match_the_database(): void
    {
        $this->actingAs($this->actingUser());

        Empresa::create(['razon_social' => 'Kosmos Demo S.A. de C.V.', 'nombre_comercial' => 'Kosmos Demo', 'rfc' => 'KDE010101AAA']);
        Empresa::create(['razon_social' => 'Grupo Profesional S.C.', 'nombre_comercial' => 'Grupo Profesional']);

        $response = $this->get('/catalogos/nucleo/exportar?tab=empresas');
        $response->assertOk();

        $tempFile = tempnam(sys_get_temp_dir(), 'gestionti-export-').'.xlsx';
        file_put_contents($tempFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        @unlink($tempFile);

        $this->assertSame(['Nombre comercial', 'Razón social', 'RFC', 'Estatus'], $rows[0]);
        $this->assertContains(['Kosmos Demo', 'Kosmos Demo S.A. de C.V.', 'KDE010101AAA', 'Activo'], $rows);
        // Una celda con "" nunca se escribe en el XML del .xlsx (se omite
        // por completo) — al releerla, `toArray()` la resuelve como `null`
        // en vez de cadena vacía; comportamiento normal de PhpSpreadsheet,
        // no un bug de este export.
        $this->assertContains(['Grupo Profesional', 'Grupo Profesional S.C.', null, 'Activo'], $rows);
    }

    public function test_export_respects_the_active_tab_and_search_filter(): void
    {
        $this->actingAs($this->actingUser());

        Empresa::create(['razon_social' => 'Kosmos Demo S.A. de C.V.', 'nombre_comercial' => 'Kosmos Demo']);
        Empresa::create(['razon_social' => 'Otra Empresa S.A. de C.V.', 'nombre_comercial' => 'Otra Empresa']);

        $response = $this->get('/catalogos/nucleo/exportar?tab=empresas&search=Kosmos');
        $response->assertOk();

        $tempFile = tempnam(sys_get_temp_dir(), 'gestionti-export-').'.xlsx';
        file_put_contents($tempFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        @unlink($tempFile);

        // Encabezado + 1 sola fila que matchea el filtro de búsqueda.
        $this->assertCount(2, $rows);
        $this->assertSame('Kosmos Demo', $rows[1][0]);
    }

    public function test_export_returns_404_for_an_unknown_tab(): void
    {
        $this->actingAs($this->actingUser());

        $this->get('/catalogos/nucleo/exportar?tab=no-existe')->assertNotFound();
    }
}
