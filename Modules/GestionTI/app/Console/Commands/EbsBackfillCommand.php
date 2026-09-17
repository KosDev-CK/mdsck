<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\GestionTI\Models\EbsSyncFailure;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncException;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;
use Throwable;

/**
 * Recorre día por día desde `--desde` (YYYY-MM-DD) hasta hoy, calculando el
 * `daysoffset` correcto para cada día (offset = días entre esa fecha y
 * hoy) y sincronizando creadas + aprobadas de EBS para cada uno.
 *
 * NUNCA dispara avisos, sin excepción — es intencional, para no inundar de
 * avisos retroactivos por aprobaciones de meses atrás. Cada día ejecuta
 * "creadas" y "aprobadas" en 2 try/catch INDEPENDIENTES — que uno de los 2
 * falle no impide que el otro se intente (antes ambos vivían en el mismo
 * try/catch: si "creadas" tronaba, "aprobadas" de ese día ni se intentaba).
 * Cada fallo real se registra en `EbsSyncFailure` (ver
 * `gestionti:ebs-reintentar-fallidos`) — no se ejecuta contra producción
 * desde este comando salvo confirmación explícita del usuario (ver
 * docs/gestionti-progreso.md).
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

            $okCreadas = $this->sincronizarMetodo(
                fn () => $service->sincronizarCreadas($offset),
                EbsRequisitionsClient::METHOD_CREADAS,
                'creadas',
                $fecha,
                $offset,
            );

            $okAprobadas = $this->sincronizarMetodo(
                // dispararAvisos SIEMPRE false aquí, sin importar ninguna
                // opción futura — regla explícita del backfill.
                fn () => $service->sincronizarAprobadas($offset, dispararAvisos: false),
                EbsRequisitionsClient::METHOD_APROBADAS,
                'aprobadas',
                $fecha,
                $offset,
            );

            if ($okCreadas && $okAprobadas) {
                $this->info("Backfill EBS: {$fecha->toDateString()} (daysoffset={$offset}) — OK.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Ejecuta un solo método (creadas o aprobadas) de un solo día, aislado
     * de cualquier otro método/día — un fallo aquí nunca se propaga hacia
     * el bucle de `handle()`. Registra/resuelve `EbsSyncFailure` según el
     * resultado.
     */
    private function sincronizarMetodo(callable $callback, string $metodo, string $etiqueta, Carbon $fecha, int $offset): bool
    {
        try {
            $callback();

            EbsSyncFailure::resolverSiPendiente($fecha, $metodo);

            return true;
        } catch (Throwable $e) {
            $errorCode = $e instanceof EbsRequisitionSyncException ? $e->errorCode : null;
            $errorMsg = $e instanceof EbsRequisitionSyncException ? $e->errorMsg : $e->getMessage();

            Log::error('GestionTI: fallo en backfill de EBS', [
                'fecha' => $fecha->toDateString(),
                'daysoffset' => $offset,
                'metodo' => $metodo,
                'error' => $e->getMessage(),
            ]);

            EbsSyncFailure::registrar($fecha, $metodo, $errorCode, $errorMsg);

            $this->error("Backfill EBS ({$etiqueta}): {$fecha->toDateString()} (daysoffset={$offset}) — FALLÓ, ver logs: {$e->getMessage()}");

            return false;
        }
    }
}
