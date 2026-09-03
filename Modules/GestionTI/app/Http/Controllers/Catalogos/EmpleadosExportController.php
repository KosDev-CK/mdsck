<?php

namespace Modules\GestionTI\Http\Controllers\Catalogos;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Support\Exports\StreamsXlsxDownloads;

/**
 * "Exportar a Excel" de la pantalla Empleados — sin pestañas, un .xlsx con
 * las mismas columnas mostradas en la tabla (Número de empleado/Nombre/
 * Correo/Puesto/Estatus), respetando el filtro de búsqueda si estaba activo.
 */
class EmpleadosExportController extends Controller
{
    use StreamsXlsxDownloads;

    public function __invoke(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $records = Empleado::query()
            ->with('puesto')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('numero_empleado', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%")
                        ->orWhere('correo', 'like', "%{$search}%");
                });
            })
            ->orderBy('nombre')
            ->get();

        $headers = ['Número de empleado', 'Nombre', 'Correo', 'Puesto', 'Estatus'];

        $rows = $records->map(fn ($r) => [
            $r->numero_empleado,
            $r->nombre,
            $r->correo,
            $r->puesto?->nombre,
            $r->activo ? 'Activo' : 'Inactivo',
        ]);

        return $this->streamXlsx('catalogos-empleados.xlsx', $headers, $rows->all());
    }
}
