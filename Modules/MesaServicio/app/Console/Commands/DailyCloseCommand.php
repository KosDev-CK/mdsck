<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MesaServicio\Console\Commands\Concerns\GeneratesCierreReports;
use Modules\MesaServicio\Models\SdpReport;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Notifications\CierreDiarioGeneradoNotification;

/**
 * Cierre diario de Mesa de Servicio (Fase 4). Procesa por defecto "ayer"
 * (today()->subDay()), consultando TODO lo creado ese día en sdp_tickets
 * SIN filtrar por estado — decisión de negocio ya cerrada (ver
 * docs/mesaservicio-progreso.md): un ticket que sigue abierto también cuenta
 * para el cierre del día en que se creó. El filtrado por estado es cosa de
 * otras pantallas (dashboard, ficha de técnico), no de este cierre.
 *
 * Acepta un argumento opcional `fecha` (Y-m-d) para poder re-generar
 * manualmente el cierre de un día pasado (ej. si se corrigió algo en SDP
 * después de la corrida automática) — no estaba explícito en el encargo,
 * se agregó por ser una operación de bajo riesgo: busca el `SdpReport`
 * existente para ese tipo+periodo y lo reemplaza en vez de duplicarlo (ver
 * Concerns\GeneratesCierreReports::guardarReporte()).
 *
 * La generación del Excel, la agrupación técnico/estado y el envío de la
 * notificación (rol supervisor + correos sueltos) viven en
 * Concerns\GeneratesCierreReports (Fase 5), compartido con
 * MonthlyCloseCommand — extraído al escribir el segundo comando de cierre en
 * vez de duplicar una vez más todo este código (mismo criterio ya aplicado
 * en Fase 2 con PaginatesSdpResults).
 */
class DailyCloseCommand extends Command
{
    use GeneratesCierreReports;

    /**
     * Duplicado deliberadamente en vez de referenciar
     * MesaServicioDatabaseSeeder::ROL_SUPERVISOR desde código de runtime —
     * mismo patrón ya usado en Modules\MesaServicio\Livewire\Catalogos\Destinatarios
     * y en Console\Commands\MonthlyCloseCommand (Fase 5).
     */
    public const ROL_SUPERVISOR = 'Supervisor Mesa de Servicio';

    protected $signature = 'sdp:daily-close {fecha? : Día a cerrar (Y-m-d), por defecto ayer}';

    protected $description = 'Genera el cierre diario de Mesa de Servicio (Excel + registro + notificación)';

    public function handle(): int
    {
        $fechaArg = $this->argument('fecha');

        try {
            $periodo = $fechaArg ? Carbon::parse($fechaArg)->startOfDay() : today()->subDay()->startOfDay();
        } catch (\Throwable) {
            $this->error("Fecha inválida: {$fechaArg}. Usa el formato Y-m-d.");

            return self::FAILURE;
        }

        $tickets = SdpTicket::with(['technician', 'ticketStatus'])
            ->whereBetween('created_time', [$periodo->copy()->startOfDay(), $periodo->copy()->endOfDay()])
            ->orderBy('created_time')
            ->get();

        $resumen = $this->construirResumenBase($tickets);
        $rutaArchivo = "mesa-servicio/reportes/diario/{$periodo->toDateString()}.xlsx";

        $this->generarExcelCierre(
            $rutaArchivo,
            $periodo->toDateString(),
            $tickets,
            $resumen,
            'Total de tickets del día'
        );

        $report = $this->guardarReporte(SdpReport::TIPO_DIARIO, $periodo->toDateString(), $rutaArchivo, $resumen);

        $this->notificarSupervisoresYCorreos(self::ROL_SUPERVISOR, new CierreDiarioGeneradoNotification($report));

        $this->info("Cierre diario generado para {$periodo->toDateString()} — {$tickets->count()} ticket(s).");

        return self::SUCCESS;
    }
}
