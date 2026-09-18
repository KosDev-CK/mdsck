<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Modules\MesaServicio\Console\Commands\Concerns\PaginatesSdpResults;
use Modules\MesaServicio\Models\SdpSite;
use Modules\MesaServicio\Services\SdpClient;

/**
 * Sincroniza el catálogo local de sitios (sdp_sites) desde SDP (Fase 8) — a
 * diferencia de sdp:sync-tickets, este comando NO está en el scheduler: los
 * sitios cambian rara vez, mismo criterio (manual-only) que tenía
 * sdp:sync-tickets antes de reactivarse el 2026-09-17. Se corre a mano
 * (consola, o un futuro botón) cuando haga falta refrescar el catálogo.
 */
class SyncSitesCommand extends Command
{
    use PaginatesSdpResults;

    protected $signature = 'sdp:sync-sites';

    protected $description = 'Sincroniza el catálogo local de sitios (sdp_sites) desde ServiceDesk Plus';

    public function handle(SdpClient $client): int
    {
        $startIndex = 1;
        $rowCount = 100;
        $total = 0;

        do {
            try {
                $payload = $client->listSites($startIndex, $rowCount);
            } catch (\Throwable $e) {
                $this->error("No se pudo obtener el catálogo de sitios: {$e->getMessage()}");

                return self::FAILURE;
            }

            $sites = $payload['sites'] ?? [];

            foreach ($sites as $site) {
                if (empty($site['id'])) {
                    continue;
                }

                SdpSite::updateOrCreate(
                    ['sdp_id' => $site['id']],
                    [
                        'nombre' => $site['name'] ?? '',
                        'pais' => $site['country'] ?? null,
                        'estado' => $site['state'] ?? null,
                        'region' => $site['region'] ?? null,
                        'ciudad' => $site['city'] ?? null,
                        'calle' => $site['street'] ?? null,
                        'numero_puerta' => $site['door_no'] ?? null,
                        'codigo_postal' => $site['postal_code'] ?? null,
                        'localidad' => $site['location'] ?? null,
                        'punto_referencia' => $site['landmark'] ?? null,
                        'zona_horaria' => $site['timezone'] ?? null,
                    ]
                );

                $total++;
            }

            $listInfo = $payload['list_info'] ?? [];
            $hasMore = $this->hasMoreRows($listInfo, $startIndex, $rowCount, count($sites));

            $startIndex += $rowCount;
        } while ($hasMore);

        $this->info("Sitios sincronizados: {$total}.");

        return self::SUCCESS;
    }
}
