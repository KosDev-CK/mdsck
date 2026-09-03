<?php

namespace Modules\GestionTI\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;

/**
 * Búsqueda rápida global (sección 7.16 del spec original): un único cuadro
 * de búsqueda que recorre varias entidades del módulo a la vez y muestra
 * resultados agrupados por tipo, cada uno con un link a la pantalla real
 * donde vive ese registro. No es una pantalla de detalle nueva ni crea
 * ninguna tabla — es 100% lectura, un directorio rápido de "¿dónde está
 * esto?".
 *
 * Vive en la raíz de Livewire/ (no en un subgrupo como Inventarios/ o
 * Compras/) porque cruza todos los grupos operativos del módulo.
 *
 * Gating por permiso: esta pantalla es visible/asignable a cualquier
 * perfil, pero cada categoría de resultado se auto-filtra según lo que ese
 * usuario ya puede ver en el resto del módulo — un usuario sin el permiso
 * de una pantalla destino no debe ver esa categoría en los resultados (ni
 * generar la consulta) para no terminar en un link que le dé 403. El
 * gating es real (se evalúa antes de correr cada query), no solo visual.
 */
#[Layout('layouts.app')]
class BusquedaGlobal extends Component
{
    public string $query = '';

    /**
     * Límite de resultados mostrados por categoría. El conteo real se
     * consulta aparte (independiente del límite) para poder avisar
     * "Mostrando 5 de 23 — refina tu búsqueda" cuando hay más.
     */
    private const RESULT_LIMIT = 5;

    /**
     * Una entrada por categoría buscable: el permiso requerido para
     * siquiera correr la consulta, el modelo, las columnas a buscar (LIKE,
     * insensible a mayúsculas — tanto MySQL como SQLite son
     * case-insensitive por defecto para LIKE sobre columnas de texto
     * ASCII, no hace falta LOWER() explícito), y closures para construir
     * el título/subtítulo/link de cada resultado.
     */
    protected function categorias(): array
    {
        return [
            'assets' => [
                'label' => 'Activos',
                'permission' => 'screens.gestionti-ficha-activo.manage',
                'model' => Asset::class,
                'columns' => ['codigo', 'numero_serie', 'service_tag'],
                'title' => fn (Asset $asset) => $asset->codigo,
                'subtitle' => fn (Asset $asset) => collect([$asset->numero_serie, $asset->service_tag])
                    ->filter()
                    ->implode(' · ') ?: null,
                'url' => fn (Asset $asset) => route('gestionti.ficha-activo.show', $asset),
            ],
            'empleados' => [
                'label' => 'Empleados',
                'permission' => 'screens.gestionti-catalogos-empleados.manage',
                'model' => Empleado::class,
                'columns' => ['numero_empleado', 'nombre'],
                'title' => fn (Empleado $empleado) => $empleado->nombre,
                'subtitle' => fn (Empleado $empleado) => "Núm. empleado: {$empleado->numero_empleado}",
                'url' => fn (Empleado $empleado) => route('gestionti.catalogos.empleados', ['search' => $empleado->numero_empleado]),
            ],
            'solicitudes_sic' => [
                'label' => 'Solicitudes de SIC',
                'permission' => 'screens.gestionti-solicitudes-sic.manage',
                'model' => SolicitudSicBorrador::class,
                'columns' => ['folio_sic'],
                'title' => fn (SolicitudSicBorrador $sic) => $sic->folio_sic,
                'subtitle' => fn () => null,
                'url' => fn (SolicitudSicBorrador $sic) => route('gestionti.solicitudes-sic.index', ['search' => $sic->folio_sic]),
            ],
            'solicitudes_proveedor' => [
                'label' => 'Solicitudes a Proveedor',
                'permission' => 'screens.gestionti-solicitudes-proveedor.manage',
                'model' => SolicitudProveedor::class,
                'columns' => ['folio'],
                'title' => fn (SolicitudProveedor $solicitud) => $solicitud->folio,
                'subtitle' => fn () => null,
                'url' => fn (SolicitudProveedor $solicitud) => route('gestionti.solicitudes-proveedor.index', ['search' => $solicitud->folio]),
            ],
            'recepciones' => [
                'label' => 'Recepciones',
                'permission' => 'screens.gestionti-recepciones.manage',
                'model' => Recepcion::class,
                'columns' => ['folio_remision'],
                'title' => fn (Recepcion $recepcion) => $recepcion->folio_remision,
                'subtitle' => fn () => null,
                'url' => fn (Recepcion $recepcion) => route('gestionti.recepciones.index', ['search' => $recepcion->folio_remision]),
            ],
            'facturas' => [
                'label' => 'Facturas',
                'permission' => 'screens.gestionti-facturas.manage',
                'model' => Invoice::class,
                'columns' => ['folio_factura'],
                'title' => fn (Invoice $invoice) => $invoice->folio_factura,
                'subtitle' => fn () => null,
                'url' => fn (Invoice $invoice) => route('gestionti.facturas.index', ['search' => $invoice->folio_factura]),
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, total: int, items: \Illuminate\Support\Collection}>
     */
    protected function buscar(string $term): array
    {
        $results = [];

        foreach ($this->categorias() as $key => $config) {
            if (! auth()->user()->can($config['permission'])) {
                continue;
            }

            $matchQuery = $config['model']::query()->where(function ($q) use ($config, $term) {
                foreach ($config['columns'] as $column) {
                    $q->orWhere($column, 'like', '%'.$term.'%');
                }
            });

            $total = (clone $matchQuery)->count();

            if ($total === 0) {
                continue;
            }

            $records = $matchQuery->limit(self::RESULT_LIMIT)->get();

            $results[$key] = [
                'label' => $config['label'],
                'total' => $total,
                'items' => $records->map(fn ($record) => [
                    'title' => $config['title']($record),
                    'subtitle' => $config['subtitle']($record),
                    'url' => $config['url']($record),
                ]),
            ];
        }

        return $results;
    }

    public function render()
    {
        $term = trim($this->query);

        $results = strlen($term) >= 2 ? $this->buscar($term) : [];

        return view('gestionti::livewire.busqueda-global', [
            'term' => $term,
            'results' => $results,
        ]);
    }
}
