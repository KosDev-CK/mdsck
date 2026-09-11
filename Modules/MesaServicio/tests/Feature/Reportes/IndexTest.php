<?php

namespace Modules\MesaServicio\Tests\Feature\Reportes;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Reportes\Index;
use Modules\MesaServicio\Models\SdpReport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Reportes',
            'slug' => 'mesaservicio-reportes',
            'route_name' => 'mesaservicio.reportes.index',
            'permission_name' => 'screens.mesaservicio-reportes.manage',
            'icon' => 'document-arrow-down',
            'order' => 3,
        ]);

        $role = Role::findOrCreate('Reportes Mesa de Servicio Test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function reporte(array $overrides = []): SdpReport
    {
        return SdpReport::create(array_replace([
            'tipo' => SdpReport::TIPO_DIARIO,
            'periodo' => today()->subDay()->toDateString(),
            'ruta_archivo' => 'mesa-servicio/reportes/diario/'.today()->subDay()->toDateString().'.xlsx',
            'resumen_metricas' => ['total' => 5, 'por_estado' => [], 'por_tecnico' => []],
            'generado_en' => now(),
        ], $overrides));
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/reportes')->assertForbidden();
    }

    public function test_it_lists_reports_ordered_by_period_descending(): void
    {
        $viejo = $this->reporte(['periodo' => today()->subDays(3)->toDateString()]);
        $reciente = $this->reporte(['periodo' => today()->subDay()->toDateString()]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Index::class);

        $reports = $component->viewData('reports');
        $this->assertSame([$reciente->id, $viejo->id], $reports->pluck('id')->all());
    }

    public function test_it_downloads_the_stored_excel_file(): void
    {
        Storage::fake('local');

        $report = $this->reporte();
        Storage::disk('local')->put($report->ruta_archivo, 'contenido-de-prueba');

        $this->actingAs($this->actingUser());

        Livewire::test(Index::class)
            ->call('download', $report->id)
            ->assertFileDownloaded($report->downloadFilename());
    }

    public function test_downloading_a_report_whose_file_is_missing_aborts_with_404(): void
    {
        Storage::fake('local');

        $report = $this->reporte();

        $this->actingAs($this->actingUser());

        Livewire::test(Index::class)
            ->call('download', $report->id)
            ->assertStatus(404);
    }
}
