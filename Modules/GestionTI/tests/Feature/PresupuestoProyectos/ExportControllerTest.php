<?php

namespace Modules\GestionTI\Tests\Feature\PresupuestoProyectos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El export reconstruye la estructura del Excel corporativo real (ver
 * docs/gestionti-progreso.md) — secciones por categoría contable con
 * subtotal cada una, un subtotal general, y un bloque final con
 * `factor_administrativo` aplicado. Mismo patrón de lectura de xlsx ya
 * usado en `Modules/GestionTI/tests/Feature/Catalogos/NucleoExportTest.php`
 * (guardar `streamedContent()` en un archivo temporal y releerlo con
 * `IOFactory::load()`).
 */
class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Presupuesto de Proyectos',
            'name' => 'Presupuesto por Proyecto',
            'slug' => 'gestionti-presupuestos-proyecto',
            'route_name' => 'gestionti.presupuestos-proyecto.index',
            'permission_name' => 'screens.gestionti-presupuestos-proyecto.manage',
            'icon' => 'banknotes',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Compras', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function proyecto(array $overrides = []): ProyectoPresupuesto
    {
        $empresa = Empresa::create(['razon_social' => 'Kosmos S.A. de C.V.', 'nombre_comercial' => 'Kosmos']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $area = Area::create(['nombre' => 'Operaciones']);
        $pm = Empleado::create(['numero_empleado' => 'EMP-PM', 'nombre' => 'PM Responsable']);

        return ProyectoPresupuesto::create(array_merge([
            'nombre_proyecto' => 'Proyecto Export Test',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => $centroCosto->id,
            'direccion_centro' => 'Av. Siempre Viva 123',
            'area_operativa_solicitante_id' => $area->id,
            'pm_responsable_id' => $pm->id,
            'fecha_solicitud' => '2026-09-01',
            'fecha_limite_captura' => '2026-09-15',
        ], $overrides));
    }

    private function articulo(ProyectoPresupuesto $proyecto, array $overrides = [])
    {
        $responsable = Empleado::create(['numero_empleado' => 'EMP-R-'.random_int(1000, 9999), 'nombre' => 'Responsable']);

        return $proyecto->articulos()->create(array_merge([
            'categoria' => 'laptops_desktops',
            'categoria_contable' => 'infraestructura',
            'descripcion' => 'Artículo de prueba',
            'cantidad' => 1,
            'responsable_costo_id' => $responsable->id,
            'costo_unitario' => 100,
            'no_meses' => 1,
            'cashflow_tipo' => 'one_time',
            'estatus_captura' => 'capturado',
            'fecha_captura' => now()->format('Y-m-d'),
        ], $overrides));
    }

    /** @return array<int, array<int, mixed>> */
    private function readRows($response): array
    {
        $response->assertOk();

        $tempFile = tempnam(sys_get_temp_dir(), 'gestionti-presupuesto-export-').'.xlsx';
        file_put_contents($tempFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);
        // `$formatData = false` — de lo contrario las celdas con formato de
        // moneda ('#,##0.00') vuelven como texto formateado ("2,000.00") en
        // vez del valor numérico real.
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false);

        @unlink($tempFile);

        return $rows;
    }

    private function findRow(array $rows, string $needleInColumnA): ?array
    {
        foreach ($rows as $row) {
            if (is_string($row[0] ?? null) && str_contains($row[0], $needleInColumnA)) {
                return $row;
            }
        }

        return null;
    }

    public function test_export_route_requires_the_screen_permission(): void
    {
        $proyecto = $this->proyecto();
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get("/presupuestos-proyecto/{$proyecto->id}/exportar")
            ->assertForbidden();
    }

    public function test_export_returns_an_xlsx_file(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto();

        $response = $this->get("/presupuestos-proyecto/{$proyecto->id}/exportar");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * 2 categorías contables distintas, cada una con su subtotal — confirma
     * que la fórmula real del Excel (`Cantidad × Precio unitario × No.
     * Meses`, puesta en "One Time" o "On going" según el CashFlow) se
     * replica correctamente, y que las secciones aparecen en el orden fijo.
     */
    public function test_export_groups_articulos_by_categoria_contable_with_subtotals(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['factor_administrativo' => '1.1000']);

        // Infraestructura: 2000 one_time + 14400 on_going = 16400 total.
        $this->articulo($proyecto, [
            'categoria_contable' => 'infraestructura',
            'descripcion' => 'Laptop',
            'cantidad' => 2,
            'costo_unitario' => 1000,
            'no_meses' => 1,
            'cashflow_tipo' => 'one_time',
        ]);
        $this->articulo($proyecto, [
            'categoria_contable' => 'infraestructura',
            'descripcion' => 'Internet dedicado',
            'cantidad' => 1,
            'costo_unitario' => 1200,
            'no_meses' => 12,
            'cashflow_tipo' => 'on_going',
        ]);

        // Telco: 1500 one_time.
        $this->articulo($proyecto, [
            'categoria_contable' => 'telco',
            'descripcion' => 'Plan celular',
            'cantidad' => 3,
            'costo_unitario' => 500,
            'no_meses' => 1,
            'cashflow_tipo' => 'one_time',
        ]);

        $response = $this->get("/presupuestos-proyecto/{$proyecto->id}/exportar");
        $rows = $this->readRows($response);

        // Orden fijo de secciones — Infraestructura (2ª categoría de la
        // lista) antes que Telco (3ª), sin importar el orden de creación.
        $indiceInfraestructura = null;
        $indiceTelco = null;
        foreach ($rows as $i => $row) {
            if (is_string($row[0] ?? null) && str_contains($row[0], 'INFRAESTRUCTURA')) {
                $indiceInfraestructura = $i;
            }
            if (is_string($row[0] ?? null) && str_contains($row[0], 'TELCO')) {
                $indiceTelco = $i;
            }
        }
        $this->assertNotNull($indiceInfraestructura);
        $this->assertNotNull($indiceTelco);
        $this->assertLessThan($indiceTelco, $indiceInfraestructura);

        $subtotalInfraestructura = $this->findRow($rows, 'Subtotal Infraestructura');
        $this->assertNotNull($subtotalInfraestructura);
        // Columnas J/K/L (índices 9/10/11) = One Time/On going/Total.
        $this->assertEquals(2000, $subtotalInfraestructura[9]);
        $this->assertEquals(14400, $subtotalInfraestructura[10]);
        $this->assertEquals(16400, $subtotalInfraestructura[11]);

        $subtotalTelco = $this->findRow($rows, 'Subtotal Telco');
        $this->assertNotNull($subtotalTelco);
        $this->assertEquals(1500, $subtotalTelco[9]);
        $this->assertEquals(0, $subtotalTelco[10]);
        $this->assertEquals(1500, $subtotalTelco[11]);
    }

    /**
     * Bloque final: One Time × factor, On going ÷ 12 × factor (convierte el
     * total anual "on going" a mensualidad), Total × factor — mismas 3
     * fórmulas del Excel real generalizadas con el factor capturado del
     * proyecto en vez de un 1.035 fijo.
     */
    public function test_export_applies_factor_administrativo_to_final_totals(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['factor_administrativo' => '1.1000']);

        $this->articulo($proyecto, [
            'categoria_contable' => 'infraestructura',
            'cantidad' => 2,
            'costo_unitario' => 1000,
            'no_meses' => 1,
            'cashflow_tipo' => 'one_time',
        ]);
        $this->articulo($proyecto, [
            'categoria_contable' => 'infraestructura',
            'cantidad' => 1,
            'costo_unitario' => 1200,
            'no_meses' => 12,
            'cashflow_tipo' => 'on_going',
        ]);
        $this->articulo($proyecto, [
            'categoria_contable' => 'telco',
            'cantidad' => 3,
            'costo_unitario' => 500,
            'no_meses' => 1,
            'cashflow_tipo' => 'one_time',
        ]);

        $response = $this->get("/presupuestos-proyecto/{$proyecto->id}/exportar");
        $rows = $this->readRows($response);

        $subtotales = $this->findRow($rows, 'Subtotales');
        $this->assertNotNull($subtotales);
        $this->assertEquals(3500, $subtotales[9]);
        $this->assertEquals(14400, $subtotales[10]);
        $this->assertEquals(17900, $subtotales[11]);

        $totalConFactor = $this->findRow($rows, 'Total con factor administrativo');
        $this->assertNotNull($totalConFactor);
        $this->assertEqualsWithDelta(3850, $totalConFactor[9], 0.01); // 3500 * 1.1
        $this->assertEqualsWithDelta(1320, $totalConFactor[10], 0.01); // 14400 / 12 * 1.1
        $this->assertEqualsWithDelta(19690, $totalConFactor[11], 0.01); // 17900 * 1.1
    }

    public function test_export_places_articulo_without_costo_unitario_as_zero_without_error(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto();

        $this->articulo($proyecto, [
            'costo_unitario' => null,
            'estatus_captura' => 'pendiente',
            'fecha_captura' => null,
            'cashflow_tipo' => null,
        ]);

        $response = $this->get("/presupuestos-proyecto/{$proyecto->id}/exportar");

        $response->assertOk();
    }
}
