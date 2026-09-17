<?php

namespace Modules\GestionTI\Tests\Feature\MesaServicio;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionTI\Models\EbsRequisition;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EbsRequisicionesExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Mesa de Servicio',
            'name' => 'SIC en EBS',
            'slug' => 'gestionti-ebs-requisiciones',
            'route_name' => 'gestionti.ebs-requisiciones.index',
            'permission_name' => 'screens.gestionti-ebs-requisiciones.manage',
            'icon' => 'arrow-path',
            'order' => 3,
        ]);

        $role = Role::findOrCreate('Mesa de Servicio', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_export_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/ebs-requisiciones/exportar')
            ->assertForbidden();
    }

    public function test_export_returns_an_xlsx_file(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create(['requisition_header_id' => 1, 'code' => '6489', 'description' => 'Equipo Laptop']);

        $response = $this->get('/ebs-requisiciones/exportar');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_respects_the_broadened_search_filter(): void
    {
        $this->actingAs($this->actingUser());

        $porNota = EbsRequisition::create(['requisition_header_id' => 1, 'code' => '1111', 'description' => 'Sin relación']);
        $porNota->notes()->create(['clave' => 'Comentario', 'valor' => 'Contiene la palabra clave especial']);

        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '2222', 'description' => 'Otra cosa']);

        $response = $this->get('/ebs-requisiciones/exportar?codigo=clave especial');
        $response->assertOk();

        $tempFile = tempnam(sys_get_temp_dir(), 'gestionti-ebs-export-').'.xlsx';
        file_put_contents($tempFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        @unlink($tempFile);

        // Encabezado + 1 sola fila: la que matchea por nota, no por código.
        $this->assertCount(2, $rows);
        $this->assertSame('1111', $rows[1][0]);
    }

    public function test_export_respects_the_fecha_aprobada_filter(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => 'AP-DENTRO',
            'approver_date' => '2026-09-05',
        ]);
        EbsRequisition::create([
            'requisition_header_id' => 2,
            'code' => 'AP-FUERA',
            'approver_date' => '2026-09-20',
        ]);

        $response = $this->get('/ebs-requisiciones/exportar?aprobada_desde=2026-09-01&aprobada_hasta=2026-09-10');
        $response->assertOk();

        $tempFile = tempnam(sys_get_temp_dir(), 'gestionti-ebs-export-').'.xlsx';
        file_put_contents($tempFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        @unlink($tempFile);

        $this->assertCount(2, $rows);
        $this->assertSame('AP-DENTRO', $rows[1][0]);
    }
}
