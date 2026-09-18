<?php

namespace Modules\MesaServicio\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\MesaServicio\Models\SdpReport;
use Modules\MesaServicio\Models\SdpReportRecipientEmail;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Lógica compartida entre los cierres de Mesa de Servicio
 * (Console\Commands\DailyCloseCommand, Fase 4, y Console\Commands\MonthlyCloseCommand,
 * Fase 5): agrupación técnico/estado, generación del Excel de 2 hojas
 * ("Resumen"/"Detalle"), persistencia idempotente del SdpReport, y el envío
 * mixto rol+correos sueltos. Extraído al escribir el segundo comando que lo
 * necesitaba casi idéntico — mismo criterio que
 * Console\Commands\Concerns\PaginatesSdpResults (Fase 2), que se extrajo al
 * escribir el tercer comando de sync en vez de duplicar una vez más.
 *
 * Deliberadamente NO incluye el nombre del rol de supervisor como constante
 * del trait: cada comando sigue declarando su propio
 * `public const ROL_SUPERVISOR` (mismo valor duplicado) — mismo patrón ya
 * documentado en DailyCloseCommand (duplicado en vez de referenciar
 * MesaServicioDatabaseSeeder::ROL_SUPERVISOR desde código de runtime), y
 * evita que un cambio futuro en un solo comando (ej. un rol de supervisor
 * distinto para el cierre mensual) obligue a tocar el trait compartido.
 */
trait GeneratesCierreReports
{
    /**
     * Etiquetas de los 2 grupos de tipo de estado, en el orden fijo en que
     * deben aparecer siempre (en_curso primero, completado después) — se
     * construyen explícitamente en este orden en vez de confiar en que
     * `orderBy('tipo')` los deje así por casualidad alfabética.
     *
     * @var array<string, string>
     */
    private const TIPOS_ESTADO_ORDENADOS = [
        SdpTicketStatus::TIPO_EN_CURSO => 'En curso',
        SdpTicketStatus::TIPO_COMPLETADO => 'Completado',
    ];

    /**
     * Nombre de estado con tratamiento especial: se saca del conteo
     * "en_curso" del técnico y se reporta en su propia columna, en vez de
     * quedar mezclado con el resto de los estados en_curso (decisión de
     * negocio confirmada explícitamente — ver docs/mesaservicio-progreso.md).
     */
    private const ESTADO_ESCALADO_A_PROVEEDOR = 'Escalado a Proveedor';

    /**
     * @param  Collection<int, SdpTicket>  $tickets
     * @return array{
     *     total: int,
     *     por_estado: array{
     *         grupos: array<int, array{tipo: string, etiqueta: string, estados: array<int, array{nombre: string, cantidad: int}>, subtotal: int}>,
     *         sin_catalogar: int,
     *     },
     *     por_tecnico: array<int, array{tecnico: string, completados: int, en_curso: int, escalado_a_proveedor: int, total: int}>,
     * }
     */
    protected function construirResumenBase(Collection $tickets): array
    {
        return [
            'total' => $tickets->count(),
            'por_estado' => $this->construirPorEstado($tickets),
            'por_tecnico' => $this->construirPorTecnico($tickets),
        ];
    }

