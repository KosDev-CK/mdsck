<?php

namespace Modules\GestionTI\Livewire\MesaServicio;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;

/**
 * Pantalla de solo lectura sobre la réplica local de requisiciones (SIC) de
 * Oracle EBS (Fase 5, punto 1). La sincronización real corre por comando
 * (`gestionti:ebs-sincronizar-creadas`/`-aprobadas`/`-backfill`) — esta
 * pantalla no dispara ninguna llamada al API, solo lista lo que ya se
 * sincronizó y permite vincular a mano cuando la vinculación automática (por
 * `folio_sic === code`) no aplicó (folio nunca capturado, o no coincidió).
 * Ver docs/gestionti-progreso.md.
 */
#[Layout('layouts.app')]
class EbsRequisiciones extends Component
{
    use WithPagination;

    #[Url(as: 'codigo')]
    public string $codigoFilter = '';

    #[Url(as: 'estatus')]
    public string $estatusFilter = '';

    /** '' | 'vinculada' | 'no_vinculada' */
    #[Url(as: 'vinculacion')]
    public string $vinculacionFilter = '';

    /**
     * '' | 'asignada' | 'sin_asignar' — a diferencia de `vinculacionFilter`
     * (que mira si hay SIC local), este filtra por si la requisición ya
     * quedó recogida por una Solicitud a Proveedor, por CUALQUIERA de los 2
     * caminos (SIC local -> línea, o EBS directo -> línea).
     */
    #[Url(as: 'asignacion')]
    public string $asignacionFilter = '';

    #[Url(as: 'desde')]
    public string $fechaDesde = '';

    #[Url(as: 'hasta')]
    public string $fechaHasta = '';

    #[Url(as: 'aprobada_desde')]
    public string $fechaAprobadaDesde = '';

    #[Url(as: 'aprobada_hasta')]
    public string $fechaAprobadaHasta = '';

    public bool $showVincularModal = false;

    public ?int $vinculandoId = null;

    public string $vincularSearch = '';

    public ?int $vincularSolicitudId = null;

    public bool $showDetalleModal = false;

    public ?int $detalleId = null;

    /**
     * "Solo SICs autorizadas y seleccionables" (punto 6 del rediseño,
     * mismo criterio que `Compras\SolicitudesProveedor::sicPickerOptions()`,
     * ver `SolicitudSicBorrador::scopeAutorizadaYSeleccionable()`) — acota el
     * grid a las requisiciones cuya SIC local ya se puede usar para armar
     * una Solicitud a Proveedor.
     */
    public bool $soloSeleccionables = false;

    /**
     * IDs de `SolicitudSicBorrador` marcados con el checkbox por fila (camino
     * "SIC local") — se arman en "Crear Solicitud a Proveedor", que navega
     * con estos ids como query param `crear_desde_sics` hacia
     * `SolicitudesProveedor::mount()`.
     *
     * @var array<int, int>
     */
    public array $sicIdsSeleccionados = [];

    /**
     * IDs de `EbsRequisition` marcados con el checkbox por fila (camino "EBS
     * directo, sin SIC local") — mismo patrón que `$sicIdsSeleccionados`,
     * pero navegan como `crear_desde_ebs`. Nunca se solapan: una fila nunca
     * ofrece los 2 checkboxes a la vez — si tiene SIC local, solo puede
     * seleccionarse por ese camino.
     *
     * @var array<int, int>
     */
    public array $ebsIdsSeleccionados = [];

    public bool $showSolicitudProveedorModal = false;

    public ?int $solicitudProveedorDetalleId = null;

    /**
     * Memo de `ebsDirectoElegibleIds()` — ver ese método para el porqué (se
     * invoca varias veces dentro de un mismo ciclo de `render()`).
     *
     * @var array<int, int>|null
     */
    private ?array $ebsDirectoElegibleIdsCache = null;

    public function updatingCodigoFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEstatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingVinculacionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingAsignacionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatingFechaHasta(): void
    {
        $this->resetPage();
    }

    public function updatingFechaAprobadaDesde(): void
    {
        $this->resetPage();
    }

    public function updatingFechaAprobadaHasta(): void
    {
        $this->resetPage();
    }

