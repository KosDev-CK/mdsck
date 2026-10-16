<?php

namespace Modules\MesaServicio\Http\Controllers\Catalogos;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MesaServicio\Models\SdpCatalogEntry;
use Modules\MesaServicio\Support\Exports\StreamsXlsxDownloads;

/**
 * "Exportar a Excel" de la pantalla Catálogos SDP — un .xlsx con el catálogo
 * de la pestaña activa (`tab`), una sola hoja, sin paginar. Siempre incluye
 * el ID de SDP como primera columna; subcategorías y artículos separan
 * categoría/subcategoría en columnas propias (más útil en Excel que el
 * "Categoría › Subcategoría" de la pantalla), y priority_matrices/
 * closure_codes despliegan lo que guardan en `extra`.
 */
class CatalogosSdpExportController extends Controller
{
    use StreamsXlsxDownloads;

    public function __invoke(Request $request)
    {
        $tab = $request->query('tab', SdpCatalogEntry::todos()[0]);
        abort_unless(in_array($tab, SdpCatalogEntry::todos(), true), 404);

        $entradas = SdpCatalogEntry::delCatalogo($tab)->orderBy('nombre')->get();
        $estatus = fn (SdpCatalogEntry $e) => $e->activo ? 'Activo' : 'Inactivo';

        [$headers, $rows] = match ($tab) {
            'subcategories' => [
                ['ID SDP', 'Categoría', 'Subcategoría', 'Descripción', 'Estatus'],
                $entradas->map(fn ($e) => [$e->sdp_id, $e->extra['category_nombre'] ?? '', $e->nombre, $e->descripcion, $estatus($e)]),
            ],
            'items' => [
                ['ID SDP', 'Categoría', 'Subcategoría', 'Artículo', 'Descripción', 'Estatus'],
                $entradas->map(fn ($e) => [$e->sdp_id, $e->extra['category_nombre'] ?? '', $e->extra['subcategory_nombre'] ?? '', $e->nombre, $e->descripcion, $estatus($e)]),
            ],
            'priority_matrices' => [
                ['Urgencia', 'Impacto', 'Prioridad resultante', 'Color'],
                $entradas->map(fn ($e) => [$e->extra['urgency']['name'] ?? '', $e->extra['impact']['name'] ?? '', $e->extra['priority']['name'] ?? '', $e->color]),
            ],
            'slas' => [
                ['ID SDP', 'Nombre', 'Descripción', 'Sitio', 'Orden', 'Aplica a servicios', 'Primera respuesta (min)', 'Resolución (min)', 'Criterios', 'Ignora fines de semana', 'Ignora feriados', 'Ignora horario operativo', 'Estatus'],
                $entradas->map(fn ($e) => [
                    $e->sdp_id, $e->nombre, $e->descripcion, $e->extra['sitio'] ?? '', $e->extra['orden'] ?? '',
                    ! empty($e->extra['es_servicio']) ? 'Sí' : 'No',
                    $e->extra['respuesta_minutos'] ?? '', $e->extra['resolucion_minutos'] ?? '',
                    $e->extra['criterios_texto'] ?? 'Todas las solicitudes',
                    ! empty($e->extra['ignora_fines_de_semana']) ? 'Sí' : 'No',
                    ! empty($e->extra['ignora_feriados']) ? 'Sí' : 'No',
                    ! empty($e->extra['ignora_horario_operativo']) ? 'Sí' : 'No',
                    $estatus($e),
                ]),
            ],
            'closure_codes' => [
                ['ID SDP', 'Nombre', 'Descripción', 'Módulo', 'Estatus'],
                $entradas->map(fn ($e) => [$e->sdp_id, $e->nombre, $e->descripcion, $e->extra['module']['name'] ?? '', $estatus($e)]),
            ],
            default => [
                ['ID SDP', 'Nombre', 'Descripción', 'Color', 'Estatus'],
                $entradas->map(fn ($e) => [$e->sdp_id, $e->nombre, $e->descripcion, $e->color, $estatus($e)]),
            ],
        };

        return $this->streamXlsx("catalogos-sdp-{$tab}.xlsx", $headers, $rows->all());
    }
}
