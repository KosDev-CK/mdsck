<?php

namespace Modules\GestionTI\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\AssetCompliance;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Puesto;
use Modules\GestionTI\Models\TipoEquipo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Cubre el comando `gestionti:importar-historico` contra un fixture sintético
 * pequeño (no el Excel histórico real de 3799 filas, deliberadamente — ver
 * docs/gestionti-progreso.md). El fixture cubre: una fila limpia, un Tipo
 * fuera de alcance, un jefe inmediato ambiguo, un costo "#N/A", una
 * Adquisición no numérica, un empleado que aparece en 2 filas, una fila sin
 * número de empleado, una fila sin Tipo, y un Modelo sin Marca.
 */
class ImportarHistoricoCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    private array $reportPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = $this->buildFixtureWorkbook();
    }

    protected function tearDown(): void
    {
        if (is_file($this->fixturePath)) {
            unlink($this->fixturePath);
        }

        foreach ($this->reportPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function reportPath(string $suffix): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gestionti_reporte_'.$suffix.'_'.uniqid().'.xlsx';
        $this->reportPaths[] = $path;

        return $path;
    }

    public function test_missing_file_fails_gracefully(): void
    {
        $exitCode = Artisan::call('gestionti:importar-historico', [
            '--path' => sys_get_temp_dir().'/no-existe-'.uniqid().'.xlsx',
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function test_fails_clearly_when_base_estatus_catalog_is_not_seeded(): void
    {
        // Deliberadamente NO se corre `module:seed GestionTI` en este test —
        // sin los 5 códigos base de EstatusActivo el comando debe abortar con
        // un mensaje claro en vez de tronar a mitad de la importación.
        $reportPath = $this->reportPath('sin-seed');

        $exitCode = Artisan::call('gestionti:importar-historico', [
            '--path' => $this->fixturePath,
            '--dry-run' => true,
            '--report' => $reportPath,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('estatus base', Artisan::output());
        $this->assertFileDoesNotExist($reportPath);
    }

    public function test_dry_run_rolls_back_and_still_produces_reconciliation_report(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $reportPath = $this->reportPath('dry-run');

        $exitCode = Artisan::call('gestionti:importar-historico', [
            '--path' => $this->fixturePath,
            '--dry-run' => true,
            '--report' => $reportPath,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('DRY-RUN completo', Artisan::output());

        // Nada debe persistir tras el rollback.
        $this->assertSame(0, Empleado::count());
        $this->assertSame(0, Asset::count());
        $this->assertSame(0, AssetCompliance::count());
        $this->assertSame(0, AssetAssignment::count());
        $this->assertSame(0, Puesto::count());

        $this->assertFileExists($reportPath);

        $spreadsheet = IOFactory::load($reportPath);
        $this->assertNotFalse($spreadsheet->getSheetByName('Resumen'));

        $jerarquia = $spreadsheet->getSheetByName('Jerarquia no resuelta');
        $this->assertNotFalse($jerarquia);
        $found = false;
        for ($row = 2; $row <= $jerarquia->getHighestRow(); $row++) {
            if ((string) $jerarquia->getCell('A'.$row)->getValue() === '1003'
                && $jerarquia->getCell('E'.$row)->getValue() === 'ambiguo') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Se esperaba una fila ambigua para el empleado 1003 (jefe "Pedro Ruiz").');

        $estatus = $spreadsheet->getSheetByName('Estatus no mapeados');
        $this->assertGreaterThanOrEqual(2, $estatus->getHighestRow()); // encabezado + al menos 1 fila (Prestamo)

        $modelos = $spreadsheet->getSheetByName('Modelos omitidos');
        $this->assertGreaterThanOrEqual(2, $modelos->getHighestRow());

        $sinTipo = $spreadsheet->getSheetByName('Filas sin tipo equipo');
        $this->assertGreaterThanOrEqual(2, $sinTipo->getHighestRow());

        $sinEmpleado = $spreadsheet->getSheetByName('Filas sin empleado');
        $this->assertGreaterThanOrEqual(2, $sinEmpleado->getHighestRow());
    }

    public function test_real_run_persists_expected_records_and_resolves_hierarchy(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $reportPath = $this->reportPath('real-run');

        $exitCode = Artisan::call('gestionti:importar-historico', [
            '--path' => $this->fixturePath,
            '--report' => $reportPath,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Importación completada y confirmada', Artisan::output());

        // 9 números de empleado distintos entre las 10 filas con columna A no vacía
        // (1001 aparece 2 veces => 1 creado + 1 actualizado).
        $this->assertSame(9, Empleado::count());

        // 11 filas totales - 1 sin Tipo (FK requerida, no se puede crear el Asset) = 10.
        $this->assertSame(10, Asset::count());
        $this->assertSame(10, AssetCompliance::count());

        // De esos 10 Assets, 1 corresponde a la fila sin número de empleado.
        $this->assertSame(9, AssetAssignment::count());

        $juanPerez = Empleado::where('numero_empleado', '1001')->firstOrFail();
        $this->assertSame(2, $juanPerez->assetAssignments()->count());
        $this->assertSame('Juan Perez', $juanPerez->nombre);

        $mariaLopez = Empleado::where('numero_empleado', '1002')->firstOrFail();
        $this->assertSame($mariaLopez->id, $juanPerez->jefe_inmediato_id, 'El jefe de Juan Perez debía resolverse sin ambigüedad a Maria Lopez.');

        $lauraSanchez = Empleado::where('numero_empleado', '1003')->firstOrFail();
        $this->assertNull($lauraSanchez->jefe_inmediato_id, 'El jefe "Pedro Ruiz" es ambiguo (2 empleados con ese nombre) y no debe resolverse.');

        // Homologación de Tipo de Equipo.
        $laptop = TipoEquipo::where('nombre', 'Laptop')->firstOrFail();
        $this->assertTrue((bool) $laptop->en_alcance);

        $desktop = TipoEquipo::where('nombre', 'PC de Escritorio')->firstOrFail();
        $this->assertTrue((bool) $desktop->en_alcance);

        $monitor = TipoEquipo::where('nombre', 'Monitor')->firstOrFail();
        $this->assertTrue((bool) $monitor->en_alcance);

        $bascula = TipoEquipo::where('nombre', 'Báscula')->firstOrFail();
        $this->assertFalse((bool) $bascula->en_alcance);

        // Códigos KOS-<SLUG>-###### secuenciales por tipo.
        $this->assertDatabaseHas('assets', ['codigo' => 'KOS-LAPTOP-000001']);
        $this->assertDatabaseHas('assets', ['codigo' => 'KOS-DESKTOP-000001']);
        $this->assertDatabaseHas('assets', ['codigo' => 'KOS-MONITOR-000001']);
        $this->assertDatabaseHas('assets', ['codigo' => 'KOS-BASCULA-000001']);

        // Costo "#N/A" (fila de Carlos Vega, serie SN006) se guarda como null, no truena.
        $costoNulo = Asset::where('numero_serie', 'SN006')->firstOrFail();
        $this->assertNull($costoNulo->costo_adquisicion);

        // Adquisición "En validación" (fila de Sofia Torres, serie SN007) no es fecha válida -> null.
        $fechaInvalida = Asset::where('numero_serie', 'SN007')->firstOrFail();
        $this->assertNull($fechaInvalida->fecha_alta_stock);

        // Estatus "Prestamo" no está en la homologación -> fallback a en_stock.
        $this->assertSame('en_stock', $fechaInvalida->estatus->codigo);

        // Modelo sin Marca (Elena Castro, "Latitude X1") se omite, no se crea.
        $this->assertFalse(Modelo::where('nombre', 'Latitude X1')->exists());
        $elenaAsset = Asset::where('numero_serie', 'SN010')->firstOrFail();
        $this->assertNull($elenaAsset->marca_id);
        $this->assertNull($elenaAsset->modelo_id);

        // Fila sin Tipo (Diego Ramirez) no genera Asset, pero el Empleado sí existe.
        $this->assertTrue(Empleado::where('numero_empleado', '1006')->exists());
        $this->assertFalse(Asset::where('numero_serie', 'SN-SIN-TIPO')->exists());

        $this->assertFileExists($reportPath);
    }

    public function test_running_twice_does_not_crash_and_keeps_codigo_sequence_unique(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $firstReport = $this->reportPath('run-1');
        $secondReport = $this->reportPath('run-2');

        $first = Artisan::call('gestionti:importar-historico', [
            '--path' => $this->fixturePath,
            '--report' => $firstReport,
        ]);
        $this->assertSame(Command::SUCCESS, $first);

        $second = Artisan::call('gestionti:importar-historico', [
            '--path' => $this->fixturePath,
            '--report' => $secondReport,
        ]);
        $this->assertSame(Command::SUCCESS, $second);

        // Limitación documentada: una 2a corrida real duplica Assets/Empleados
        // (no hay clave natural en el Excel para deduplicar) pero no debe
        // reventar por códigos duplicados — la secuencia por tipo continúa
        // desde el máximo ya existente.
        $this->assertSame(20, Asset::count());
        $this->assertSame(20, Asset::distinct('codigo')->count('codigo'));
        $this->assertDatabaseHas('assets', ['codigo' => 'KOS-LAPTOP-000006']);
    }

    public function test_dry_run_against_real_historical_file_if_present(): void
    {
        $realPath = storage_path('app/imports/Inventario_20210622.xlsx');

        if (! is_file($realPath)) {
            $this->markTestSkipped('storage/app/imports/Inventario_20210622.xlsx no está presente en este entorno (gitignored) — se omite el smoke test contra el archivo real.');
        }

        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $reportPath = $this->reportPath('real-file-smoke');

        $exitCode = Artisan::call('gestionti:importar-historico', [
            '--path' => $realPath,
            '--dry-run' => true,
            '--report' => $reportPath,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($reportPath);

        // Solo smoke test — no se afirman conteos exactos contra datos reales,
        // solo que la transacción se revirtió correctamente.
        $this->assertSame(0, Asset::count());
        $this->assertSame(0, Empleado::count());
    }

    /**
     * Construye un .xlsx sintético con el mismo layout de columnas que el
     * Excel histórico real (hoja "Activos", encabezados en la fila 3, datos
     * desde la fila 4), cubriendo los escenarios descritos en la tarea.
     */
    private function buildFixtureWorkbook(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Activos');

        $headers = [
            'A' => 'Empleado', 'B' => 'Nombre Completo', 'C' => 'Correo', 'D' => 'Puesto',
            'F' => 'Area', 'G' => 'Jefe inmediato o Gerente', 'H' => 'Ubicación', 'J' => 'Unidad de Negocio',
            'K' => 'Nombre Completo de la empresa', 'L' => 'Empresa', 'N' => 'Director', 'O' => 'Director Ejecutivo',
            'P' => 'Tipo', 'Q' => 'Marca', 'R' => 'Modelo', 'S' => 'Serie', 'T' => 'Equipo en dominio',
            'U' => 'Usuario de dominio', 'V' => 'Hostname', 'W' => 'WIFI', 'X' => 'Ethernet', 'Y' => 'Procesador',
            'Z' => 'Generacion', 'AA' => 'Disco Duro', 'AB' => 'RAM', 'AC' => 'S.O.', 'AD' => 'Adquisición',
            'AF' => 'Nota Adquisicion', 'AG' => 'Fecha de Validación', 'AH' => 'Validado por', 'AJ' => 'Proveedor',
            'AK' => 'Propiedad', 'AL' => 'Costo Unitario', 'AM' => 'CrowdStrike', 'AN' => '1° Licencia',
            'AO' => '2° licencia', 'AP' => 'Estatus', 'AR' => 'Observaciones', 'AS' => 'Activación BitLocker',
            'AT' => 'Mtto. Preventivo',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col.'3', $label);
        }

        $common = [
            'D' => 'Analista', 'F' => 'TI', 'H' => 'CDMX', 'J' => 'Corporativo',
            'K' => 'Empresa Uno SA de CV', 'L' => 'Empresa Uno', 'AP' => 'Asignado',
        ];

        // Nota: el override de cada fila va PRIMERO en la unión de arrays
        // (`fila + $common`, no `$common + fila`) porque el operador `+` de
        // PHP conserva el valor del arreglo IZQUIERDO cuando una clave se
        // repite en ambos — si $common fuera primero, su 'AP' => 'Asignado'
        // ganaría siempre y silenciaría el override de Estatus de la fila 10.
        $rows = [
            // Fila limpia, "feliz".
            4 => [
                'A' => 1001, 'B' => 'Juan Perez', 'C' => 'juan@empresauno.mx',
                'G' => 'Maria Lopez',
                'P' => 'Portátil', 'Q' => 'Dell', 'R' => 'Latitude 5420', 'S' => 'SN001',
                'T' => 'SI', 'U' => 'jperez', 'V' => 'HOST001', 'W' => 'AA:BB:CC', 'X' => 'DD:EE:FF',
                'Y' => 'i5', 'Z' => '10', 'AA' => '256Gb SSD', 'AB' => '8Gb', 'AC' => 'W10',
                'AD' => 44382, 'AF' => 'Compra normal', 'AG' => 45000, 'AH' => 'ABC',
                'AJ' => 'Proveedor Uno', 'AK' => 'Landit', 'AL' => 15000, 'AM' => 'Si',
                'AN' => 'Office 365 E3', 'AS' => 'SI', 'AT' => 45100,
            ] + $common,
            // Tipo fuera de alcance (homologación "cualquier otro valor").
            5 => [
                'A' => 1002, 'B' => 'Maria Lopez',
                'P' => 'Báscula', 'Q' => 'Dibal', 'R' => 'BB-100', 'S' => 'SN002',
                'AC' => 'NA', 'AM' => 'No',
            ] + $common,
            // Candidato 1 de nombre ambiguo.
            6 => [
                'A' => 2001, 'B' => 'Pedro Ruiz',
                'P' => 'Sobremesa', 'Q' => 'HP', 'R' => 'ProDesk 400', 'S' => 'SN003', 'AC' => 'W10',
            ] + $common,
            // Candidato 2 de nombre ambiguo (mismo nombre, otro número de empleado).
            7 => [
                'A' => 2002, 'B' => 'Pedro Ruiz',
                'P' => 'Sobremesa', 'Q' => 'HP', 'R' => 'ProDesk 600', 'S' => 'SN004', 'AC' => 'W10',
            ] + $common,
            // Jefe inmediato ambiguo ("Pedro Ruiz" matchea 2001 y 2002).
            8 => [
                'A' => 1003, 'B' => 'Laura Sanchez', 'G' => 'Pedro Ruiz',
                'P' => 'Monitor', 'Q' => 'LG', 'R' => '24ML600', 'S' => 'SN005', 'AC' => 'NA',
            ] + $common,
            // Costo "#N/A" literal.
            9 => [
                'A' => 1004, 'B' => 'Carlos Vega',
                'P' => 'Portátil', 'Q' => 'Lenovo', 'R' => 'ThinkPad T14', 'S' => 'SN006', 'AC' => 'W11',
                'AL' => '#N/A',
            ] + $common,
            // Adquisición no numérica + Estatus sin homologar.
            10 => [
                'A' => 1005, 'B' => 'Sofia Torres',
                'P' => 'Portátil', 'Q' => 'Dell', 'R' => 'Latitude 3420', 'S' => 'SN007', 'AC' => 'W10',
                'AD' => 'En validación', 'AP' => 'Prestamo',
            ] + $common,
            // Mismo número de empleado que la fila 4 (2° activo de Juan Perez) —
            // repite el mismo Jefe que la fila 4 a propósito: el comando usa
            // "última fila gana" para los campos de jerarquía (igual que
            // `updateOrCreate` para el resto de los campos de Empleado), así
            // que si esta fila dejara "Jefe" en blanco borraría la resolución
            // de la fila 4 en vez de simplemente no repetirla.
            11 => [
                'A' => 1001, 'B' => 'Juan Perez', 'G' => 'Maria Lopez',
                'P' => 'Monitor', 'Q' => 'Samsung', 'R' => '24 pulgadas', 'S' => 'SN008', 'AC' => 'NA',
            ] + $common,
            // Sin número de empleado (equipo en stock, sin dueño).
            12 => [
                'A' => '', 'B' => 'Equipo en stock',
                'P' => 'Portátil', 'Q' => 'Dell', 'R' => 'Latitude 5420', 'S' => 'SN009', 'AC' => 'W10',
                'AP' => 'Asignado',
            ],
            // Sin Tipo de Equipo (FK requerida, no se puede crear el Asset).
            13 => [
                'A' => 1006, 'B' => 'Diego Ramirez',
                'P' => '', 'S' => 'SN-SIN-TIPO',
            ] + $common,
            // Modelo sin Marca (se omite el Modelo, el Asset se crea igual).
            14 => [
                'A' => 1007, 'B' => 'Elena Castro',
                'P' => 'Portátil', 'Q' => '', 'R' => 'Latitude X1', 'S' => 'SN010', 'AC' => 'W10',
            ] + $common,
        ];

        foreach ($rows as $rowNumber => $columns) {
            foreach ($columns as $col => $value) {
                if ($value === '') {
                    continue;
                }
                $sheet->setCellValue($col.$rowNumber, $value);
            }
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gestionti_fixture_'.uniqid().'.xlsx';

        (new XlsxWriter($spreadsheet))->save($path);

        return $path;
    }
}
