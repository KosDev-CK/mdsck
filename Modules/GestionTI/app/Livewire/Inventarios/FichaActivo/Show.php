<?php

namespace Modules\GestionTI\Livewire\Inventarios\FichaActivo;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\SolicitudSicBorrador;

/**
 * Ficha de Activo / Trazabilidad (sección 7.13 del spec original) — detalle
 * de un solo `Asset`: encabezado de solo lectura + línea de tiempo completa
 * de su ciclo de vida. 100% lectura/agregación, sin tablas ni migraciones
 * nuevas — recorre relaciones ya cerradas en etapas previas de Fase 3. Ver
 * docs/gestionti-progreso.md, Fase 3 etapa 10, para el diseño completo.
 *
 * Ruta con route-model binding sobre `Asset` (mismo patrón que
 * `PresupuestoProyectos\Show::mount(ProyectoPresupuesto $proyectoPresupuesto)`).
 *
 * Nota importante sobre "dos caminos al mismo registro" (ver
 * docs/gestionti-progreso.md): `RecepcionLinea::solicitudProveedorLinea()`
 * también llega, indirectamente, al mismo `SolicitudProveedor` que
 * `recepcionLinea->recepcion->solicitudProveedor()` — este componente
 * DELIBERADAMENTE solo usa el segundo camino (vía `Recepcion`) para
 * construir el evento "Solicitud a Proveedor", nunca el primero, así que
 * nunca se duplica ese evento.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Asset $asset;

    public function mount(Asset $asset): void
    {
        $this->asset = $asset->load([
            'tipoEquipo', 'marca', 'modelo', 'estatus', 'ubicacionActual', 'sicReservada',
            'dadoDeAltaPor',
            'recepcionLinea.recepcion.recibidoPor',
            'recepcionLinea.recepcion.solicitudProveedor.ticket.empleado',
            'recepcionLinea.recepcion.solicitudProveedor.sic.empleado',
            'recepcionLinea.recepcion.solicitudProveedor.proyectoPresupuestoArticulo.proyecto',
            'recepcionLinea.recepcion.solicitudProveedor.vendor',
            'sicReservationLogs',
            'assignments.empleado',
            'assignments.responsableEntrega',
            'mantenimientos.vendor',
            'mantenimientos.realizadoPor',
            'stockMovements.ubicacionOrigen',
            'stockMovements.ubicacionDestino',
            'invoice.vendor',
        ]);
    }

    /**
     * Construye la línea de tiempo completa, recorriendo TODAS las fuentes
     * documentadas en docs/gestionti-progreso.md (Fase 3 etapa 10) y
     * ordenándolas cronológicamente ascendente.
     *
     * Criterio de fecha/orden para eventos sin fecha de negocio exacta
     * (documentado en el reporte final de la tarea, no solo aquí): cada
     * evento tiene una `sort_fecha` (SIEMPRE una fecha real, nunca null, para
     * ordenar) y una `fecha` (la que se MUESTRA, puede ser null). Cuando el
     * evento tiene una fecha de negocio real (ej. `fecha_solicitud` de un
     * Ticket) se usa esa para ambas. Cuando no la tiene (ej. una
     * `AssetAssignment` migrada del histórico sin `fecha_asignacion`, o un
     * `AssetSicReservationLog` que no tiene columna de fecha propia — su
     * `fecha` es `created_at`), `sort_fecha` cae a `created_at` del registro
     * que origina el evento (nunca null, todo modelo tiene timestamps) para
     * ubicarlo en un punto razonable de la línea de tiempo según cuándo se
     * capturó, en vez de amontonar todos los eventos sin fecha al principio
     * — pero `fecha` (la mostrada) solo lleva la fecha de negocio real, para
     * no mentirle al usuario mostrando un `created_at` como si fuera la
     * fecha real del evento; si no hay fecha de negocio, la vista muestra
     * "Sin fecha registrada". Como segundo criterio de desempate (fechas
     * iguales o ambas ausentes) cada evento tiene un `orden_tipo` que refleja
     * el orden causal documentado por el encargo para la cadena de origen de
     * una compra (Ticket -> SIC/Proyecto -> Solicitud a Proveedor ->
     * Recepción) y, después de esa cadena, el resto de fuentes en el orden
     * en que aparecen en el spec.
     */
    private function buildTimeline(): array
    {
        $events = [];

        // ---- Origen del activo ----
        if ($this->asset->origen_tipo === 'alta_manual') {
            $events[] = $this->event(
                fecha: $this->asset->fecha_alta_stock ? Carbon::parse($this->asset->fecha_alta_stock) : null,
                fallback: $this->asset->created_at,
                orden: 0,
                tipo: 'alta_manual',
                color: 'gray',
                icono: 'plus-circle',
                titulo: 'Alta manual',
                detalle: $this->altaManualDetalle(),
            );
        } elseif ($this->asset->origen_tipo === 'migracion_historica') {
            $events[] = $this->event(
                fecha: $this->asset->fecha_alta_stock ? Carbon::parse($this->asset->fecha_alta_stock) : null,
                fallback: $this->asset->created_at,
                orden: 0,
                tipo: 'migracion_historica',
                color: 'gray',
                icono: 'archive-box',
                titulo: 'Alta por migración histórica',
                detalle: $this->asset->nota_adquisicion_original ?: null,
            );
        } elseif ($this->asset->origen_tipo === 'compra') {
            array_push($events, ...$this->origenCompraEventos());
        }

        // ---- Reasignaciones de SIC ----
        foreach ($this->asset->sicReservationLogs as $log) {
            $events[] = $this->event(
                fecha: null,
                fallback: $log->created_at,
                orden: 4,
                tipo: 'reasignacion_sic',
                color: 'amber',
                icono: 'arrow-path',
                titulo: 'Reasignación de SIC',
                detalle: $this->reasignacionSicDetalle($log),
            );
        }

        // ---- Asignaciones (+ devolución, si aplica) ----
        foreach ($this->asset->assignments as $assignment) {
            $empleadoNombre = $assignment->empleado?->nombre ?? 'empleado sin registro';

            $events[] = $this->event(
                fecha: $assignment->fecha_asignacion ? Carbon::parse($assignment->fecha_asignacion) : null,
                fallback: $assignment->created_at,
                orden: 5,
                tipo: 'asignacion',
                color: 'emerald',
                icono: 'user-plus',
                titulo: "Asignado a {$empleadoNombre}",
                detalle: $this->asignacionDetalle($assignment),
            );

            if ($assignment->fecha_devolucion) {
                $events[] = $this->event(
                    fecha: Carbon::parse($assignment->fecha_devolucion),
                    fallback: $assignment->updated_at,
                    orden: 6,
                    tipo: 'devolucion',
                    color: 'gray',
                    icono: 'arrow-uturn-left',
                    titulo: "Devuelto por {$empleadoNombre}",
                    detalle: null,
                );
            }
        }

        // ---- Mantenimientos ----
        foreach ($this->asset->mantenimientos as $mantenimiento) {
            $events[] = $this->event(
                fecha: $mantenimiento->fecha_realizada ?? $mantenimiento->fecha_programada,
                fallback: $mantenimiento->created_at,
                orden: 7,
                tipo: 'mantenimiento',
                color: $this->mantenimientoColor($mantenimiento->estatus),
                icono: 'wrench-screwdriver',
                titulo: 'Mantenimiento '.$this->mantenimientoTipoLabel($mantenimiento->tipo),
                detalle: $this->mantenimientoDetalle($mantenimiento),
            );
        }

        // ---- Traslados (StockMovement con tipo = 'traslado') ----
        foreach ($this->asset->stockMovements->where('tipo', 'traslado') as $movimiento) {
            $events[] = $this->event(
                fecha: $movimiento->fecha,
                fallback: $movimiento->created_at,
                orden: 8,
                tipo: 'traslado',
                color: 'info',
                icono: 'map',
                titulo: 'Trasladado de '.($movimiento->ubicacionOrigen?->nombre ?? 'origen sin registro')
                    .' a '.($movimiento->ubicacionDestino?->nombre ?? 'destino sin registro'),
                detalle: $movimiento->comentarios,
            );
        }

        // ---- Factura ----
        if ($this->asset->invoice) {
            $events[] = $this->event(
                fecha: $this->asset->invoice->fecha_recepcion,
                fallback: $this->asset->invoice->created_at,
                orden: 9,
                tipo: 'factura',
                color: 'emerald',
                icono: 'document-currency-dollar',
                titulo: 'Facturado',
                detalle: $this->facturaDetalle(),
            );
        }

        usort($events, function (array $a, array $b) {
            $comparacion = $a['sort_fecha']->timestamp <=> $b['sort_fecha']->timestamp;

            return $comparacion !== 0 ? $comparacion : ($a['orden_tipo'] <=> $b['orden_tipo']);
        });

        return $events;
    }

    /**
     * Cadena de eventos de origen para `origen_tipo = 'compra'`: Ticket (si
     * existe) -> SIC/Proyecto de Presupuesto (si existe alguno de los 2,
     * son mutuamente excluyentes por regla de negocio) -> Solicitud a
     * Proveedor -> Recepción. Todo defensivo con `?->` — cualquiera de estos
     * eslabones puede faltar (compra directa sin ticket/SIC/proyecto).
     *
     * @return array<int, array>
     */
    private function origenCompraEventos(): array
    {
        $events = [];

        $recepcion = $this->asset->recepcionLinea?->recepcion;
        $solicitud = $recepcion?->solicitudProveedor;

        if ($solicitud?->ticket) {
            $ticket = $solicitud->ticket;

            $events[] = $this->event(
                fecha: $ticket->fecha,
                fallback: $ticket->created_at,
                orden: 0,
                tipo: 'ticket',
                color: 'indigo',
                icono: 'ticket',
                titulo: 'Ticket',
                detalle: 'Folio '.($ticket->sdp_display_id ?? "#{$ticket->id}")
                    .' — solicitante: '.($ticket->empleado?->nombre ?? '—'),
            );
        }

        if ($solicitud?->sic) {
            $sic = $solicitud->sic;

            $events[] = $this->event(
                fecha: $sic->fecha_solicitud,
                fallback: $sic->created_at,
                orden: 1,
                tipo: 'sic',
                color: 'indigo',
                icono: 'document-text',
                titulo: 'Solicitud de SIC',
                detalle: 'Folio: '.($sic->folio_sic ?: "SIC #{$sic->id}")
                    .' — solicitante: '.($sic->empleado?->nombre ?? '—'),
            );
        } elseif ($solicitud?->proyectoPresupuestoArticulo?->proyecto) {
            $proyecto = $solicitud->proyectoPresupuestoArticulo->proyecto;

            $events[] = $this->event(
                fecha: $proyecto->fecha_solicitud,
                fallback: $proyecto->created_at,
                orden: 1,
                tipo: 'proyecto_presupuesto',
                color: 'indigo',
                icono: 'banknotes',
                titulo: 'Proyecto de Presupuesto',
                detalle: $proyecto->nombre_proyecto,
            );
        }

        if ($solicitud) {
            $events[] = $this->event(
                fecha: $solicitud->fecha_solicitud,
                fallback: $solicitud->created_at,
                orden: 2,
                tipo: 'solicitud_proveedor',
                color: 'amber',
                icono: 'shopping-cart',
                titulo: 'Solicitud a Proveedor',
                detalle: 'Folio '.$solicitud->folio.' — proveedor: '.($solicitud->vendor?->nombre_comercial ?? '—'),
            );
        }

        if ($recepcion) {
            $events[] = $this->event(
                fecha: $recepcion->fecha_recepcion,
                fallback: $recepcion->created_at,
                orden: 3,
                tipo: 'recepcion',
                color: 'amber',
                icono: 'inbox-arrow-down',
                titulo: 'Recepción de Proveedor',
                detalle: 'Remisión '.($recepcion->folio_remision ?? '—')
                    .' — recibido por: '.($recepcion->recibidoPor?->nombre ?? '—'),
            );
        }

        return $events;
    }

    private function altaManualDetalle(): ?string
    {
        $partes = [];

        if ($this->asset->motivo_alta_manual) {
            $partes[] = "Motivo: {$this->asset->motivo_alta_manual}";
        }

        if ($this->asset->dadoDeAltaPor) {
            $partes[] = "Dado de alta por: {$this->asset->dadoDeAltaPor->nombre}";
        }

        return $partes ? implode(' — ', $partes) : null;
    }

    private function reasignacionSicDetalle($log): string
    {
        $anterior = $log->sic_anterior_id
            ? ($this->sicFolioLabel($log->sic_anterior_id))
            : '—';

        $nueva = $this->sicFolioLabel($log->sic_nueva_id);

        return "Motivo: {$log->motivo} — SIC anterior: {$anterior} → SIC nueva: {$nueva}";
    }

    private function sicFolioLabel(?int $sicId): string
    {
        if (! $sicId) {
            return '—';
        }

        $sic = SolicitudSicBorrador::find($sicId);

        if (! $sic) {
            return "SIC #{$sicId}";
        }

        return $sic->folio_sic ?: "SIC #{$sic->id}";
    }

    private function asignacionDetalle($assignment): ?string
    {
        $partes = [];

        if ($assignment->estado_equipo_entrega) {
            $partes[] = 'Estado de entrega: '.$assignment->estado_equipo_entrega;
        }

        if ($assignment->responsableEntrega) {
            $partes[] = 'Responsable de entrega: '.$assignment->responsableEntrega->nombre;
        }

        return $partes ? implode(' — ', $partes) : null;
    }

    private function mantenimientoColor(string $estatus): string
    {
        return match ($estatus) {
            'realizado' => 'emerald',
            'cancelado' => 'gray',
            default => 'amber',
        };
    }

    private function mantenimientoTipoLabel(string $tipo): string
    {
        return match ($tipo) {
            'preventivo' => 'preventivo',
            'correctivo' => 'correctivo',
            default => $tipo,
        };
    }

    private function mantenimientoDetalle($mantenimiento): string
    {
        $estatusLabels = [
            'programado' => 'Programado',
            'en_proceso' => 'En proceso',
            'realizado' => 'Realizado',
            'cancelado' => 'Cancelado',
            'reprogramado' => 'Reprogramado',
        ];

        $detalle = 'Estatus: '.($estatusLabels[$mantenimiento->estatus] ?? $mantenimiento->estatus);

        $detalle .= $mantenimiento->origen_ejecucion === 'externo'
            ? ' — proveedor: '.($mantenimiento->vendor?->nombre_comercial ?? '—')
            : ' — realizado por: '.($mantenimiento->realizadoPor?->nombre ?? '—');

        return $detalle;
    }

    private function facturaDetalle(): string
    {
        $invoice = $this->asset->invoice;

        return 'Folio '.$invoice->folio_factura
            .' — proveedor: '.($invoice->vendor?->nombre_comercial ?? '—')
            .' — monto: '.$invoice->moneda.' '.number_format((float) $invoice->monto_total, 2);
    }

    /**
     * @param  Carbon|string|null  $fecha  Fecha de negocio real del evento
     *                                     (mostrada tal cual, o "Sin fecha
     *                                     registrada" si es null).
     * @param  Carbon  $fallback  `created_at` del registro que origina el
     *                            evento — solo se usa para ordenar cuando no
     *                            hay fecha de negocio, nunca se muestra.
     */
    private function event($fecha, Carbon $fallback, int $orden, string $tipo, string $color, string $icono, string $titulo, ?string $detalle): array
    {
        $fecha = $fecha instanceof Carbon ? $fecha : ($fecha ? Carbon::parse($fecha) : null);

        return [
            'fecha' => $fecha,
            'sort_fecha' => $fecha ?? $fallback,
            'orden_tipo' => $orden,
            'tipo' => $tipo,
            'color' => $color,
            'icono' => $icono,
            'titulo' => $titulo,
            'detalle' => $detalle,
        ];
    }

    /**
     * Reporte de trazabilidad en PDF — reutiliza `buildTimeline()` (mismo
     * método privado que ya usa `render()` para la pantalla Livewire) en vez
     * de duplicar la lógica de recorrido de relaciones. Mismo patrón:
     * `Pdf::loadView(...)` + `streamDownload(...)`.
     */
    public function exportTrazabilidadPdf()
    {
        $pdf = Pdf::loadView('gestionti::pdf.reporte-trazabilidad', [
            'asset' => $this->asset,
            'timeline' => $this->buildTimeline(),
        ]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'trazabilidad-'.$this->asset->codigo.'.pdf'
        );
    }

    public function render()
    {
        return view('gestionti::livewire.inventarios.ficha-activo.show', [
            'timeline' => $this->buildTimeline(),
        ]);
    }
}
