<?php

namespace Modules\GestionTI\Http\Controllers\Catalogos;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\ArticuloSolicitud;
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
        'articulos_solicitud' => ['model' => ArticuloSolicitud::class, 'orderBy' => 'codigo', 'searchColumns' => ['codigo', 'descripcion', 'categoria']],
    ];

    public function __invoke(Request $request)
    {
        $tab = $request->query('tab', 'proveedores');
        abort_unless(array_key_exists($tab, self::CATALOGOS), 404);

        $config = self::CATALOGOS[$tab];
        $search = trim((string) $request->query('search', ''));

        $records = $config['model']::query()
            ->when($tab === 'articulos_solicitud', fn ($q) => $q->with('tipoEquipo'))
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
            default => [
                ['Código', 'Descripción', 'Unidad de medida', 'Categoría', 'Tipo de equipo', 'Estatus'],
                $records->map(fn ($r) => [$r->codigo, $r->descripcion, $r->unidad_medida, $r->categoria, $r->tipoEquipo?->nombre, $r->activo ? 'Activo' : 'Inactivo']),
            ],
        };

        return $this->streamXlsx("catalogos-compras-{$tab}.xlsx", $headers, $rows->all());
    }
}