    public function updatingSoloSeleccionables(): void
    {
        $this->resetPage();
    }

    public function openDetalle(int $id): void
    {
        $this->detalleId = $id;
        $this->showDetalleModal = true;
    }

    public function closeDetalle(): void
    {
        $this->showDetalleModal = false;
        $this->detalleId = null;
    }

    public function openVincular(int $id): void
    {
        $this->vinculandoId = $id;
        $this->vincularSearch = '';
        $this->vincularSolicitudId = null;
        $this->resetValidation();
        $this->showVincularModal = true;
    }

    public function cancelVincular(): void
    {
        $this->showVincularModal = false;
        $this->vinculandoId = null;
        $this->vincularSearch = '';
        $this->vincularSolicitudId = null;
        $this->resetValidation();
    }

    public function confirmVincular(): void
    {
        $this->validate([
            'vincularSolicitudId' => 'required|integer|exists:solicitudes_sic_borrador,id',
        ]);

        $ebsRequisicion = EbsRequisition::findOrFail($this->vinculandoId);
        $solicitud = SolicitudSicBorrador::findOrFail($this->vincularSolicitudId);

        if ($solicitud->ebs_requisition_id && $solicitud->ebs_requisition_id !== $ebsRequisicion->id) {
            $this->addError('vincularSolicitudId', 'Esa Solicitud de SIC ya está vinculada a otra requisición de EBS.');

            return;
        }

        app(EbsRequisitionSyncService::class)->vincularManualmente($solicitud, $ebsRequisicion);

        $this->cancelVincular();
        session()->flash('status', 'Vinculado correctamente.');
    }

    /**
     * Modal de solo lectura de la Solicitud a Proveedor a la que ya quedó
     * asignada la SIC local de esta fila (punto 5 del rediseño) — reemplaza
     * el link de navegación que había antes. Sin ninguna acción dentro, solo
     * consulta.
     */
    public function openSolicitudProveedor(int $id): void
    {
        $this->solicitudProveedorDetalleId = $id;
        $this->showSolicitudProveedorModal = true;
    }

    public function closeSolicitudProveedor(): void
    {
        $this->showSolicitudProveedorModal = false;
        $this->solicitudProveedorDetalleId = null;
    }

