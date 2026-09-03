<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionTI\Console\Commands\Support\RowRangeReadFilter;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\AssetCompliance;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Licencia;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Propiedad;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Puesto;
use Modules\GestionTI\Models\SistemaOperativo;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\UnidadNegocio;
use Modules\GestionTI\Models\Validador;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Throwable;

/**
 * Migración "big bang" del Excel histórico de inventario TI hacia las tablas
 * de la Fase 1 (catálogos) y la capa mínima de la Fase 2 (Asset /
 * AssetCompliance / AssetAssignment).
 *
 * Ver docs/gestionti-progreso.md — sección "Excel histórico" para el layout
 * exacto de columnas, y la entrada de Fase 2 para todas las decisiones de
 * homologación / mapeo / limitaciones documentadas de este comando.
 */
class ImportarHistoricoCommand extends Command
{
    protected $signature = 'gestionti:importar-historico
        {--path= : Ruta al .xlsx histórico (default: storage/app/imports/Inventario_20210622.xlsx)}
        {--dry-run : Corre todo dentro de una transacción que se revierte al final, sin persistir nada}
        {--report= : Ruta de salida del reporte de reconciliación .xlsx}';

    protected $description = 'Migra de una sola vez el Excel histórico de inventario TI hacia los catálogos y Asset/AssetCompliance/AssetAssignment.';

    private const SHEET_NAME = 'Activos';

    private const HEADER_ROW = 3;

    private const FIRST_DATA_ROW = 4;

    /** Margen amplio sobre las ~3799 filas reales conocidas (ver RowRangeReadFilter). */
    private const ROW_SCAN_CAP = 20000;

    private const COL_NUMERO_EMPLEADO = 'A';

    private const COL_NOMBRE = 'B';

    private const COL_CORREO = 'C';

    private const COL_PUESTO = 'D';

    private const COL_AREA = 'F';

    private const COL_JEFE_INMEDIATO = 'G';

    private const COL_UBICACION = 'H';

    private const COL_UNIDAD_NEGOCIO = 'J';

    private const COL_EMPRESA_RAZON_SOCIAL = 'K';

    private const COL_EMPRESA_NOMBRE_COMERCIAL = 'L';

    private const COL_DIRECTOR = 'N';

    private const COL_DIRECTOR_EJECUTIVO = 'O';

    private const COL_TIPO = 'P';

    private const COL_MARCA = 'Q';

    private const COL_MODELO = 'R';

    private const COL_SERIE = 'S';

    private const COL_EQUIPO_DOMINIO = 'T';

    private const COL_USUARIO_DOMINIO = 'U';

    private const COL_HOSTNAME = 'V';

    private const COL_WIFI = 'W';

    private const COL_ETHERNET = 'X';

    private const COL_PROCESADOR = 'Y';

    private const COL_GENERACION = 'Z';

    private const COL_DISCO_DURO = 'AA';

    private const COL_RAM = 'AB';

    private const COL_SO = 'AC';

    private const COL_ADQUISICION = 'AD';

    private const COL_NOTA_ADQUISICION = 'AF';

    private const COL_FECHA_VALIDACION = 'AG';

    private const COL_VALIDADO_POR = 'AH';

    private const COL_PROVEEDOR = 'AJ';

    private const COL_PROPIEDAD = 'AK';

    private const COL_COSTO = 'AL';

    private const COL_CROWDSTRIKE = 'AM';

    private const COL_LICENCIA_1 = 'AN';

    private const COL_LICENCIA_2 = 'AO';

    private const COL_ESTATUS = 'AP';

    private const COL_OBSERVACIONES = 'AR';

    private const COL_BITLOCKER = 'AS';

    private const COL_MTTO_PREVENTIVO = 'AT';

    /** Columnas revisadas para detectar la última fila real con datos (columna A no siempre basta, ver progreso.md). */
    private const SCAN_COLUMNS = [
        'A', 'B', 'C', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'N', 'O', 'P', 'Q', 'R', 'S',
        'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AF', 'AG', 'AH',
        'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AR', 'AS', 'AT',
    ];

    /**
     * Homologación de Tipo de Equipo (punto 1 del prompt). Match EXACTO
     * (case-sensitive, sin normalizar acentos/mayúsculas) contra los 3
     * valores históricos conocidos — cualquier otra grafía (incluidas
     * variantes de capitalización como "MONITOR" o "Portatil" sin acento) cae
     * en la rama "fuera de alcance" y se migra tal cual, de solo consulta.
     */
    private const TIPO_HOMOLOGACION = [
        'Portátil' => ['nombre' => 'Laptop', 'en_alcance' => true],
        'Sobremesa' => ['nombre' => 'PC de Escritorio', 'en_alcance' => true],
        'Monitor' => ['nombre' => 'Monitor', 'en_alcance' => true],
    ];

