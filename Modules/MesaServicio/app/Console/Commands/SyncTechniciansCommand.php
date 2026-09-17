<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Modules\MesaServicio\Console\Commands\Concerns\PaginatesSdpResults;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Services\SdpClient;

class SyncTechniciansCommand extends Command
{
    use PaginatesSdpResults;

    protected $signature = 'sdp:sync-technicians';

    protected $description = 'Deriva el catálogo local de técnicos (sdp_technicians) del objeto "technician" embebido en los tickets de los últimos 12 meses';

    /**
     * No existe un recurso "technicians" en la API v3 de SDP (ver SdpClient)
     * — el catálogo se deriva/deduplica del objeto "technician" embebido en
     * cada ticket devuelto por listRequests().
     *
     * NOTA (formato de search_criteria sin verificar contra la API real):
     * no fue posible confirmar en esta sesión el formato exacto que SDP v3
     * espera para filtrar por fecha de creación. Se implementa con
     * ['field' => 'created_time', 'condition' => 'after', 'value' => <ms>]
     * por ser el formato indicado explícitamente como mejor entendimiento
     * disponible — revisar contra la instancia real (o la documentación de
     * SDP v3) antes de confiar en el filtrado de 12 meses en producción. Si
     * el campo/condición no es el correcto, la sintomatología esperada es
     * que SDP devuelva un error 400 o ignore el filtro y traiga todo el
     * histórico — en ambos casos el comando seguiría funcionando para
     * técnicos activos, solo se perdería la acotación a 12 meses.
     */
    public function handle(SdpClient $client): int
    {
        $cutoffMs = (string) now()->subMonths(12)->getTimestampMs();

        $searchCriteria = [
            ['field' => 'created_time', 'condition' => 'after', 'value' => $cutoffMs],
        ];

        $startIndex = 1;
        $rowCount = 100;
        $seenSdpIds = [];
        $ticketsSeen = 0;

        do {
            try {
                $payload = $client->listRequests($searchCriteria, ['technician'], $startIndex, $rowCount);
            } catch (\Throwable $e) {
                $this->error("No se pudo obtener tickets para derivar técnicos: {$e->getMessage()}");

                return self::FAILURE;
            }

            $requests = $payload['requests'] ?? [];

            foreach ($requests as $request) {
                $technician = $request['technician'] ?? null;

                if (empty($technician['id'])) {
                    continue;
                }

                SdpTechnician::updateOrCreate(
                    ['sdp_id' => $technician['id']],
                    [
                        'nombre' => $technician['name'] ?? '',
                        'correo' => $technician['email_id'] ?? null,
                        'puesto' => $technician['job_title'] ?? null,
                        'activo' => true,
                    ]
                );

                $seenSdpIds[] = $technician['id'];
            }

            $ticketsSeen += count($requests);

            $listInfo = $payload['list_info'] ?? [];
            $hasMore = $this->hasMoreRows($listInfo, $startIndex, $rowCount, count($requests));

            $startIndex += $rowCount;
        } while ($hasMore);

        // $seenSdpIds trae una entrada por TICKET visto, no por técnico —
        // con varios miles de tickets en 12 meses, un whereNotIn() sin
        // deduplicar genera igual de miles de placeholders y MySQL lo
        // rechaza ("error 1390: Prepared statement contains too many
        // placeholders"). Deduplicar aquí lo acota al tamaño real del
        // catálogo de técnicos (decenas, no miles).
        $uniqueSdpIds = array_values(array_unique($seenSdpIds));

        $inactivated = SdpTechnician::where('activo', true)
            ->whereNotIn('sdp_id', $uniqueSdpIds)
            ->update(['activo' => false]);

        $this->info(sprintf(
            'Tickets revisados: %d. Técnicos vistos: %d. Marcados inactivos: %d.',
            $ticketsSeen,
            count($uniqueSdpIds),
            $inactivated
        ));

        return self::SUCCESS;
    }
}