    /**
     * Árbol de estados agrupados por tipo (en_curso/completado), con TODOS
     * los estados activos del catálogo — incluso los que no tuvieron ningún
     * ticket este periodo, que aparecen con cantidad 0, a propósito, para
     * que la hoja "Resumen" siempre muestre el catálogo completo — más un
     * subtotal por grupo y un conteo aparte de tickets cuyo `estado_nombre`
     * no matcheó ningún estado activo del catálogo (`sin_catalogar`, cubre
     * un estado nuevo en SDP que todavía no se sembró localmente vía
     * `sdp:sync-ticket-statuses`). Match por `nombre` (no por FK) porque
     * `estado_nombre` siempre está poblado, a diferencia de la relación
     * `ticketStatus`, que puede quedar `null` si la sincronización de
     * catálogo no alcanzó a resolver ese estado todavía.
     *
     * @param  Collection<int, SdpTicket>  $tickets
     * @return array{grupos: array<int, array{tipo: string, etiqueta: string, estados: array<int, array{nombre: string, cantidad: int}>, subtotal: int}>, sin_catalogar: int}
     */
    private function construirPorEstado(Collection $tickets): array
    {
        $conteoPorNombre = $tickets
            ->groupBy(fn (SdpTicket $ticket) => $ticket->estado_nombre ?? '')
            ->map(fn (Collection $grupo) => $grupo->count());

        $catalogoActivo = SdpTicketStatus::activos()->orderBy('id')->get();
        $nombresCatalogados = [];
        $grupos = [];

        foreach (self::TIPOS_ESTADO_ORDENADOS as $tipo => $etiqueta) {
            $estados = [];
            $subtotal = 0;

            foreach ($catalogoActivo->where('tipo', $tipo) as $estadoCatalogo) {
                $cantidad = (int) ($conteoPorNombre->get($estadoCatalogo->nombre) ?? 0);
                $estados[] = ['nombre' => $estadoCatalogo->nombre, 'cantidad' => $cantidad];
                $subtotal += $cantidad;
                $nombresCatalogados[] = $estadoCatalogo->nombre;
            }

            $grupos[] = [
                'tipo' => $tipo,
                'etiqueta' => $etiqueta,
                'estados' => $estados,
                'subtotal' => $subtotal,
            ];
        }

        $sinCatalogar = $tickets
            ->reject(fn (SdpTicket $ticket) => in_array($ticket->estado_nombre, $nombresCatalogados, true))
            ->count();

        return [
            'grupos' => $grupos,
            'sin_catalogar' => $sinCatalogar,
        ];
    }