    /**
     * Homologación best-effort de Estatus (columna AP) contra los 5 códigos
     * base sembrados por GestionTIDatabaseSeeder. Comparación case-insensitive
     * y con y sin acentos. Cualquier valor no listado aquí (incluyendo vacío)
     * cae a `en_stock` y se marca en el reporte de reconciliación.
     */
    private const ESTATUS_HOMOLOGACION = [
        'asignado' => 'asignado',
        'disponible para reasignación' => 'en_stock',
        'disponible para reasignacion' => 'en_stock',
        'para reasignar' => 'en_stock',
        'reasignado de recuperación' => 'asignado',
        'reasignado de recuperacion' => 'asignado',
    ];

    private const DRY_RUN_SENTINEL = '__gestionti_importar_historico_dry_run__';

    private bool $dryRun = false;

    private array $summary = [];

    private array $report = [];

    /** @var array<string, array{empleado_id:int, nombre:string, jefe:string, director:string, director_ejecutivo:string}> */
    private array $jerarquiaRaw = [];

    private array $puestoCache = [];

    private array $areaCache = [];

    private array $ubicacionCache = [];

    private array $unidadNegocioCache = [];

    private array $empresaCache = [];

    private array $marcaCache = [];

    private array $modeloCache = [];

    private array $sistemaOperativoCache = [];

    private array $licenciaCache = [];

    private array $propiedadCache = [];

    private array $proveedorCache = [];

    private array $validadorCache = [];

    /** @var array<string, array{id:int, nombre:string}> */
    private array $tipoEquipoCache = [];

    private array $estatusIdByCodigo = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $path = $this->option('path') ?: storage_path('app/imports/Inventario_20210622.xlsx');
        $reportPath = $this->option('report') ?: storage_path('app/imports/reporte-reconciliacion-'.now()->format('Ymd-His').'.xlsx');

        if (! is_file($path)) {
            $this->error("No se encontró el archivo Excel en: {$path}");

            return self::FAILURE;
        }

        $this->initState();

        // No se restaura el memory_limit al final a propósito: el proceso CLI
        // termina junto con el comando, y forzar el límite hacia abajo después
        // de procesar miles de filas puede fallar si el uso actual ya lo
        // superó (ErrorException de PHP al intentar "encoger" el límite).
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {
            $sheet = $this->loadActivosSheet($path);
        } catch (Throwable $e) {
            $this->error('No se pudo leer el archivo Excel: '.$e->getMessage());

            return self::FAILURE;
        }

        $lastRow = $this->detectLastDataRow($sheet);

