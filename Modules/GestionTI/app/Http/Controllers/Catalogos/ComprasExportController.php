<?php

namespace Modules\GestionTI\Http\Controllers\Catalogos;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\CategoriaArticulo;
use Modules\GestionTI\Models\EbsArticulo;
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Support\Exports\StreamsXlsxDownloads;

/**
 * "Exportar a Excel" de la pantalla Catálogos de Compras — un .xlsx con las
 * mismas columnas mostradas en la pestaña activa (`tab`), respetando el
 * filtro de búsqueda si estaba activo, sin paginar.
 */
class ComprasExportController extends Controller
{
    use StreamsXlsxDownloads;

    private const CATALOGOS = [
        'proveedores' => ['model' => Proveedor::class, 'orderBy' => 'nombre_comercial', 'searchColumns' => ['razon_social', 'nombre_comercial', 'rfc', 'contacto_nombre']],
        'articulos_solicitud' => ['model' => ArticuloSolicitud::class, 'orderBy' => 'codigo', 'searchColumns' => ['codigo', 'descripcion']],
        'categorias' => ['model' => CategoriaArticulo::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'lugares_entrega' => ['model' => LugarEntrega::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'ebs_articulos' => ['model' => EbsArticulo::class, 'orderBy' => 'ebs_item_id', 'searchColumns' => ['ebs_item_id', 'ebs_item_description']],
    ];

    public function __invoke(Request $request)
    {
        $tab = $request->query('tab', 'proveedores');
        abort_unless(array_key_exists($tab, self::CATALOGOS), 404);

        $config = self::CATALOGOS[$tab];
        $search = trim((string) $request->query('search', ''));

        $records = $config['model']::query()
            ->when($tab === 'articulos_solicitud', fn ($q) => $q->with(['tipoEquipo', 'categoria']))
            ->when($tab === 'ebs_articulos', fn ($q) => $q->with('articulo'))
            ->when($search !== '', function ($q) use ($config, $search) {
                $q->where(function ($q) use ($config, $search) {
                    foreach ($config['searchColumns'] as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy($config['orderBy'])
            ->get();

        [$headers, $rows] = match ($tab) {
            'proveedores' => [
                ['Nombre comercial', 'Razón social', 'RFC', 'Contacto', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre_comercial, $r->razon_social, $r->rfc, $r->contacto_nombre, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'categorias' => [
                ['Nombre', 'Va a Compras', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre, $r->es_compra ? 'Sí' : 'No', $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'lugares_entrega' => [
                ['Nombre', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'ebs_articulos' => [
                ['Item ID (EBS)', 'Descripción en EBS', 'Artículo mapeado'],
                $records->map(fn ($r) => [$r->ebs_item_id, $r->ebs_item_description, $r->articulo ? "{$r->articulo->codigo} — {$r->articulo->descripcion}" : 'Sin mapear']),
            ],
            default => [
                ['Código', 'Descripción', 'Unidad de medida', 'Categoría', 'Tipo de equipo', 'Estatus'],
                $records->map(fn ($r) => [$r->codigo, $r->descripcion, $r->unidad_medida, $r->categoria?->nombre, $r->tipoEquipo?->nombre, $r->activo ? 'Activo' : 'Inactivo']),
            ],
        };

        return $this->streamXlsx("catalogos-compras-{$tab}.xlsx", $headers, $rows->all());
    }
}
