<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;
use Throwable;

/**
 * `requisition_header_approved` — requisiciones (SIC) ya aprobadas
 * (`APPROVED`, y `REJECTED` si algún día EBS lo trae por aquí) hace `--dias`
 * días. Dispara `SIC_AUTORIZADA`/`SIC_RECHAZADA` cuando la SIC vinculada
 * transiciona de estatus en esta corrida, salvo `--sin-avisos`. Mismo
 * criterio de "no tronar" que `gestionti:ebs-sincronizar-creadas`.
 */
class EbsSincronizarAprobadasCommand extends Command
{
    protected $signature = 'gestionti:ebs-sincronizar-aprobadas {--dias=1} {--sin-avisos}';

    protected $description = 'Sincroniza desde Oracle EBS las Solicitudes Internas de Compra aprobadas hace N días (requisition_header_approved).';

    public function handle(EbsRequisitionSyncService $service): int
    {
        $dias = (int) $this->option('dias');
        $sinAvisos = (bool) $this->option('sin-avisos');

        try {
            $service->sincronizarAprobadas($dias, dispararAvisos: ! $sinAvisos);
        } catch (Throwable $e) {
            Log::error('GestionTI: fallo al sincronizar SIC aprobadas desde EBS', [
                'dias' => $dias,
                'error' => $e->getMessage(),
            ]);

            $this->error("Fallo al sincronizar requisiciones aprobadas de EBS: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Sincronización de requisiciones aprobadas de EBS (daysoffset={$dias}) completada.");

        return self::SUCCESS;
    }
}
