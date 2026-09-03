<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;
use Throwable;

/**
 * `requisition_header_line` — todas las requisiciones (SIC) creadas hace
 * `--dias` días, sin importar estatus. Se corre siempre antes de
 * `gestionti:ebs-sincronizar-aprobadas` (crea el registro base que ese
 * comando completa). Un fallo de EBS (API caída, errorCode != 0, etc.) se
 * registra en el log y el comando termina en FAILURE sin excepción sin
 * capturar — nunca debe afectar el flujo manual existente.
 */
class EbsSincronizarCreadasCommand extends Command
{
    protected $signature = 'gestionti:ebs-sincronizar-creadas {--dias=1}';

    protected $description = 'Sincroniza desde Oracle EBS las Solicitudes Internas de Compra creadas hace N días (requisition_header_line).';

    public function handle(EbsRequisitionSyncService $service): int
    {
        $dias = (int) $this->option('dias');

        try {
            $service->sincronizarCreadas($dias);
        } catch (Throwable $e) {
            Log::error('GestionTI: fallo al sincronizar SIC creadas desde EBS', [
                'dias' => $dias,
                'error' => $e->getMessage(),
            ]);

            $this->error("Fallo al sincronizar requisiciones creadas de EBS: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Sincronización de requisiciones creadas de EBS (daysoffset={$dias}) completada.");

        return self::SUCCESS;
    }
}
