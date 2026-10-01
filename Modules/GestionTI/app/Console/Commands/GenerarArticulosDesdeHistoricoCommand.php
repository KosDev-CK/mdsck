<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionTI\Models\Almacenamiento;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\CategoriaArticulo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Procesador;
use Modules\GestionTI\Models\Ram;
use Modules\GestionTI\Models\TipoEquipo;

/**
 * Backfill único (idempotente) del catálogo unificado de Artículos a partir
 * de los `Asset` históricos importados por `gestionti:importar-historico`
 * (ver docs/gestionti-progreso.md, entrada "Catálogo unificado de
 * Artículos").
 *
 * Agrupa los Asset sin `articulo_id` por combinación exacta de
 * Tipo+Marca+Modelo. Por cada combinación única, crea (o reutiliza, si ya
 * existe de una corrida anterior) un `ArticuloSolicitud` con `categoria_id`
 * apuntando a la categoría real `'laptops_desktops'` y `es_inventariable = true` (decisión
 * confirmada con el usuario — todo lo que sale de este backfill es equipo de
 * cómputo físico real), usando como ficha técnica "representativa" la
 * combinación (procesador, ram, almacenamiento) MÁS FRECUENTE entre los
 * Asset del grupo (tomada de `especificaciones->disco_duro` para
 * `almacenamiento` — el Asset no tiene columna propia). Desde que
 * `procesador`/`ram`/`almacenamiento` son catálogos reales en
 * `ArticuloSolicitud` (ver docs/gestionti-progreso.md, entrada "Catálogos de
 * Procesador/RAM/Almacenamiento"), el valor representativo se resuelve con
 * `firstOrCreate(['nombre' => $valor])` contra `Procesador`/`Ram`/
 * `Almacenamiento` y se guarda el `_id` resultante. El Artículo es una
 * plantilla, no una copia exacta por unidad: no importa que un Asset
 * individual no calce con la combinación elegida.
 *
 * Todos los Asset del grupo (los que ya generaron el Artículo y los que lo
 * reutilizan) quedan vinculados vía `articulo_id`.
 *
 * Idempotente por construcción: la query base solo toma Asset con
 * `articulo_id` nulo, así que una segunda corrida no encuentra nada que
 * procesar una vez que la primera ya vinculó todo — no depende de ningún
 * flag ni tabla de control adicional.
 */
class GenerarArticulosDesdeHistoricoCommand extends Command
{
    protected $signature = 'gestionti:generar-articulos-desde-historico';

    protected $description = 'Genera Artículos del catálogo (categoría Laptops/Desktops) a partir de los Assets históricos agrupados por Tipo+Marca+Modelo, y vincula esos Assets al Artículo correspondiente.';

