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
 * Además de esos 12, sincroniza 2 catálogos jerárquicos —subcategories e
 * items— que SDP solo expone anidados (categories/{id}/subcategories y
 * categories/{id}/subcategories/{id}/items); ver sincronizarSubcategorias()/
 * sincronizarArticulos(). Con ~27 categorías / ~120 subcategorías / ~600
 * artículos son ~150 llamadas (~95 s en la instancia real), por eso la
 * pantalla web solo sincroniza los 12 planos y estos 2 se corren desde
 * consola.
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

    protected $signature = 'sdp:sync-catalogos {catalogo? : Sincroniza solo este catálogo en vez de los 14 (ver Modules\MesaServicio\Models\SdpCatalogEntry::todos())}';

    protected $description = 'Sincroniza los catálogos de configuración de ServiceDesk Plus (categorías, subcategorías, artículos, niveles, modos, prioridades, etc.) hacia sdp_catalog_entries';

    public function handle(SdpClient $client): int
    {
        $catalogoArg = $this->argument('catalogo');

        if ($catalogoArg !== null && ! in_array($catalogoArg, SdpCatalogEntry::todos(), true)) {
            $this->error("Catálogo desconocido: {$catalogoArg}. Los válidos son: ".implode(', ', SdpCatalogEntry::todos()));

            return self::FAILURE;
        }

        $catalogos = $catalogoArg !== null ? [$catalogoArg] : SdpCatalogEntry::todos();

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
        if ($catalogo === 'subcategories') {
            return $this->sincronizarSubcategorias($client);
        }

        if ($catalogo === 'items') {
            return $this->sincronizarArticulos($client);
        }

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
     * Subcategorías: se recorren las categorías YA sincronizadas localmente
     * (SDP solo las expone anidadas, una llamada por categoría) — por eso
     * "categories" debe haberse sincronizado antes (el orden de
     * SdpCatalogEntry::todos() ya lo garantiza al correr los 14).
     */
    private function sincronizarSubcategorias(SdpClient $client): int
    {
        $categorias = SdpCatalogEntry::delCatalogo('categories')->get();

        if ($categorias->isEmpty()) {
            throw new \RuntimeException('No hay categorías locales — corre primero "sdp:sync-catalogos categories".');
        }

        $total = 0;

        foreach ($categorias as $categoria) {
            $total += $this->paginar(
                fn (int $start, int $rows) => $client->listSubcategories($categoria->sdp_id, $start, $rows),
                'subcategories',
                function (array $entry) use ($categoria) {
                    $this->upsertFlatEntry('subcategories', $entry, [
                        'category_id' => $categoria->sdp_id,
                        'category_nombre' => $entry['category']['name'] ?? $categoria->nombre,
                    ]);
                },
            );
        }

        return $total;
    }

    /**
     * Artículos: una llamada por subcategoría local (SDP los expone solo bajo
     * categories/{id}/subcategories/{id}/items), usando la categoría que
     * quedó guardada en `extra` de cada subcategoría.
     */
    private function sincronizarArticulos(SdpClient $client): int
    {
        $subcategorias = SdpCatalogEntry::delCatalogo('subcategories')->get();

        if ($subcategorias->isEmpty()) {
            throw new \RuntimeException('No hay subcategorías locales — corre primero "sdp:sync-catalogos subcategories".');
        }

        $total = 0;

        foreach ($subcategorias as $subcategoria) {
            $categoriaId = $subcategoria->extra['category_id'] ?? null;

            if (! $categoriaId) {
                continue;
            }

            $total += $this->paginar(
                fn (int $start, int $rows) => $client->listItems($categoriaId, $subcategoria->sdp_id, $start, $rows),
                'items',
                function (array $entry) use ($subcategoria) {
                    $this->upsertFlatEntry('items', $entry, [
                        'category_id' => $subcategoria->extra['category_id'],
                        'category_nombre' => $subcategoria->extra['category_nombre'] ?? null,
                        'subcategory_id' => $subcategoria->sdp_id,
                        'subcategory_nombre' => $subcategoria->nombre,
                    ]);
                },
            );
        }

        return $total;
    }

    /**
     * Pagina un listado anidado y aplica $callback a cada fila; devuelve
     * cuántas filas se procesaron.
     */
    private function paginar(\Closure $fetch, string $clave, \Closure $callback): int
    {
        $startIndex = 1;
        $rowCount = 100;
        $total = 0;

        do {
            $payload = $fetch($startIndex, $rowCount);
            $entries = $payload[$clave] ?? [];

            foreach ($entries as $entry) {
                $callback($entry);
                $total++;
            }

            $hasMore = $this->hasMoreRows($payload['list_info'] ?? [], $startIndex, $rowCount, count($entries));
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
    private function upsertFlatEntry(string $catalogo, array $entry, array $extraPadre = []): void
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
            ...$extraPadre,
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
