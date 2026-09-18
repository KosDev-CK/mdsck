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
     * @param  Collection<int, SdpTicket>  $tickets
     * @return array{total: int, por_estado: array<string, int>, por_tecnico: array<string, int>}
     */
    protected function construirResumenBase(Collection $tickets): array
    {
        $porEstado = $tickets
            ->groupBy(fn (SdpTicket $ticket) => $ticket->estado_nombre ?: 'Sin estado')
            ->map(fn (Collection $grupo) => $grupo->count())
            ->sortDesc();

        $porTecnico = $tickets
            ->groupBy(fn (SdpTicket $ticket) => $ticket->technician?->nombre ?? 'Sin asignar')
            ->map(fn (Collection $grupo) => $grupo->count())
            ->sortDesc();

        return [
            'total' => $tickets->count(),
            'por_estado' => $porEstado->all(),
            'por_tecnico' => $porTecnico->all(),
        ];
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
        $rows = [
            ['Métrica', 'Valor'],
            ['Periodo', $periodoLabel],
            [$totalLabel, $resumen['total']],
            ['', ''],
            ['Por estado', ''],
        ];

        foreach ($resumen['por_estado'] as $estado => $conteo) {
            $rows[] = [$estado, $conteo];
        }

        $rows[] = ['', ''];
        $rows[] = ['Por técnico', ''];

        foreach ($resumen['por_tecnico'] as $tecnico => $conteo) {
            $rows[] = [$tecnico, $conteo];
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
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A5')->getFont()->setBold(true);
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
