<?php

namespace Modules\GestionTI\Http\Controllers\PresupuestoProyectos;

use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Support\Exports\StreamsXlsxDownloads;

/**
 * "Exportar a Excel" de Presupuesto por Proyecto — una sola hoja con los
 * artículos del proyecto (categoría/descripción/cantidad/responsable/costo
 * unitario/subtotal calculado), disponible en cualquier estatus del
 * proyecto. Mismo patrón de controller+ruta que los 4 exports de catálogos
 * (`Modules\GestionTI\Support\Exports\StreamsXlsxDownloads`).
 *
 * Es un formato genérico placeholder, NO la plantilla corporativa (pendiente
 * de que el usuario la comparta, ver docs/gestionti-progreso.md, tabla de
 * insumos pendientes) — mismo criterio que el placeholder de cláusula legal
 * de la responsiva en la etapa de Asignación (Fase 3, etapa 4).
 */
class ExportController extends Controller
{
    use StreamsXlsxDownloads;

    public function __invoke(ProyectoPresupuesto $proyectoPresupuesto)
    {
        $proyectoPresupuesto->load('articulos.responsableCosto');

        $headers = ['Categoría', 'Descripción', 'Cantidad', 'Responsable', 'Costo unitario', 'Subtotal'];

        $rows = $proyectoPresupuesto->articulos->map(function ($articulo) {
            $subtotal = $articulo->costo_unitario !== null
                ? $articulo->costo_unitario * $articulo->cantidad
                : null;

            return [
                ProyectoPresupuestoArticulo::CATEGORIA_LABELS[$articulo->categoria] ?? $articulo->categoria,
                $articulo->descripcion,
                $articulo->cantidad,
                $articulo->responsableCosto?->nombre,
                $articulo->costo_unitario,
                $subtotal,
            ];
        });

        $slug = Str::slug($proyectoPresupuesto->nombre_proyecto) ?: (string) $proyectoPresupuesto->id;

        return $this->streamXlsx("presupuesto-proyecto-{$slug}.xlsx", $headers, $rows->all());
    }
}