    /**
     * Base compartida por `render()` (el grid completo) y
     * `elegibleSicIdsVisibles()`/`elegibleEbsIdsVisibles()` (las filas
     * elegibles de la página ACTUALMENTE visible, respetando filtros y
     * paginación) — mismos `when()` en ambos para no duplicar la cadena de
     * filtros.
     */
    private function baseQuery()
    {
        return EbsRequisition::query()
            ->with([
                'solicitudSicBorrador.ticket',
                'solicitudSicBorrador.solicitudProveedorLineas.solicitud',
                'solicitudProveedorLineas.solicitud',
            ])
            ->when($this->codigoFilter !== '', fn ($q) => $q->matchesSearch($this->codigoFilter))
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('status', $this->estatusFilter))
            ->when($this->vinculacionFilter === 'vinculada', fn ($q) => $q->whereHas('solicitudSicBorrador'))
            ->when($this->vinculacionFilter === 'no_vinculada', fn ($q) => $q->whereDoesntHave('solicitudSicBorrador'))
            ->when($this->asignacionFilter === 'asignada', fn ($q) => $q->where(function ($q2) {
                $q2->whereHas('solicitudSicBorrador.solicitudProveedorLineas')
                    ->orWhereHas('solicitudProveedorLineas');
            }))
            ->when($this->asignacionFilter === 'sin_asignar', fn ($q) => $q->where(function ($q2) {
                $q2->whereDoesntHave('solicitudProveedorLineas')
                    ->where(function ($q3) {
                        $q3->whereDoesntHave('solicitudSicBorrador')
                            ->orWhereHas('solicitudSicBorrador', fn ($q4) => $q4->whereDoesntHave('solicitudProveedorLineas'));
                    });
            }))
            ->when($this->fechaDesde !== '', fn ($q) => $q->whereDate('fecha_creacion', '>=', $this->fechaDesde))
            ->when($this->fechaHasta !== '', fn ($q) => $q->whereDate('fecha_creacion', '<=', $this->fechaHasta))
            ->when($this->fechaAprobadaDesde !== '', fn ($q) => $q->whereDate('approver_date', '>=', $this->fechaAprobadaDesde))
            ->when($this->fechaAprobadaHasta !== '', fn ($q) => $q->whereDate('approver_date', '<=', $this->fechaAprobadaHasta))
            ->when($this->soloSeleccionables, function ($q) {
                $ebsDirectoIds = $this->ebsDirectoElegibleIds();

                $q->where(function ($q2) use ($ebsDirectoIds) {
                    $q2->whereHas('solicitudSicBorrador', fn ($q3) => $q3->autorizadaYSeleccionable())
                        ->orWhereIn('id', $ebsDirectoIds);
                });
            });
    }

    /**
     * IDs de `EbsRequisition` elegibles por el camino directo (sin SIC
     * local, ver `EbsRequisition::scopeElegibleDirectoSinSic()`/
     * `articuloMapeadoDeCompra()`) — resuelto en 2 pasos porque el mapeo
     * "item_id de la primera línea -> ArticuloSolicitud de categoría compra"
     * no es expresable en una sola cláusula SQL portátil: primero se filtra
     * en SQL (status, sin SIC local, sin asignar directo), luego en PHP.
     * Universo completo (no paginado) — el filtro `soloSeleccionables` y el
     * cálculo de "elegibles visibles" lo intersectan después con la página
     * actual. Memoizado por request (`$ebsDirectoElegibleIdsCache`, ver
     * arriba) para no repetir el filtrado en PHP (una consulta por
     * candidato) en las varias veces que `render()`/`baseQuery()` lo piden
     * en un mismo ciclo.
     */
    private function ebsDirectoElegibleIds(): array
    {
        if ($this->ebsDirectoElegibleIdsCache !== null) {
            return $this->ebsDirectoElegibleIdsCache;
        }

        return $this->ebsDirectoElegibleIdsCache = EbsRequisition::elegibleDirectoSinSic()
            ->with('lines')
            ->get()
            ->filter(fn ($ebsRequisicion) => $ebsRequisicion->articuloMapeadoDeCompra() !== null)
            ->pluck('id')
            ->all();
    }

    /**
     * IDs de `SolicitudSicBorrador` elegibles (autorizada + categoría de
     * compra + sin asignar, ver `SolicitudSicBorrador::scopeAutorizadaYSeleccionable()`)
     * de las filas de la página que el usuario tiene actualmente abierta —
     * usada por "Seleccionar todas"/"Deseleccionar todas", que actúan solo
     * sobre lo visible, respetando los demás filtros activos (punto 6 del
     * rediseño). Reutiliza la misma paginación de `render()` (mismo tamaño
     * de página, mismo resolvedor de página de Livewire), así que devuelve
     * exactamente las filas que el usuario está viendo en pantalla.
     */
    private function elegibleSicIdsVisibles(): array
    {
        return $this->baseQuery()
            ->orderByDesc('fecha_creacion')
            ->paginate(15)
            ->getCollection()
            ->pluck('solicitudSicBorrador.id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Mismo criterio que `elegibleSicIdsVisibles()` pero para el camino
     * directo (sin SIC local) — intersecta `ebsDirectoElegibleIds()` (el
     * universo completo) con los ids de la página actualmente visible.
     */
    private function elegibleEbsIdsVisibles(): array
    {
        $ebsDirectoIds = $this->ebsDirectoElegibleIds();

        return $this->baseQuery()
            ->orderByDesc('fecha_creacion')
            ->paginate(15)
            ->getCollection()
            ->pluck('id')
            ->filter(fn ($id) => in_array($id, $ebsDirectoIds, true))
            ->values()
            ->all();
    }

    public function seleccionarTodas(): void
    {
        $visiblesSic = $this->elegibleSicIdsVisibles();
        $visiblesEbs = $this->elegibleEbsIdsVisibles();

        $this->sicIdsSeleccionados = array_values(array_unique(array_merge($this->sicIdsSeleccionados, $visiblesSic)));
        $this->ebsIdsSeleccionados = array_values(array_unique(array_merge($this->ebsIdsSeleccionados, $visiblesEbs)));
    }

    public function deseleccionarTodas(): void
    {
        $visiblesSic = $this->elegibleSicIdsVisibles();
        $visiblesEbs = $this->elegibleEbsIdsVisibles();

        $this->sicIdsSeleccionados = array_values(array_diff($this->sicIdsSeleccionados, $visiblesSic));
        $this->ebsIdsSeleccionados = array_values(array_diff($this->ebsIdsSeleccionados, $visiblesEbs));
    }

    /**
     * Navega a "Solicitud a Proveedores" con las SICs y/o requisiciones de
     * EBS marcadas (pueden venir mezcladas) — esa pantalla reconoce
     * `crear_desde_sics`/`crear_desde_ebs` en su `mount()` y precarga el
     * formulario de creación con ellas ya seleccionadas (punto 7 del
     * rediseño). Sin ninguna seleccionada, no hace nada (el botón que
     * dispara esto ya se oculta en el blade en ese caso, esto es solo la
     * segunda capa de defensa).
     */
    public function crearSolicitudProveedor(): void
    {
        if (empty($this->sicIdsSeleccionados) && empty($this->ebsIdsSeleccionados)) {
            return;
        }

        $params = [];

        if (! empty($this->sicIdsSeleccionados)) {
            $params['crear_desde_sics'] = implode(',', $this->sicIdsSeleccionados);
        }

        if (! empty($this->ebsIdsSeleccionados)) {
            $params['crear_desde_ebs'] = implode(',', $this->ebsIdsSeleccionados);
        }

        $this->redirect(route('gestionti.solicitudes-proveedor.index', $params), navigate: false);
    }

    public function render()
    {
        $records = $this->baseQuery()
            ->orderByDesc('fecha_creacion')
            ->paginate(15);

        $detalle = $this->showDetalleModal
            ? EbsRequisition::with([
                'lines', 'notes',
                'solicitudSicBorrador.ticket',
                'solicitudSicBorrador.solicitudProveedorLineas.solicitud',
                'solicitudProveedorLineas.solicitud',
            ])->find($this->detalleId)
            : null;

        $solicitudProveedorDetalle = $this->showSolicitudProveedorModal
            ? SolicitudProveedor::with(['vendor', 'lineas.articulo', 'lineas.sic'])->find($this->solicitudProveedorDetalleId)
            : null;

        $solicitudOptions = collect();

        if ($this->vincularSearch !== '') {
            $solicitudOptions = SolicitudSicBorrador::query()
                ->with(['ticket', 'empleado'])
                ->where(function ($q) {
                    $q->where('folio_sic', 'like', "%{$this->vincularSearch}%")
                        ->orWhereHas('empleado', fn ($q2) => $q2->where('nombre', 'like', "%{$this->vincularSearch}%"))
                        ->orWhereHas('ticket', fn ($q2) => $q2->where('sdp_display_id', 'like', "%{$this->vincularSearch}%"));
                })
                ->limit(20)
                ->get();
        }

        // IDs de SIC elegibles ENTRE LAS FILAS YA CARGADAS en esta página
        // (no una query aparte) — determina qué filas muestran el checkbox,
        // ver `scopeAutorizadaYSeleccionable()`.
        $elegibleSicIds = SolicitudSicBorrador::autorizadaYSeleccionable()
            ->whereIn('id', $records->pluck('solicitudSicBorrador.id')->filter())
            ->pluck('id')
            ->all();

        // IDs de EbsRequisition elegibles por el camino directo, entre las
        // filas ya cargadas en esta página.
        $elegibleEbsIds = array_values(array_intersect(
            $this->ebsDirectoElegibleIds(),
            $records->pluck('id')->all()
        ));

        return view('gestionti::livewire.mesa-servicio.ebs-requisiciones', [
            'records' => $records,
            'solicitudProveedorDetalle' => $solicitudProveedorDetalle,
            'elegibleSicIds' => $elegibleSicIds,
            'elegibleEbsIds' => $elegibleEbsIds,
            'estatusOptions' => EbsRequisition::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'solicitudOptions' => $solicitudOptions,
            'detalle' => $detalle,
        ]);
    }
}