    /**
     * Matriz de 3 columnas por técnico: Completados / En Curso / Escalado a
     * Proveedor — "Escalado a Proveedor" se saca deliberadamente del conteo
     * "En Curso" (aunque su tipo de catálogo sea TIPO_EN_CURSO) para no
     * contarlo dos veces. Igual que `construirPorEstado()`, el match es por
     * `estado_nombre` (no por FK), robusto a que `ticketStatus` no haya
     * resuelto todavía. A diferencia del árbol de `por_estado`, aquí NO se
     * listan técnicos sin actividad este periodo — el árbol de estados
     * refleja el catálogo completo a propósito, esta lista se queda acotada
     * a quién tuvo actividad real.
     *
     * @param  Collection<int, SdpTicket>  $tickets
     * @return array<int, array{tecnico: string, completados: int, en_curso: int, escalado_a_proveedor: int, total: int}>
     */
    private function construirPorTecnico(Collection $tickets): array
    {
        $nombresEnCurso = SdpTicketStatus::activos()->where('tipo', SdpTicketStatus::TIPO_EN_CURSO)->pluck('nombre')->all();
        $nombresCompletado = SdpTicketStatus::activos()->where('tipo', SdpTicketStatus::TIPO_COMPLETADO)->pluck('nombre')->all();

        return $tickets
            ->groupBy(fn (SdpTicket $ticket) => $ticket->technician?->nombre ?? 'Sin asignar')
            ->map(function (Collection $grupo, string $tecnico) use ($nombresEnCurso, $nombresCompletado) {
                $completados = 0;
                $enCurso = 0;
                $escaladoAProveedor = 0;

                foreach ($grupo as $ticket) {
                    if ($ticket->estado_nombre === self::ESTADO_ESCALADO_A_PROVEEDOR) {
                        $escaladoAProveedor++;
                    } elseif (in_array($ticket->estado_nombre, $nombresEnCurso, true)) {
                        $enCurso++;
                    } elseif (in_array($ticket->estado_nombre, $nombresCompletado, true)) {
                        $completados++;
                    }
                }

                return [
                    'tecnico' => $tecnico,
                    'completados' => $completados,
                    'en_curso' => $enCurso,
                    'escalado_a_proveedor' => $escaladoAProveedor,
                    'total' => $grupo->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SdpTicket>  $tickets
     * @param  array<int, array{0: string, 1: mixed}>  $filasExtra  Filas adicionales
     *         (ej. la métrica de folios combinados del cierre mensual) que se
     *         agregan al final de la hoja "Resumen", después del bloque "Por
     *         técnico". Vacío por defecto (cierre diario no agrega ninguna).
     */
    protected function generarExcelCierre(
        string $rutaRelativa,
        string $periodoLabel,
        Collection $tickets,
        array $resumen,
        string $totalLabel,
        array $filasExtra = []
    ): void {
        Storage::disk('local')->makeDirectory(dirname($rutaRelativa));

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->llenarHojaResumen(
            $spreadsheet->createSheet()->setTitle('Resumen'),
            $periodoLabel,
            $resumen,
            $totalLabel,
            $filasExtra
        );
        $this->llenarHojaDetalle($spreadsheet->createSheet()->setTitle('Detalle'), $tickets);

        (new XlsxWriter($spreadsheet))->save(Storage::disk('local')->path($rutaRelativa));
    }

    /**
     * @param  array<int, array{0: string, 1: mixed}>  $filasExtra
     */
    private function llenarHojaResumen(
        Worksheet $sheet,
        string $periodoLabel,
        array $resumen,
        string $totalLabel,
        array $filasExtra = []
    ): void {
        $rows = [];
        // Filas [número de fila (1-based), cantidad de columnas a negritear]
        // acumuladas conforme se arma $rows, en vez de hardcodear números de
        // fila fijos — el bloque "Por estado" ahora tiene largo variable
        // (depende de cuántos estados activos haya en el catálogo).
        $filasNegritas = [];

        $rows[] = ['Métrica', 'Valor'];
        $filasNegritas[] = [count($rows), 2];

        $rows[] = ['Periodo', $periodoLabel];
        $rows[] = [$totalLabel, $resumen['total']];
        $rows[] = ['', ''];

        $rows[] = ['Por estado', ''];
        $filasNegritas[] = [count($rows), 2];

        foreach ($resumen['por_estado']['grupos'] as $grupo) {
            $rows[] = ["Tipo: {$grupo['etiqueta']}", ''];
            $filasNegritas[] = [count($rows), 2];

            foreach ($grupo['estados'] as $estado) {
                $rows[] = ['  '.$estado['nombre'], $this->omitirSiCero($estado['cantidad'])];
            }

            $rows[] = ['Subtotal', $grupo['subtotal']];
            $filasNegritas[] = [count($rows), 2];
        }

        if (($resumen['por_estado']['sin_catalogar'] ?? 0) > 0) {
            $rows[] = ['Sin catalogar', $resumen['por_estado']['sin_catalogar']];
        }

        $rows[] = ['', ''];

        $rows[] = ['Por técnico', ''];
        $filasNegritas[] = [count($rows), 2];

        $rows[] = ['Técnico', 'Completados', 'En Curso', 'Escalado a Proveedor', 'Total'];
        $filasNegritas[] = [count($rows), 5];

        foreach ($resumen['por_tecnico'] as $fila) {
            $rows[] = [
                $fila['tecnico'],
                $this->omitirSiCero($fila['completados']),
                $this->omitirSiCero($fila['en_curso']),
                $this->omitirSiCero($fila['escalado_a_proveedor']),
                // Total no se omite aunque fuera 0: un técnico solo aparece
                // en esta lista si tuvo actividad ese periodo (ver
                // construirPorTecnico()), así que su total nunca es 0 en la
                // práctica — pero se deja el número real por claridad, es la
                // columna ancla de la fila, no "ruido" como las demás.
                $fila['total'],
            ];
        }

        // Fila de suma general de la tabla "Por técnico" — el usuario notó
        // que faltaba (la traía a mano seleccionando el rango en Excel). La
        // columna "Total" de esta fila debe coincidir con
        // $resumen['total'] — es la misma suma vista desde otro ángulo, un
        // buen check cruzado visual si algún día no cuadran.
        if ($resumen['por_tecnico'] !== []) {
            $rows[] = [
                'Total',
                array_sum(array_column($resumen['por_tecnico'], 'completados')),
                array_sum(array_column($resumen['por_tecnico'], 'en_curso')),
                array_sum(array_column($resumen['por_tecnico'], 'escalado_a_proveedor')),
                array_sum(array_column($resumen['por_tecnico'], 'total')),
            ];
            $filasNegritas[] = [count($rows), 5];
        }

        if ($filasExtra !== []) {
            $rows[] = ['', ''];

            foreach ($filasExtra as $fila) {
                $rows[] = $fila;
            }
        }

        foreach ($rows as $rowIndex => $rowValues) {
            $this->writeRow($sheet, $rowIndex + 1, $rowValues);
        }

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(12);

        foreach ($filasNegritas as [$rowNumber, $columnCount]) {
            $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
            $sheet->getStyle("A{$rowNumber}:{$lastColumn}{$rowNumber}")->getFont()->setBold(true);
        }
    }

    /**
     * @param  Collection<int, SdpTicket>  $tickets
     */
    private function llenarHojaDetalle(Worksheet $sheet, Collection $tickets): void
    {
        $headers = [
            'Folio', 'Asunto', 'Técnico', 'Estado', 'Categoría', 'Subcategoría',
            'Solicitante', 'Departamento', 'Prioridad', 'Creado', 'Resuelto', 'Completado',
        ];

        $this->writeRow($sheet, 1, $headers);
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($headers)).'1')->getFont()->setBold(true);

        foreach ($tickets->values() as $index => $ticket) {
            $this->writeRow($sheet, $index + 2, [
                $ticket->display_id,
                $ticket->asunto,
                $ticket->technician?->nombre ?? 'Sin asignar',
                $ticket->estado_nombre,
                $ticket->categoria,
                $ticket->subcategoria,
                $ticket->solicitante_nombre,
                $ticket->departamento,
                $ticket->prioridad,
                optional($ticket->created_time)->format('Y-m-d H:i'),
                optional($ticket->resolved_time)->format('Y-m-d H:i'),
                optional($ticket->completed_time)->format('Y-m-d H:i'),
            ]);
        }

        foreach (range('A', Coordinate::stringFromColumnIndex(count($headers))) as $column) {
            $sheet->getColumnDimension($column)->setWidth(22);
        }
    }

    /**
     * Fase 8 (Parte 5) — backlog histórico: total de tickets que TODAVÍA
     * siguen pendientes (ticketStatus.tipo = TIPO_EN_CURSO — "Combinado"
     * cuenta como completado, así que queda excluido aquí automáticamente,
     * sin necesitar una exclusión explícita) con `created_time` menor o
     * igual al cierre del periodo ($hasta), SIN límite inferior de fecha —
     * a diferencia de construirResumenBase(), que solo mira lo CREADO
     * dentro del periodo del cierre, esto es una foto del backlog completo
     * de cualquier año anterior que siga abierto al momento del corte.
     *
     * @return array{backlog_historico_total: int, backlog_historico_por_tecnico: array<string,int>, backlog_historico_por_categoria: array<string,int>}
     */
    protected function construirBacklogHistorico(Carbon $hasta): array
    {
        $tickets = SdpTicket::with(['technician', 'ticketStatus'])
            ->whereHas('ticketStatus', fn ($query) => $query->where('tipo', SdpTicketStatus::TIPO_EN_CURSO))
            ->where('created_time', '<=', $hasta)
            ->get();

        $porTecnico = $tickets
            ->groupBy(fn (SdpTicket $ticket) => $ticket->technician?->nombre ?? 'Sin asignar')
            ->map(fn (Collection $grupo) => $grupo->count())
            ->sortDesc();

        $porCategoria = $tickets
            ->groupBy(fn (SdpTicket $ticket) => $ticket->categoria ?: 'Sin categoría')
            ->map(fn (Collection $grupo) => $grupo->count())
            ->sortDesc();

        return [
            'backlog_historico_total' => $tickets->count(),
            'backlog_historico_por_tecnico' => $porTecnico->all(),
            'backlog_historico_por_categoria' => $porCategoria->all(),
        ];
    }

    /**
     * A petición del usuario: en las filas de detalle del árbol "Por estado"
     * y de la matriz "Por técnico", un conteo en 0 se deja en blanco en vez
     * de escribir el número "0" — reduce el ruido visual de un Excel con
     * muchas celdas en cero. Deliberadamente NO se aplica a los renglones de
     * "Subtotal"/"Total" (esos siguen mostrando el número real aunque sea 0,
     * son líneas de resumen, no "ruido").
     */
    private function omitirSiCero(int $cantidad): int|string
    {
        return $cantidad > 0 ? $cantidad : '';
    }

    /**
     * Ver Modules\GestionTI\Console\Commands\ImportarHistoricoCommand::writeRow()
     * — mismo motivo: cualquier texto libre (asunto, nombre) que empiece con
     * "=" sería interpretado como fórmula por el comportamiento por defecto
     * de PhpSpreadsheet si se usara fromArray()/setCellValue() en vez de
     * setCellValueExplicit(..., DataType::TYPE_STRING).
     */
    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $index => $value) {
            $coordinate = Coordinate::stringFromColumnIndex($index + 1).$row;

            if (is_int($value) || is_float($value)) {
                $sheet->setCellValue($coordinate, $value);
            } else {
                $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
            }
        }
    }