    public function handle(): int
    {
        $assets = Asset::query()
            ->whereNotNull('tipo_equipo_id')
            ->whereNotNull('marca_id')
            ->whereNotNull('modelo_id')
            ->whereNull('articulo_id')
            ->get(['id', 'tipo_equipo_id', 'marca_id', 'modelo_id', 'especificaciones']);

        if ($assets->isEmpty()) {
            $this->info('No hay Assets pendientes de vincular (ya tienen articulo_id, o les falta Tipo/Marca/Modelo).');

            return self::SUCCESS;
        }

        $grupos = $assets->groupBy(
            fn (Asset $asset) => $asset->tipo_equipo_id.'|'.$asset->marca_id.'|'.$asset->modelo_id
        );

        $nombresTipoEquipo = TipoEquipo::query()->pluck('nombre', 'id');
        $nombresMarca = Marca::query()->pluck('nombre', 'id');
        $nombresModelo = Modelo::query()->pluck('nombre', 'id');

        $articulosCreados = 0;
        $articulosReutilizados = 0;
        $assetsVinculados = 0;

        // Resuelto una sola vez fuera del loop — mismo `id` estable para
        // todo el comando, el slug `'laptops_desktops'` es la clave interna
        // protegida de `CategoriaArticulo` (ver su docblock).
        $categoriaLaptopsDesktopsId = CategoriaArticulo::where('slug', 'laptops_desktops')->value('id');

        DB::transaction(function () use (
            $grupos,
            $nombresTipoEquipo,
            $nombresMarca,
            $nombresModelo,
            $categoriaLaptopsDesktopsId,
            &$articulosCreados,
            &$articulosReutilizados,
            &$assetsVinculados
        ): void {
            foreach ($grupos as $grupo) {
                /** @var Asset $primero */
                $primero = $grupo->first();

                $tipoEquipoId = $primero->tipo_equipo_id;
                $marcaId = $primero->marca_id;
                $modeloId = $primero->modelo_id;

                $nombreTipo = $nombresTipoEquipo[$tipoEquipoId] ?? 'Equipo';
                $nombreMarca = $nombresMarca[$marcaId] ?? 'Marca';
                $nombreModelo = $nombresModelo[$modeloId] ?? 'Modelo';

                [$procesador, $ram, $almacenamiento] = $this->specsRepresentativos($grupo);

                $articulo = ArticuloSolicitud::firstOrCreate(
                    [
                        'tipo_equipo_id' => $tipoEquipoId,
                        'marca_id' => $marcaId,
                        'modelo_id' => $modeloId,
                    ],
                    [
                        'codigo' => $this->generarCodigo($nombreTipo, $nombreMarca, $nombreModelo),
                        'descripcion' => trim("{$nombreTipo} {$nombreMarca} {$nombreModelo}"),
                        'unidad_medida' => 'pieza',
                        'categoria_id' => $categoriaLaptopsDesktopsId,
                        'es_inventariable' => true,
                        'activo' => true,
                        'procesador_id' => $procesador !== null ? Procesador::firstOrCreate(['nombre' => $procesador])->id : null,
                        'ram_id' => $ram !== null ? Ram::firstOrCreate(['nombre' => $ram])->id : null,
                        'almacenamiento_id' => $almacenamiento !== null ? Almacenamiento::firstOrCreate(['nombre' => $almacenamiento])->id : null,
                    ]
                );

                if ($articulo->wasRecentlyCreated) {
                    $articulosCreados++;
                } else {
                    $articulosReutilizados++;
                }

                // whereNull('articulo_id') de nuevo aquí (no solo en la query
                // base): asegura que, si el mismo grupo ya tenía ALGUNOS
                // Asset vinculados de una corrida anterior (ej. el comando se
                // interrumpió a la mitad), no se pisen esos articulo_id ya
                // asignados — aunque en la práctica siempre coincidirían con
                // el mismo Artículo, vía firstOrCreate.
                $vinculados = Asset::query()
                    ->where('tipo_equipo_id', $tipoEquipoId)
                    ->where('marca_id', $marcaId)
                    ->where('modelo_id', $modeloId)
                    ->whereNull('articulo_id')
                    ->update(['articulo_id' => $articulo->id]);

                $assetsVinculados += $vinculados;
            }
        });

        $this->info('Generación de Artículos desde histórico completada.');
        $this->table(['Métrica', 'Valor'], [
            ['Artículos creados', $articulosCreados],
            ['Artículos reutilizados (ya existían de una corrida anterior)', $articulosReutilizados],
            ['Assets vinculados a un Artículo', $assetsVinculados],
        ]);

        return self::SUCCESS;
    }

    /**
     * Combinación (procesador, ram, almacenamiento) más frecuente dentro del
     * grupo, calculada a partir de `Asset->especificaciones` (json). Un
     * empate lo gana la primera combinación encontrada recorriendo el grupo
     * ordenado por id (criterio determinista, confirmado con el usuario).
     * Los Asset sin `especificaciones`, o con procesador/ram/disco_duro
     * todos vacíos, no participan en el conteo.
     *
     * @param  Collection<int, Asset>  $grupo
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function specsRepresentativos(Collection $grupo): array
    {
        $conteos = [];

        foreach ($grupo->sortBy('id') as $asset) {
            $especificaciones = $asset->especificaciones;

            if (! is_array($especificaciones)) {
                continue;
            }

            $procesador = $especificaciones['procesador'] ?? null;
            $ram = $especificaciones['ram'] ?? null;
            $almacenamiento = $especificaciones['disco_duro'] ?? null;

            if ($procesador === null && $ram === null && $almacenamiento === null) {
                continue;
            }

            $clave = serialize([$procesador, $ram, $almacenamiento]);

            if (! isset($conteos[$clave])) {
                $conteos[$clave] = [
                    'combo' => [$procesador, $ram, $almacenamiento],
                    'count' => 0,
                ];
            }

            $conteos[$clave]['count']++;
        }

        if ($conteos === []) {
            return [null, null, null];
        }

        $mejor = null;

        foreach ($conteos as $entry) {
            // Comparación estricta ">" (no ">="): en un empate gana la
            // primera combinación insertada, que por el sortBy('id') de
            // arriba es la del Asset con menor id que la trae.
            if ($mejor === null || $entry['count'] > $mejor['count']) {
                $mejor = $entry;
            }
        }

        return $mejor['combo'];
    }

    private function generarCodigo(string $nombreTipo, string $nombreMarca, string $nombreModelo): string
    {
        $codigo = sprintf(
            'ART-%s-%s-%s',
            Str::upper(Str::slug($nombreTipo)),
            Str::upper(Str::slug($nombreMarca)),
            Str::upper(Str::slug($nombreModelo))
        );

        return Str::limit($codigo, 190, '');
    }
}
