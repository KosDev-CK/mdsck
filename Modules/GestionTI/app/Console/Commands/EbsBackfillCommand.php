<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;
use Throwable;

/**
 * Recorre día por día desde `--desde` (YYYY-MM-DD) hasta hoy, calculando el
 * `daysoffset` correcto para cada día (offset = días entre esa fecha y
 * hoy) y sincronizando creadas + aprobadas de EBS para cada uno.
 *
 * NUNCA dispara avisos, sin excepción — es intencional, para no inundar de
 * avisos retroactivos por aprobaciones de meses atrás. Tolera que un solo
 * día falle (log + mensaje en consola) sin abortar los días restantes — no
 * se ejecuta contra producción desde este comando salvo confirmación
 * explícita del usuario (ver docs/gestionti-progreso.md).
 */
class EbsBackfillCommand extends Command
{
    protected $signature = 'gestionti:ebs-backfill {--desde=}';

    protected $description = 'Recorre día por día desde --desde=YYYY-MM-DD hasta hoy, sincronizando SIC creadas/aprobadas de EBS sin disparar avisos.';

    public function handle(EbsRequisitionSyncService $service): int
    {
        $desde = $this->option('desde');

        if (! $desde) {
            $this->error('Debes indicar --desde=YYYY-MM-DD.');

            return self::FAILURE;
        }

        try {
            $fechaInicio = Carbon::createFromFormat('Y-m-d', $desde)->startOfDay();
        } catch (Throwable) {
            $this->error("Fecha inválida: \"{$desde}\". Usa el formato YYYY-MM-DD.");

            return self::FAILURE;
        }

        $hoy = now()->startOfDay();

        if ($fechaInicio->greaterThan($hoy)) {
            $this->error('--desde no puede ser una fecha futura.');

            return self::FAILURE;
        }

        for ($fecha = $fechaInicio->copy(); $fecha->lessThanOrEqualTo($hoy); $fecha->addDay()) {
            // Carbon 3 cambió el default de diffInDays() a una diferencia
            // con signo (ya no absoluta) — forzamos abs() explícito, el
            // offset siempre es >= 0 (hoy siempre es fecha, o posterior).
            $offset = abs($hoy->diffInDays($fecha));

            try {
                // dispararAvisos SIEMPRE false aquí, sin importar ninguna
                // opción futura — regla explícita del backfill.
                $service->sincronizarCreadas($offset);
                $service->sincronizarAprobadas($offset, dispararAvisos: false);

                $this->info("Backfill EBS: {$fecha->toDateString()} (daysoffset={$offset}) — OK.");
            } catch (Throwable $e) {
                Log::error('GestionTI: fallo en backfill de EBS', [
                    'fecha' => $fecha->toDateString(),
                    'daysoffset' => $offset,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Backfill EBS: {$fecha->toDateString()} (daysoffset={$offset}) — FALLÓ, ver logs: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