    /**
     * No se usa SdpReport::updateOrCreate(['periodo' => ...], [...]) a
     * propósito: el cast 'date' de Eloquent solo trunca la hora al LEER el
     * atributo (asDate()), pero al ESCRIBIR usa el formato datetime completo
     * de la conexión (fromDateTime()/getDateFormat(), sin truncar) — el WHERE
     * armado con un string plano "Y-m-d" no matchearía contra el valor real
     * guardado en la columna ("Y-m-d 00:00:00"), y terminaría insertando un
     * registro duplicado en vez de actualizar. whereDate() sí normaliza la
     * comparación (extrae solo la parte de fecha vía SQL) sin importar la
     * precisión real almacenada. Ver docs/mesaservicio-progreso.md, Fase 4.
     */
    protected function guardarReporte(string $tipo, string $periodoDateString, string $rutaArchivo, array $resumen): SdpReport
    {
        $existing = SdpReport::where('tipo', $tipo)
            ->whereDate('periodo', $periodoDateString)
            ->first();

        $atributos = [
            'tipo' => $tipo,
            'periodo' => $periodoDateString,
            'ruta_archivo' => $rutaArchivo,
            'resumen_metricas' => $resumen,
            'generado_en' => now(),
        ];

        return $existing
            ? tap($existing)->update($atributos)
            : SdpReport::create($atributos);
    }

    /**
     * Envía la notificación de cierre (cualquier subclase de
     * Illuminate\Notifications\Notification) a los usuarios activos con el
     * rol de supervisor Y a cada correo suelto de sdp_report_recipient_emails
     * — ninguno de los dos grupos falla si el otro está vacío.
     */
    protected function notificarSupervisoresYCorreos(string $rolSupervisor, $notification): void
    {
        $supervisores = User::whereHas('roles', fn ($query) => $query->where('name', $rolSupervisor))
            ->where('is_active', true)
            ->get();

        if ($supervisores->isNotEmpty()) {
            Notification::send($supervisores, $notification);
        }

        foreach (SdpReportRecipientEmail::all() as $recipient) {
            Notification::route('mail', $recipient->email)->notify($notification);
        }
    }
}
