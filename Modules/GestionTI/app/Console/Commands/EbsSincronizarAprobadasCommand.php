<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\GestionTI\Models\EbsSyncFailure;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncException;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;
use Throwable;

/**
 * `requisition_header_approved` — requisiciones (SIC) ya aprobadas
 * (`APPROVED`, y `REJECTED` si algún día EBS lo trae por aquí) hace `--dias`
 * días. Dispara `SIC_AUTORIZADA`/`SIC_RECHAZADA` cuando la SIC vinculada
 * transiciona de estatus en esta corrida, salvo `--sin-avisos`. Mismo
 * criterio de "no tronar" (y de registrar/resolver `EbsSyncFailure`, ver
 * `gestionti:ebs-reintentar-fallidos`) que `gestionti:ebs-sincronizar-creadas`.
 */
class EbsSincronizarAprobadasCommand extends Command
{
    protected $signature = 'gestionti:ebs-sincronizar-aprobadas {--dias=1} {--sin-avisos}';

    protected $description = 'Sincroniza desde Oracle EBS las Solicitudes Internas de Compra aprobadas hace N días (requisition_header_approved).';

    public function handle(EbsRequisitionSyncService $service): int
    {
        $dias = (int) $this->option('dias');
        $sinAvisos = (bool) $this->option('sin-avisos');
        $fecha = now()->subDays($dias)->startOfDay();

        try {
            $service->sincronizarAprobadas($dias, dispararAvisos: ! $sinAvisos);
        } catch (Throwable $e) {
            $errorCode = $e instanceof EbsRequisitionSyncException ? $e->errorCode : null;
            $errorMsg = $e instanceof EbsRequisitionSyncException ? $e->errorMsg : $e->getMessage();

            Log::error('GestionTI: fallo al sincronizar SIC aprobadas desde EBS', [
                'dias' => $dias,
                'error' => $e->getMessage(),
            ]);

            EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_APROBADAS, $errorCode, $errorMsg);

            $this->error("Fallo al sincronizar requisiciones aprobadas de EBS: {$e->getMessage()}");

            return self::FAILURE;
        }

        EbsSyncFailure::resolverSiPendiente($fecha, EbsRequisitionsClient::METHOD_APROBADAS);

        $this->info("Sincronización de requisiciones aprobadas de EBS (daysoffset={$dias}) completada.");

        return self::SUCCESS;
    }
}