        if ($lastRow < self::FIRST_DATA_ROW) {
            $this->warn('No se encontraron filas de datos en la hoja "Activos".');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Procesando filas %d a %d de la hoja "%s" (%s)...',
            self::FIRST_DATA_ROW,
            $lastRow,
            self::SHEET_NAME,
            $this->dryRun ? 'dry-run, sin persistir' : 'ejecución real'
        ));

        try {
            $this->preloadCatalogCaches();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rolledBackForDryRun = false;

        try {
            DB::transaction(function () use ($sheet, $lastRow): void {
                $this->importRows($sheet, $lastRow);
                $this->resolveJerarquia();

                if ($this->dryRun) {
                    throw new \RuntimeException(self::DRY_RUN_SENTINEL);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== self::DRY_RUN_SENTINEL) {
                throw $e;
            }
            $rolledBackForDryRun = true;
        }

        $reportWritten = false;

        try {
            $this->writeReport($reportPath);
            $reportWritten = true;
        } catch (Throwable $e) {
            $this->warn('No se pudo escribir el reporte de reconciliación: '.$e->getMessage());
        }

        $this->printSummary($reportWritten ? $reportPath : null, $rolledBackForDryRun);

        return self::SUCCESS;
    }

    private function initState(): void
    {
        $this->summary = [
            'filas_procesadas' => 0,
            'filas_con_error' => 0,
            'filas_sin_numero_empleado' => 0,
            'filas_sin_tipo_equipo' => 0,
            'empleados_creados' => 0,
            'empleados_actualizados' => 0,
            'assets_creados' => 0,
            'asset_compliances_creados' => 0,
            'asset_assignments_creados' => 0,
            'costos_no_numericos' => 0,
            'estatus_no_mapeados' => 0,
            'modelos_omitidos_por_marca' => 0,
            'jerarquia_no_resuelta' => 0,
            'catalogos_creados' => [
                'puestos' => 0,
                'areas' => 0,
                'ubicaciones' => 0,
                'unidades_negocio' => 0,
                'empresas' => 0,
                'marcas' => 0,
                'modelos' => 0,
                'sistemas_operativos' => 0,
                'licencias' => 0,
                'propiedades' => 0,
                'proveedores' => 0,
                'validadores' => 0,
                'tipos_equipo' => 0,
            ],
        ];

        $this->report = [
            'jerarquia_no_resuelta' => [],
            'modelos_omitidos' => [],
            'estatus_no_mapeados' => [],
            'sin_numero_empleado' => [],
            'sin_tipo_equipo' => [],
            'errores_fila' => [],
        ];

        $this->jerarquiaRaw = [];
        // Secuencia de `codigo` de Asset — vive en Asset::generateCodigo()
        // (compartida con la pantalla de Recepción de Proveedor, Fase 3) en
        // vez de un caché propio de este comando; se resetea aquí para que
        // esta corrida siempre arranque de un `max(codigo)` fresco.
        Asset::resetCodigoSequenceCache();
        $this->puestoCache = [];
        $this->areaCache = [];
        $this->ubicacionCache = [];
        $this->unidadNegocioCache = [];
        $this->empresaCache = [];
        $this->marcaCache = [];
        $this->modeloCache = [];
        $this->sistemaOperativoCache = [];
        $this->licenciaCache = [];
        $this->propiedadCache = [];
        $this->proveedorCache = [];
        $this->validadorCache = [];
        $this->tipoEquipoCache = [];
        $this->estatusIdByCodigo = [];
    }

    private function loadActivosSheet(string $path): Worksheet
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new RowRangeReadFilter(self::ROW_SCAN_CAP));

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);

        if (! $sheet) {
            throw new \RuntimeException('El archivo no tiene una hoja llamada "'.self::SHEET_NAME.'".');
        }

        return $sheet;
    }

    private function detectLastDataRow(Worksheet $sheet): int
    {
        $lastRow = self::HEADER_ROW;
        $maxScan = min(self::ROW_SCAN_CAP, max($sheet->getHighestRow(), self::FIRST_DATA_ROW));

        for ($row = self::FIRST_DATA_ROW; $row <= $maxScan; $row++) {
            foreach (self::SCAN_COLUMNS as $col) {
                $value = $sheet->getCell($col.$row)->getValue();
                if ($value !== null && trim((string) $value) !== '') {
                    $lastRow = $row;
                    break;
                }
            }
        }

        return $lastRow;
    }

    private function preloadCatalogCaches(): void
    {
        $this->puestoCache = Puesto::query()->pluck('id', 'nombre')->all();
        $this->areaCache = Area::query()->pluck('id', 'nombre')->all();
        $this->ubicacionCache = Ubicacion::query()->pluck('id', 'nombre')->all();
        $this->unidadNegocioCache = UnidadNegocio::query()->pluck('id', 'nombre')->all();
        $this->marcaCache = Marca::query()->pluck('id', 'nombre')->all();
        $this->sistemaOperativoCache = SistemaOperativo::query()->pluck('id', 'nombre')->all();
        $this->licenciaCache = Licencia::query()->pluck('id', 'nombre')->all();
        $this->propiedadCache = Propiedad::query()->pluck('id', 'nombre')->all();
        $this->validadorCache = Validador::query()->pluck('id', 'nombre')->all();

        foreach (Empresa::query()->get(['id', 'razon_social']) as $empresa) {
            $this->empresaCache[$empresa->razon_social] = $empresa->id;
        }

        foreach (Proveedor::query()->get(['id', 'razon_social']) as $proveedor) {
            $this->proveedorCache[$proveedor->razon_social] = $proveedor->id;
        }

        foreach (Modelo::query()->get(['id', 'nombre', 'marca_id']) as $modelo) {
            $this->modeloCache[$modelo->marca_id.'|'.$modelo->nombre] = $modelo->id;
        }

        foreach (TipoEquipo::query()->get(['id', 'nombre']) as $tipo) {
            $this->tipoEquipoCache[$tipo->nombre] = ['id' => $tipo->id, 'nombre' => $tipo->nombre];
        }

        $this->estatusIdByCodigo = EstatusActivo::query()->pluck('id', 'codigo')->all();

        foreach (['en_stock', 'reservado', 'asignado', 'en_reparacion', 'baja'] as $codigoBase) {
            if (! array_key_exists($codigoBase, $this->estatusIdByCodigo)) {
                throw new \RuntimeException(
                    "Falta el estatus base '{$codigoBase}' en la tabla estatus_activo — corre primero `php artisan module:seed GestionTI`."
                );
            }
        }
    }

    private function importRows(Worksheet $sheet, int $lastRow): void
    {
        $total = $lastRow - self::FIRST_DATA_ROW + 1;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        for ($row = self::FIRST_DATA_ROW; $row <= $lastRow; $row++) {
            try {
                $this->processRow($sheet, $row);
            } catch (Throwable $e) {
                $this->summary['filas_con_error']++;
                $this->report['errores_fila'][] = [
                    'fila_excel' => $row,
                    'numero_empleado' => trim((string) $sheet->getCell(self::COL_NUMERO_EMPLEADO.$row)->getValue()),
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function processRow(Worksheet $sheet, int $row): void
    {
        $get = function (string $col) use ($sheet, $row): string {
            $value = $sheet->getCell($col.$row)->getValue();

            return trim((string) ($value ?? ''));
        };

        $this->summary['filas_procesadas']++;

        $numeroEmpleado = $get(self::COL_NUMERO_EMPLEADO);
        $nombre = $get(self::COL_NOMBRE);
        $correo = $get(self::COL_CORREO);

        $puestoId = $this->resolveSimpleCatalog(Puesto::class, $get(self::COL_PUESTO), $this->puestoCache, 'puestos');
        $areaId = $this->resolveSimpleCatalog(Area::class, $get(self::COL_AREA), $this->areaCache, 'areas');
        $ubicacionId = $this->resolveSimpleCatalog(Ubicacion::class, $get(self::COL_UBICACION), $this->ubicacionCache, 'ubicaciones');
        $unidadNegocioId = $this->resolveSimpleCatalog(UnidadNegocio::class, $get(self::COL_UNIDAD_NEGOCIO), $this->unidadNegocioCache, 'unidades_negocio');
        $empresaId = $this->resolveEmpresa($get(self::COL_EMPRESA_RAZON_SOCIAL), $get(self::COL_EMPRESA_NOMBRE_COMERCIAL));

        $empleadoId = null;

        if ($numeroEmpleado !== '') {
            $empleado = Empleado::updateOrCreate(
                ['numero_empleado' => $numeroEmpleado],
                [
                    // `nombre` es NOT NULL en el esquema; si el Excel algún día trae
                    // un número de empleado sin nombre, se usa el propio número como
                    // fallback en vez de tronar (no observado en el archivo real).
                    'nombre' => $nombre !== '' ? $nombre : $numeroEmpleado,
                    'correo' => $correo !== '' ? $correo : null,
                    'puesto_id' => $puestoId,
                    'area_id' => $areaId,
                    'ubicacion_id' => $ubicacionId,
                    'unidad_negocio_id' => $unidadNegocioId,
                    'empresa_id' => $empresaId,
                ]
            );

            if ($empleado->wasRecentlyCreated) {
                $this->summary['empleados_creados']++;
            } else {
                $this->summary['empleados_actualizados']++;
            }

            $empleadoId = $empleado->id;

            // Última fila gana (mismo comportamiento que el resto de los campos
            // de Empleado vía updateOrCreate) — la jerarquía se resuelve en una
            // segunda pasada, una vez que TODOS los empleados existen.
            $this->jerarquiaRaw[$numeroEmpleado] = [
                'empleado_id' => $empleadoId,
                'nombre' => $empleado->nombre,
                'jefe' => $get(self::COL_JEFE_INMEDIATO),
                'director' => $get(self::COL_DIRECTOR),
                'director_ejecutivo' => $get(self::COL_DIRECTOR_EJECUTIVO),
            ];
        } else {
            $this->summary['filas_sin_numero_empleado']++;
            $this->report['sin_numero_empleado'][] = [
                'fila_excel' => $row,
                'nombre' => $nombre,
                'numero_serie' => $get(self::COL_SERIE),
            ];
        }

        $tipoRaw = $get(self::COL_TIPO);

        if ($tipoRaw === '') {
            // tipo_equipo_id es una FK requerida en `assets` — sin Tipo no hay
            // forma de dar de alta el Asset de esta fila (1 caso confirmado en
            // el archivo real, fila con solo el número de empleado capturado).
            $this->summary['filas_sin_tipo_equipo']++;
            $this->report['sin_tipo_equipo'][] = [
                'fila_excel' => $row,
                'numero_empleado' => $numeroEmpleado,
                'numero_serie' => $get(self::COL_SERIE),
            ];

            return;
        }

        [$tipoEquipoId, $tipoCanonicalNombre] = $this->resolveTipoEquipo($tipoRaw);

        $marcaId = $this->resolveSimpleCatalog(Marca::class, $get(self::COL_MARCA), $this->marcaCache, 'marcas');
        $modeloId = $this->resolveModelo($marcaId, $get(self::COL_MODELO), $row, $numeroEmpleado);
        $sistemaOperativoId = $this->resolveSimpleCatalog(SistemaOperativo::class, $get(self::COL_SO), $this->sistemaOperativoCache, 'sistemas_operativos');
        $licencia1Id = $this->resolveSimpleCatalog(Licencia::class, $get(self::COL_LICENCIA_1), $this->licenciaCache, 'licencias');
        $licencia2Id = $this->resolveSimpleCatalog(Licencia::class, $get(self::COL_LICENCIA_2), $this->licenciaCache, 'licencias');
        $propiedadId = $this->resolveSimpleCatalog(Propiedad::class, $get(self::COL_PROPIEDAD), $this->propiedadCache, 'propiedades');
        $proveedorId = $this->resolveProveedor($get(self::COL_PROVEEDOR));
        $validadorId = $this->resolveSimpleCatalog(Validador::class, $get(self::COL_VALIDADO_POR), $this->validadorCache, 'validadores');

        $especificaciones = [
            'equipo_en_dominio' => $this->nullableString($get(self::COL_EQUIPO_DOMINIO)),
            'usuario_dominio' => $this->nullableString($get(self::COL_USUARIO_DOMINIO)),
            'hostname' => $this->nullableString($get(self::COL_HOSTNAME)),
            'mac_wifi' => $this->nullableString($get(self::COL_WIFI)),
            'mac_ethernet' => $this->nullableString($get(self::COL_ETHERNET)),
            'procesador' => $this->nullableString($get(self::COL_PROCESADOR)),
            'generacion' => $this->nullableString($get(self::COL_GENERACION)),
            'disco_duro' => $this->nullableString($get(self::COL_DISCO_DURO)),
            'ram' => $this->nullableString($get(self::COL_RAM)),
            // El esquema de Asset (Fase 2, ya cerrado) no tiene columna
            // `sistema_operativo_id` — el catálogo SistemaOperativo igual se
            // crea/reutiliza (paso 2 de la migración) para que quede poblado,
            // pero aquí solo se conserva el texto crudo; una futura Fase 3 que
            // agregue la FK real puede reconciliarla contra el catálogo ya
            // sembrado. Ver docs/gestionti-progreso.md.
            'sistema_operativo' => $this->nullableString($get(self::COL_SO)),
        ];

        $costoRaw = $get(self::COL_COSTO);
        $costo = null;

        if ($costoRaw !== '') {
            if (is_numeric($costoRaw)) {
                $costo = (float) $costoRaw;
            } else {
                // Ej. "#N/A", "NA", cadenas con formato de moneda ("$20,877.68")
                // o texto libre ("En validación") — se documenta como limitación
                // conocida en docs/gestionti-progreso.md en vez de intentar un
                // parseo heurístico de moneda con formatos ambiguos.
                $this->summary['costos_no_numericos']++;
            }
        }

        // El generador de `codigo` solo necesita el `nombre` del tipo (para
        // resolver el slug) — se arma una instancia transitoria en vez de
        // volver a consultar `TipoEquipo` por id, ya resuelta arriba vía
        // `resolveTipoEquipo()`. Ver Asset::generateCodigo() — extraído de
        // este comando en Fase 3 (etapa 3, Recepción de Proveedor) para que
        // ambos compartan la misma secuencia y no colisionen en `codigo`.
        $codigo = Asset::generateCodigo(new TipoEquipo(['nombre' => $tipoCanonicalNombre]));

        $asset = Asset::create([
            'codigo' => $codigo,
            'tipo_equipo_id' => $tipoEquipoId,
            'marca_id' => $marcaId,
            'modelo_id' => $modeloId,
            'numero_serie' => $this->nullableString($get(self::COL_SERIE)),
            'service_tag' => null,
            'especificaciones' => $especificaciones,
            'costo_adquisicion' => $costo,
            'origen_tipo' => 'migracion_historica',
            'vendor_id' => $proveedorId,
            'fecha_alta_stock' => $this->parseExcelDate($get(self::COL_ADQUISICION)),
            'ubicacion_actual_id' => $ubicacionId,
            'estatus_id' => $this->resolveEstatus($get(self::COL_ESTATUS), $row, $numeroEmpleado),
            'propiedad_id' => $propiedadId,
            'nota_adquisicion_original' => $this->nullableString($get(self::COL_NOTA_ADQUISICION)),
        ]);

        $this->summary['assets_creados']++;

        AssetCompliance::firstOrCreate(
            ['asset_id' => $asset->id],
            [
                'crowdstrike' => $this->parseTriStateBool($get(self::COL_CROWDSTRIKE)),
                'bitlocker' => $this->parseTriStateBool($get(self::COL_BITLOCKER)),
                'licencia_1_id' => $licencia1Id,
                'licencia_2_id' => $licencia2Id,
                'mantenimiento_preventivo' => $this->parseExcelDate($get(self::COL_MTTO_PREVENTIVO)),
                'fecha_validacion' => $this->parseExcelDate($get(self::COL_FECHA_VALIDACION)),
                'validado_por_id' => $validadorId,
            ]
        );

        $this->summary['asset_compliances_creados']++;

        if ($empleadoId !== null) {
            AssetAssignment::create([
                'asset_id' => $asset->id,
                'empleado_id' => $empleadoId,
                'fecha_asignacion' => null,
                'fecha_devolucion' => null,
                'observaciones' => $this->nullableString($get(self::COL_OBSERVACIONES)),
            ]);

            $this->summary['asset_assignments_creados']++;
        }
    }

    private function resolveJerarquia(): void
    {
        if ($this->jerarquiaRaw === []) {
            return;
        }

        $normalizedNameToIds = [];
        $idToNumero = [];

        Empleado::query()->select(['id', 'numero_empleado', 'nombre'])
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$normalizedNameToIds, &$idToNumero): void {
                foreach ($chunk as $empleado) {
                    $key = mb_strtolower(trim($empleado->nombre), 'UTF-8');
                    $normalizedNameToIds[$key][] = $empleado->id;
                    $idToNumero[$empleado->id] = $empleado->numero_empleado;
                }
            });

        $fields = [
            'jefe' => 'jefe_inmediato_id',
            'director' => 'director_id',
            'director_ejecutivo' => 'director_ejecutivo_id',
        ];

        foreach ($this->jerarquiaRaw as $numeroEmpleadoKey => $data) {
            // PHP castea automáticamente claves de array numérico-string
            // (ej. "1003") a int — se vuelve a forzar string aquí para que el
            // reporte de reconciliación sea consistente con el resto de las
            // columnas "número de empleado" (siempre texto, ya que también
            // puede traer valores no numéricos como "no aplica").
            $numeroEmpleado = (string) $numeroEmpleadoKey;
            $updates = [];

            foreach ($fields as $rawKey => $column) {
                $rawName = trim($data[$rawKey]);

                if ($rawName === '') {
                    continue;
                }

                $key = mb_strtolower($rawName, 'UTF-8');
                $candidates = $normalizedNameToIds[$key] ?? [];

                if (count($candidates) === 1) {
                    $updates[$column] = $candidates[0];

                    continue;
                }

                $motivo = $candidates === [] ? 'no_encontrado' : 'ambiguo';
                $this->summary['jerarquia_no_resuelta']++;
                $this->report['jerarquia_no_resuelta'][] = [
                    'numero_empleado' => $numeroEmpleado,
                    'nombre' => $data['nombre'],
                    'campo' => $rawKey,
                    'valor_original' => $rawName,
                    'motivo' => $motivo,
                    'candidatos' => $motivo === 'ambiguo'
                        ? implode(', ', array_map(fn ($id) => $idToNumero[$id] ?? (string) $id, $candidates))
                        : '',
                ];
            }

            if ($updates !== []) {
                Empleado::whereKey($data['empleado_id'])->update($updates);
            }
        }
    }

    private function resolveSimpleCatalog(string $modelClass, string $raw, array &$cache, string $summaryKey): ?int
    {
        if ($raw === '') {
            return null;
        }

        if (array_key_exists($raw, $cache)) {
            return $cache[$raw];
        }

        $model = $modelClass::firstOrCreate(['nombre' => $raw]);

        if ($model->wasRecentlyCreated) {
            $this->summary['catalogos_creados'][$summaryKey]++;
        }

        return $cache[$raw] = $model->id;
    }

    private function resolveEmpresa(string $rawRazonSocial, string $rawNombreComercial): ?int
    {
        if ($rawRazonSocial === '' && $rawNombreComercial === '') {
            return null;
        }

        // K ("Nombre Completo de la empresa") es la clave preferida; si viene
        // vacía (10 filas en el archivo real) se usa L como razón social.
        $key = $rawRazonSocial !== '' ? $rawRazonSocial : $rawNombreComercial;

        if (isset($this->empresaCache[$key])) {
            return $this->empresaCache[$key];
        }

        $empresa = Empresa::firstOrCreate(
            ['razon_social' => $key],
            // nombre_comercial es NOT NULL en el esquema; si L viene vacía se
            // repite la razón social (1 fila en el archivo real).
            ['nombre_comercial' => $rawNombreComercial !== '' ? $rawNombreComercial : $key]
        );

        if ($empresa->wasRecentlyCreated) {
            $this->summary['catalogos_creados']['empresas']++;
        }

        return $this->empresaCache[$key] = $empresa->id;
    }

    private function resolveProveedor(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }

        if (isset($this->proveedorCache[$raw])) {
            return $this->proveedorCache[$raw];
        }

        $proveedor = Proveedor::firstOrCreate(
            ['razon_social' => $raw],
            ['nombre_comercial' => $raw]
        );

        if ($proveedor->wasRecentlyCreated) {
            $this->summary['catalogos_creados']['proveedores']++;
        }

        return $this->proveedorCache[$raw] = $proveedor->id;
    }

    private function resolveModelo(?int $marcaId, string $rawModelo, int $row, string $numeroEmpleado): ?int
    {
        if ($rawModelo === '') {
            return null;
        }

        if ($marcaId === null) {
            $this->summary['modelos_omitidos_por_marca']++;
            $this->report['modelos_omitidos'][] = [
                'fila_excel' => $row,
                'numero_empleado' => $numeroEmpleado,
                'modelo' => $rawModelo,
            ];

            return null;
        }

        $cacheKey = $marcaId.'|'.$rawModelo;

        if (isset($this->modeloCache[$cacheKey])) {
            return $this->modeloCache[$cacheKey];
        }

        $modelo = Modelo::firstOrCreate(['nombre' => $rawModelo, 'marca_id' => $marcaId]);

        if ($modelo->wasRecentlyCreated) {
            $this->summary['catalogos_creados']['modelos']++;
        }

        return $this->modeloCache[$cacheKey] = $modelo->id;
    }

    private function resolveTipoEquipo(string $raw): array
    {
        if (isset($this->tipoEquipoCache[$raw])) {
            return [$this->tipoEquipoCache[$raw]['id'], $this->tipoEquipoCache[$raw]['nombre']];
        }

        if (isset(self::TIPO_HOMOLOGACION[$raw])) {
            $target = self::TIPO_HOMOLOGACION[$raw];
            $tipo = TipoEquipo::firstOrCreate(
                ['nombre' => $target['nombre']],
                ['en_alcance' => $target['en_alcance']]
            );
        } else {
            // Fuera de alcance: se migra tal cual (de solo consulta), sin
            // intentar normalizar variantes de capitalización/typos contra los
            // 3 tipos canónicos — ver docs/gestionti-progreso.md.
            $tipo = TipoEquipo::firstOrCreate(
                ['nombre' => $raw],
                ['en_alcance' => false]
            );
        }

        if ($tipo->wasRecentlyCreated) {
            $this->summary['catalogos_creados']['tipos_equipo']++;
        }

        $this->tipoEquipoCache[$raw] = ['id' => $tipo->id, 'nombre' => $tipo->nombre];

        return [$tipo->id, $tipo->nombre];
    }

    private function resolveEstatus(string $raw, int $row, string $numeroEmpleado): int
    {
        $normalized = mb_strtolower($raw, 'UTF-8');
        $normalizedAscii = mb_strtolower(Str::ascii($raw));

        $codigo = self::ESTATUS_HOMOLOGACION[$normalized] ?? self::ESTATUS_HOMOLOGACION[$normalizedAscii] ?? null;

        if ($codigo === null) {
            $codigo = 'en_stock';
            $this->summary['estatus_no_mapeados']++;
            $this->report['estatus_no_mapeados'][] = [
                'fila_excel' => $row,
                'numero_empleado' => $numeroEmpleado,
                'valor_original' => $raw !== '' ? $raw : '(vacío)',
            ];
        }

        return $this->estatusIdByCodigo[$codigo];
    }

    private function parseTriStateBool(string $raw): ?bool
    {
        $normalized = mb_strtolower(trim($raw), 'UTF-8');

        return match (true) {
            in_array($normalized, ['si', 'sí'], true) => true,
            $normalized === 'no' => false,
            default => null,
        };
    }

    private function parseExcelDate(string $raw): ?string
    {
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        try {
            return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableString(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function writeReport(string $path): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->fillResumenSheet($spreadsheet->createSheet()->setTitle('Resumen'));

        $this->fillListSheet(
            $spreadsheet->createSheet()->setTitle('Jerarquia no resuelta'),
            ['Número de empleado', 'Nombre', 'Campo', 'Valor original', 'Motivo', 'Candidatos (núm. empleado)'],
            array_map(fn ($r) => [$r['numero_empleado'], $r['nombre'], $r['campo'], $r['valor_original'], $r['motivo'], $r['candidatos']], $this->report['jerarquia_no_resuelta'])
        );

        $this->fillListSheet(
            $spreadsheet->createSheet()->setTitle('Modelos omitidos'),
            ['Fila Excel', 'Número de empleado', 'Modelo (sin marca)'],
            array_map(fn ($r) => [$r['fila_excel'], $r['numero_empleado'], $r['modelo']], $this->report['modelos_omitidos'])
        );

        $this->fillListSheet(
            $spreadsheet->createSheet()->setTitle('Estatus no mapeados'),
            ['Fila Excel', 'Número de empleado', 'Valor original'],
            array_map(fn ($r) => [$r['fila_excel'], $r['numero_empleado'], $r['valor_original']], $this->report['estatus_no_mapeados'])
        );

        $this->fillListSheet(
            $spreadsheet->createSheet()->setTitle('Filas sin empleado'),
            ['Fila Excel', 'Nombre', 'Número de serie'],
            array_map(fn ($r) => [$r['fila_excel'], $r['nombre'], $r['numero_serie']], $this->report['sin_numero_empleado'])
        );

        $this->fillListSheet(
            $spreadsheet->createSheet()->setTitle('Filas sin tipo equipo'),
            ['Fila Excel', 'Número de empleado', 'Número de serie'],
            array_map(fn ($r) => [$r['fila_excel'], $r['numero_empleado'], $r['numero_serie']], $this->report['sin_tipo_equipo'])
        );

        $this->fillListSheet(
            $spreadsheet->createSheet()->setTitle('Errores inesperados'),
            ['Fila Excel', 'Número de empleado', 'Error'],
            array_map(fn ($r) => [$r['fila_excel'], $r['numero_empleado'], $r['error']], $this->report['errores_fila'])
        );

        (new XlsxWriter($spreadsheet))->save($path);
    }

    private function fillResumenSheet(Worksheet $sheet): void
    {
        $rows = [
            ['Métrica', 'Valor'],
            ['Filas procesadas', $this->summary['filas_procesadas']],
            ['Filas con error inesperado (omitidas, ver hoja "Errores inesperados")', $this->summary['filas_con_error']],
            ['Filas sin número de empleado (Asset creado sin AssetAssignment)', $this->summary['filas_sin_numero_empleado']],
            ['Filas sin Tipo de Equipo (fila omitida por completo)', $this->summary['filas_sin_tipo_equipo']],
            ['Empleados creados', $this->summary['empleados_creados']],
            ['Empleados actualizados', $this->summary['empleados_actualizados']],
            ['Activos (Asset) creados', $this->summary['assets_creados']],
            ['Registros de compliance creados', $this->summary['asset_compliances_creados']],
            ['Asignaciones (AssetAssignment) creadas', $this->summary['asset_assignments_creados']],
            ['Costos no numéricos (nota costo_adquisicion = null)', $this->summary['costos_no_numericos']],
            ['Estatus no mapeados (fallback a en_stock)', $this->summary['estatus_no_mapeados']],
            ['Modelos omitidos por falta de Marca', $this->summary['modelos_omitidos_por_marca']],
            ['Campos de jerarquía (jefe/director/director ejecutivo) no resueltos', $this->summary['jerarquia_no_resuelta']],
            ['', ''],
            ['Catálogos creados', ''],
        ];

        foreach ($this->summary['catalogos_creados'] as $catalogo => $cantidad) {
            $rows[] = [$catalogo, $cantidad];
        }

        foreach ($rows as $rowIndex => $rowValues) {
            $this->writeRow($sheet, $rowIndex + 1, $rowValues);
        }

        $sheet->getColumnDimension('A')->setWidth(70);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
    }

    private function fillListSheet(Worksheet $sheet, array $headers, array $rows): void
    {
        $this->writeRow($sheet, 1, $headers);
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1')->getFont()->setBold(true);

        foreach ($rows as $index => $rowValues) {
            $this->writeRow($sheet, $index + 2, $rowValues);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setWidth(28);
        }
    }

    /**
     * Escribe una fila celda por celda usando `setCellValueExplicit` con tipo
     * STRING forzado para cualquier valor no numérico.
     *
     * Necesario porque varias columnas del Excel histórico (jefe/director,
     * observaciones, etc.) pueden traer texto arrastrado que empieza con "="
     * (ej. fórmulas VLOOKUP copiadas por error desde otras celdas) — el
     * `fromArray()` por defecto de PhpSpreadsheet auto-detecta ese tipo de
     * valores como fórmulas, y al no existir la tabla/rango original en este
     * libro nuevo, el *writer* revienta al guardar. Ver
     * docs/gestionti-progreso.md, sección Fase 2.
     */
    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $index => $value) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1).$row;

            if (is_int($value) || is_float($value)) {
                $sheet->setCellValue($coordinate, $value);
            } else {
                $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
            }
        }
    }

    private function printSummary(?string $reportPath, bool $rolledBack): void
    {
        $this->newLine();

        if ($rolledBack) {
            $this->info('DRY-RUN completo — no se guardó ningún cambio (transacción revertida).');
        } else {
            $this->info('Importación completada y confirmada en la base de datos.');
        }

        $this->table(['Métrica', 'Valor'], [
            ['Filas procesadas', $this->summary['filas_procesadas']],
            ['Filas con error inesperado (omitidas)', $this->summary['filas_con_error']],
            ['Filas sin número de empleado', $this->summary['filas_sin_numero_empleado']],
            ['Filas sin tipo de equipo', $this->summary['filas_sin_tipo_equipo']],
            ['Empleados creados', $this->summary['empleados_creados']],
            ['Empleados actualizados', $this->summary['empleados_actualizados']],
            ['Activos creados', $this->summary['assets_creados']],
            ['Registros de compliance creados', $this->summary['asset_compliances_creados']],
            ['Asignaciones creadas', $this->summary['asset_assignments_creados']],
            ['Costos no numéricos (nulos)', $this->summary['costos_no_numericos']],
            ['Estatus no mapeados (fallback en_stock)', $this->summary['estatus_no_mapeados']],
            ['Modelos omitidos por falta de marca', $this->summary['modelos_omitidos_por_marca']],
            ['Jerarquía no resuelta', $this->summary['jerarquia_no_resuelta']],
        ]);

        $this->table(
            ['Catálogo', 'Registros nuevos'],
            collect($this->summary['catalogos_creados'])->map(fn ($v, $k) => [$k, $v])->values()->all()
        );

        if ($reportPath !== null) {
            $this->info("Reporte de reconciliación: {$reportPath}");
        }
    }
}
