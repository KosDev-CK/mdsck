<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Modules\MesaServicio\Console\Commands\Concerns\PaginatesSdpResults;
use Modules\MesaServicio\Models\SdpCatalogEntry;
use Modules\MesaServicio\Services\SdpClient;

/**
 * Fase 8 (Parte 2) — sincroniza los 12 catálogos de configuración ("setup")
 * de SDP hacia sdp_catalog_entries: categories, levels, modes, impacts,
 * urgencies, priorities, priority_matrices, request_types, task_types,
 * worklog_types, closure_codes, downtime_types. Todos confirmados contra la
 * instancia real (HTTP 200, sin necesitar search_criteria/fields_required).
 *
 * Manual-only, deliberadamente NO agregado a
 * MesaServicioServiceProvider::configureSchedules() — mismo criterio que
 * sdp:sync-sites: catálogos de referencia de muy baja frecuencia de cambio.
 *
 * Forma de la respuesta, por catálogo:
 * - La mayoría: array plano bajo una clave de nivel superior con el mismo
 *   nombre que el recurso (ej. {"modes": [{"id","name","description",
 *   "deleted", ...}]}), algunos con "color"/"internal_name" adicionales.
 * - "closure_codes": usa "inactive" en vez de "deleted", y trae un
 *   sub-objeto "module" (ej. {"api_plural_name": "requests", "name":
 *   "request"}) — se guarda TAL CUAL para cualquier módulo (requests,
 *   problems, changes...), sin filtrar, solo se deja visible en `extra`.
 * - "priority_matrices": estructuralmente distinto — sin id/name propios,
 *   un set de triples {"urgency", "impact", "priority"} que mapean una
 *   combinación urgencia+impacto a la prioridad resultante. Ver
 *   upsertPriorityMatrixEntry().
 */
class SyncCatalogosCommand extends Command
{
    use PaginatesSdpResults;

    protected $signature = 'sdp:sync-catalogos {catalogo? : Sincroniza solo este catálogo en vez de los 12 (ver Modules\MesaServicio\Models\SdpCatalogEntry::CATALOGOS)}';

    protected $description = 'Sincroniza los catálogos de configuración de ServiceDesk Plus (categorías, niveles, modos, prioridades, etc.) hacia sdp_catalog_entries';

    public function handle(SdpClient $client): int
    {
        $catalogoArg = $this->argument('catalogo');

        if ($catalogoArg !== null && ! in_array($catalogoArg, SdpCatalogEntry::CATALOGOS, true)) {
            $this->error("Catálogo desconocido: {$catalogoArg}. Los válidos son: ".implode(', ', SdpCatalogEntry::CATALOGOS));

            return self::FAILURE;
        }

        $catalogos = $catalogoArg !== null ? [$catalogoArg] : SdpCatalogEntry::CATALOGOS;

        foreach ($catalogos as $catalogo) {
            try {
                $total = $this->sincronizarCatalogo($client, $catalogo);
            } catch (\Throwable $e) {
                $this->error("No se pudo sincronizar el catálogo \"{$catalogo}\": {$e->getMessage()}");

                return self::FAILURE;
            }

            $this->info("Catálogo \"{$catalogo}\" sincronizado: {$total} registro(s).");
        }

        return self::SUCCESS;
    }

    private function sincronizarCatalogo(SdpClient $client, string $catalogo): int
    {
        $startIndex = 1;
        $rowCount = 100;
        $total = 0;

        do {
            $payload = $client->listCatalog($catalogo, $startIndex, $rowCount);

            $entries = $payload[$catalogo] ?? [];

            foreach ($entries as $entry) {
                if ($catalogo === 'priority_matrices') {
                    $this->upsertPriorityMatrixEntry($entry);
                } else {
                    $this->upsertFlatEntry($catalogo, $entry);
                }

                $total++;
            }

            $listInfo = $payload['list_info'] ?? [];
            $hasMore = $this->hasMoreRows($listInfo, $startIndex, $rowCount, count($entries));

            $startIndex += $rowCount;
        } while ($hasMore);

        return $total;
    }

    /**
     * Catálogos "planos" (todos menos priority_matrices): id/name propios,
     * "deleted" (bool) casi todos, "closure_codes" usa "inactive" en su
     * lugar y trae un sub-objeto "module" que se conserva tal cual en
     * `extra` para dar contexto (sin filtrar por module.api_plural_name).
     */
    private function upsertFlatEntry(string $catalogo, array $entry): void
    {
        if (empty($entry['id'])) {
            return;
        }

        $activo = $catalogo === 'closure_codes'
            ? ! ($entry['inactive'] ?? false)
            : ! ($entry['deleted'] ?? false);

        $extra = array_filter([
            'internal_name' => $entry['internal_name'] ?? null,
            'module' => $entry['module'] ?? null,
        ], fn ($value) => $value !== null);

        SdpCatalogEntry::updateOrCreate(
            ['catalogo' => $catalogo, 'sdp_id' => (string) $entry['id']],
            [
                'nombre' => $entry['name'] ?? '',
                'descripcion' => $entry['description'] ?? null,
                'color' => $entry['color'] ?? null,
                'activo' => $activo,
                'extra' => $extra !== [] ? $extra : null,
            ]
        );
    }

    /**
     * priority_matrices no trae id/name propios — cada entrada es un triple
     * {"urgency": {...}, "impact": {...}, "priority": {...}} que mapea una
     * combinación urgencia+impacto a la prioridad resultante.
     *
     * `sdp_id` se sintetiza de forma determinista (md5 de urgency_id +
     * impact_id) porque la constraint única (catalogo, sdp_id) de
     * sdp_catalog_entries exige un valor no-null uniforme para los 12
     * catálogos — la combinación urgencia+impacto ya es única por
     * definición en SDP (una sola prioridad resultante por combinación), así
     * que el hash es estable entre corridas (idempotente) sin depender de un
     * id que SDP no expone para esta entrada.
     */
    private function upsertPriorityMatrixEntry(array $entry): void
    {
        $urgency = $entry['urgency'] ?? [];
        $impact = $entry['impact'] ?? [];
        $priority = $entry['priority'] ?? [];

        if (empty($urgency['id']) || empty($impact['id'])) {
            return;
        }

        $sdpId = md5($urgency['id'].'|'.$impact['id']);
        $nombre = sprintf(
            '%s + %s → %s',
            $urgency['name'] ?? '(sin urgencia)',
            $impact['name'] ?? '(sin impacto)',
            $priority['name'] ?? '(sin prioridad)'
        );

        SdpCatalogEntry::updateOrCreate(
            ['catalogo' => 'priority_matrices', 'sdp_id' => $sdpId],
            [
                'nombre' => $nombre,
                'descripcion' => null,
                'color' => $priority['color'] ?? null,
                'activo' => true,
                'extra' => [
                    'urgency' => $urgency,
                    'impact' => $impact,
                    'priority' => $priority,
                ],
            ]
        );
    }
}
