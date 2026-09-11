<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Modules\MesaServicio\Console\Commands\Concerns\PaginatesSdpResults;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Modules\MesaServicio\Services\SdpClient;

class SyncTicketStatusesCommand extends Command
{
    use PaginatesSdpResults;

    protected $signature = 'sdp:sync-ticket-statuses';

    protected $description = 'Sincroniza el catálogo de estados de solicitud desde ServiceDesk Plus (sdp_ticket_statuses)';

    public function handle(SdpClient $client): int
    {
        $startIndex = 1;
        $rowCount = 100;
        $total = 0;

        do {
            try {
                $payload = $client->listStatuses($startIndex, $rowCount);
            } catch (\Throwable $e) {
                $this->error("No se pudo obtener el catálogo de estados: {$e->getMessage()}");

                return self::FAILURE;
            }

            $statuses = $payload['statuses'] ?? [];

            foreach ($statuses as $status) {
                if (empty($status['id'])) {
                    continue;
                }

                SdpTicketStatus::updateOrCreate(
                    ['sdp_id' => $status['id']],
                    [
                        'nombre' => $status['name'] ?? '',
                        'internal_name' => $status['internal_name'] ?? null,
                        'tipo' => ($status['in_progress'] ?? false)
                            ? SdpTicketStatus::TIPO_EN_CURSO
                            : SdpTicketStatus::TIPO_COMPLETADO,
                        'activo' => ! ($status['deleted'] ?? false),
                    ]
                );

                $total++;
            }

            $listInfo = $payload['list_info'] ?? [];
            $hasMore = $this->hasMoreRows($listInfo, $startIndex, $rowCount, count($statuses));

            $startIndex += $rowCount;
        } while ($hasMore);

        $this->info("Estados sincronizados: {$total}.");

        return self::SUCCESS;
    }
}
