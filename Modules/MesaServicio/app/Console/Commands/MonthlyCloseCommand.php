<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\MesaServicio\Console\Commands\Concerns\GeneratesCierreReports;
use Modules\MesaServicio\Models\SdpReport;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Notifications\CierreMensualGeneradoNotification;

/**
 * Cierre mensual de Mesa de Servicio (Fase 5). Procesa por defecto "el mes
 * pasado completo" (today()->subMonthNoOverflow()->startOfMonth() hasta
 * endOfMonth() de ese mismo mes), consultando TODO lo creado ese mes en
 * sdp_tickets SIN filtrar por estado — mismo criterio de negocio ya cerrado
 * que el cierre diario (ver docs/mesaservicio-progreso.md).
 *
 * Acepta un argumento opcional `mes` (Y-m, ej. "2026-08") para regenerar
 * manualmente el cierre de un mes pasado — mismo criterio de bajo riesgo ya
 * aplicado a `fecha` en DailyCloseCommand.
 *
 * Agrega, además de lo que ya guarda el cierre diario (total/por_estado/
 * por_tecnico), la métrica de "folios combinados": SDP puede fusionar varios
 * tickets bajo un mismo folio visible, lo que hace que el rango de
 * `display_id` (folio mínimo a máximo del mes) sea más amplio que el conteo
 * real de tickets — la diferencia es un estimado de tickets que sí
 * representan trabajo real del técnico pero que SDP retiró del listado al
 * fusionarlos. Fórmula exacta: `(max(display_id) - min(display_id)) -
 * conteo_real`, calculada solo sobre tickets con `display_id` numérico (los
 * nulos/no numéricos se ignoran para el cálculo de min/max, pero SÍ cuentan
 * en `conteo_real` y en el total general del cierre). Si el resultado da
 * negativo, se guarda tal cual (sin forzar a 0) — es información de
 * diagnóstico sobre datos reales, no queremos ocultar una anomalía.
 *
 * La generación del Excel, la agrupación técnico/estado y el envío de la
 * notificación viven en Concerns\GeneratesCierreReports, compartido con
 * DailyCloseCommand (ver ese trait para el porqué de la extracción).
 */
class MonthlyCloseCommand extends Command
{
    use GeneratesCierreReports;

    /**
     * Duplicado deliberadamente, mismo valor y mismo criterio que
     * DailyCloseCommand::ROL_SUPERVISOR — ver docblock de esa constante.
     */
    public const ROL_SUPERVISOR = 'Supervisor Mesa de Servicio';

    protected $signature = 'sdp:monthly-close {mes? : Mes a cerrar (Y-m, ej. 2026-08), por defecto el mes pasado completo}';

    protected $description = 'Genera el cierre mensual de Mesa de Servicio (Excel + registro + notificación + métrica de folios combinados)';

    public function handle(): int
    {
        $mesArg = $this->argument('mes');

        if ($mesArg !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesArg)) {
            $this->error("Mes inválido: {$mesArg}. Usa el formato Y-m (ej. 2026-08).");

            return self::FAILURE;
        }

        $periodoInicio = $mesArg
            ? Carbon::createFromFormat('Y-m-d', "{$mesArg}-01")->startOfDay()
            : today()->subMonthNoOverflow()->startOfMonth()->startOfDay();

        $periodoFin = $periodoInicio->copy()->endOfMonth()->endOfDay();

        $tickets = SdpTicket::with(['technician', 'ticketStatus'])
            ->whereBetween('created_time', [$periodoInicio, $periodoFin])
            ->orderBy('created_time')
            ->get();

        $resumen = array_merge(
            $this->construirResumenBase($tickets),
            $this->calcularFoliosCombinados($tickets)
        );

        $rutaArchivo = "mesa-servicio/reportes/mensual/{$periodoInicio->toDateString()}.xlsx";

        $this->generarExcelCierre(
            $rutaArchivo,
            $periodoInicio->translatedFormat('F Y'),
            $tickets,
            $resumen,
            'Total de tickets del mes',
            $this->filasExtraFolios($resumen)
        );

        $report = $this->guardarReporte(SdpReport::TIPO_MENSUAL, $periodoInicio->toDateString(), $rutaArchivo, $resumen);

        $this->notificarSupervisoresYCorreos(self::ROL_SUPERVISOR, new CierreMensualGeneradoNotification($report));

        $this->info("Cierre mensual generado para {$periodoInicio->format('Y-m')} — {$tickets->count()} ticket(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, SdpTicket>  $tickets
     * @return array{folio_min: int|null, folio_max: int|null, conteo_real: int, estimado_combinados: int|null}
     */
    private function calcularFoliosCombinados(Collection $tickets): array
    {
        $conteoReal = $tickets->count();

        $displayIdsNumericos = $tickets
            ->pluck('display_id')
            ->filter(fn ($displayId) => $displayId !== null && is_numeric($displayId))
            ->map(fn ($displayId) => (int) $displayId);

        if ($displayIdsNumericos->isEmpty()) {
            return [
                'folio_min' => null,
                'folio_max' => null,
                'conteo_real' => $conteoReal,
                'estimado_combinados' => null,
            ];
        }

        $folioMin = $displayIdsNumericos->min();
        $folioMax = $displayIdsNumericos->max();

        return [
            'folio_min' => $folioMin,
            'folio_max' => $folioMax,
            'conteo_real' => $conteoReal,
            // Deliberadamente sin max(0, ...) — un resultado negativo es una
            // anomalía real de los datos (ej. folios reutilizados/eliminados
            // fuera del patrón esperado de fusión) y se reporta tal cual como
            // dato de diagnóstico, no se oculta forzándolo a 0.
            'estimado_combinados' => ($folioMax - $folioMin) - $conteoReal,
        ];
    }

    /**
     * @return array<int, array{0: string, 1: mixed}>
     */
    private function filasExtraFolios(array $resumen): array
    {
        return [
            ['Folios combinados (diagnóstico)', ''],
            ['Folio mínimo', $resumen['folio_min'] ?? 'N/D'],
            ['Folio máximo', $resumen['folio_max'] ?? 'N/D'],
            ['Conteo real de tickets', $resumen['conteo_real']],
            ['Tickets combinados estimados', $resumen['estimado_combinados'] ?? 'N/D'],
        ];
    }
}
