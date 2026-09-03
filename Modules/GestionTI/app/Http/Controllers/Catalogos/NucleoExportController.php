<?php

namespace Modules\GestionTI\Http\Controllers\Catalogos;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\Puesto;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\UnidadNegocio;
use Modules\GestionTI\Support\Exports\StreamsXlsxDownloads;

/**
 * "Exportar a Excel" de la pantalla Catálogos Núcleo — un .xlsx con las
 * mismas columnas mostradas en la pestaña activa (`tab`), respetando el
 * filtro de búsqueda si estaba activo (`search`), sin paginar. Mismo patrón
 * de controller+ruta que `TicketFormLinkPdfController` de FormBuilder.
 */
class NucleoExportController extends Controller
{
    use StreamsXlsxDownloads;

    private const CATALOGOS = [
        'empresas' => ['model' => Empresa::class, 'orderBy' => 'nombre_comercial', 'searchColumns' => ['razon_social', 'nombre_comercial', 'rfc']],
        'ubicaciones' => ['model' => Ubicacion::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre', 'nombre_conocido']],
        'areas' => ['model' => Area::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre', 'nombre_conocido']],
        'unidades_negocio' => ['model' => UnidadNegocio::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre', 'nombre_conocido']],
        'puestos' => ['model' => Puesto::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre', 'nombre_conocido']],
        'centros_costo' => ['model' => CentroCosto::class, 'orderBy' => 'nombre', 'searchColumns' => ['codigo', 'nombre', 'nombre_conocido']],
    ];

    public function __invoke(Request $request)
    {
        $tab = $request->query('tab', 'empresas');
        abort_unless(array_key_exists($tab, self::CATALOGOS), 404);

        $config = self::CATALOGOS[$tab];
        $search = trim((string) $request->query('search', ''));

        $records = $config['model']::query()
            ->when($tab === 'centros_costo', fn ($q) => $q->with('empresa'))
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
            'empresas' => [
                ['Nombre comercial', 'Razón social', 'RFC', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre_comercial, $r->razon_social, $r->rfc, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'centros_costo' => [
                ['Código', 'Nombre', 'Empresa', 'Estatus'],
                $records->map(fn ($r) => [$r->codigo, $r->nombre, $r->empresa?->nombre_comercial, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            default => [
                ['Nombre', 'Nombre conocido', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre, $r->nombre_conocido, $r->activo ? 'Activo' : 'Inactivo']),
            ],
        };

        return $this->streamXlsx("catalogos-nucleo-{$tab}.xlsx", $headers, $rows->all());
    }
}
