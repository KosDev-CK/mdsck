<?php

namespace Modules\GestionTI\Http\Controllers\Catalogos;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Licencia;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\PeriodicidadMantenimiento;
use Modules\GestionTI\Models\Propiedad;
use Modules\GestionTI\Models\SistemaOperativo;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Validador;
use Modules\GestionTI\Support\Exports\StreamsXlsxDownloads;

/**
 * "Exportar a Excel" de la pantalla Catálogos de Inventario — un .xlsx con
 * las mismas columnas mostradas en la pestaña activa (`tab`), respetando el
 * filtro de búsqueda si estaba activo (las 2 pestañas "regla" no tienen
 * buscador en pantalla, igual que en `Inventario::render()`), sin paginar.
 */
class InventarioExportController extends Controller
{
    use StreamsXlsxDownloads;

    private const CATALOGOS = [
        'tipo_equipo' => ['model' => TipoEquipo::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre', 'nombre_conocido']],
        'marcas' => ['model' => Marca::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'modelos' => ['model' => Modelo::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'sistemas_operativos' => ['model' => SistemaOperativo::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'licencias' => ['model' => Licencia::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'propiedades' => ['model' => Propiedad::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'validadores' => ['model' => Validador::class, 'orderBy' => 'nombre', 'searchColumns' => ['nombre']],
        'estatus_activo' => ['model' => EstatusActivo::class, 'orderBy' => 'nombre', 'searchColumns' => ['codigo', 'nombre']],
        'periodicidad_mantenimiento' => ['model' => PeriodicidadMantenimiento::class, 'orderBy' => 'id', 'searchColumns' => []],
        'stock_minimo' => ['model' => StockMinimo::class, 'orderBy' => 'id', 'searchColumns' => []],
    ];

    public function __invoke(Request $request)
    {
        $tab = $request->query('tab', 'tipo_equipo');
        abort_unless(array_key_exists($tab, self::CATALOGOS), 404);

        $config = self::CATALOGOS[$tab];
        $search = trim((string) $request->query('search', ''));

        $records = $config['model']::query()
            ->when($tab === 'modelos', fn ($q) => $q->with('marca'))
            ->when($tab === 'periodicidad_mantenimiento', fn ($q) => $q->with('tipoEquipo'))
            ->when($tab === 'stock_minimo', fn ($q) => $q->with(['tipoEquipo', 'ubicacion']))
            ->when($search !== '' && ! empty($config['searchColumns']), function ($q) use ($config, $search) {
                $q->where(function ($q) use ($config, $search) {
                    foreach ($config['searchColumns'] as $column) {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy($config['orderBy'])
            ->get();

        [$headers, $rows] = match ($tab) {
            'tipo_equipo' => [
                ['Nombre', 'Nombre conocido', 'En alcance', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre, $r->nombre_conocido, $r->en_alcance ? 'Sí' : 'No', $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'modelos' => [
                ['Nombre', 'Marca', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre, $r->marca?->nombre, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'estatus_activo' => [
                ['Código', 'Nombre', 'Estatus'],
                $records->map(fn ($r) => [$r->codigo, $r->nombre, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'periodicidad_mantenimiento' => [
                ['Tipo de equipo', 'Meses sugeridos', 'Estatus'],
                $records->map(fn ($r) => [$r->tipoEquipo?->nombre, $r->meses_sugeridos, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            'stock_minimo' => [
                ['Tipo de equipo', 'Ubicación', 'Cantidad mínima', 'Estatus'],
                $records->map(fn ($r) => [$r->tipoEquipo?->nombre, $r->ubicacion?->nombre, $r->cantidad_minima, $r->activo ? 'Activo' : 'Inactivo']),
            ],
            default => [
                ['Nombre', 'Estatus'],
                $records->map(fn ($r) => [$r->nombre, $r->activo ? 'Activo' : 'Inactivo']),
            ],
        };

        return $this->streamXlsx("catalogos-inventario-{$tab}.xlsx", $headers, $rows->all());
    }
}
