<?php

namespace Modules\GestionTI\Livewire\MesaServicio;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\EbsRequisition;
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

    #[Url(as: 'desde')]
    public string $fechaDesde = '';

    #[Url(as: 'hasta')]
    public string $fechaHasta = '';

    public bool $showVincularModal = false;

    public ?int $vinculandoId = null;

    public string $vincularSearch = '';

    public ?int $vincularSolicitudId = null;

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

    public function updatingFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatingFechaHasta(): void
    {
        $this->resetPage();
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

    public function render()
    {
        $records = EbsRequisition::query()
            ->with(['solicitudSicBorrador.ticket'])
            ->when($this->codigoFilter !== '', fn ($q) => $q->where('code', 'like', "%{$this->codigoFilter}%"))
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('status', $this->estatusFilter))
            ->when($this->vinculacionFilter === 'vinculada', fn ($q) => $q->whereHas('solicitudSicBorrador'))
            ->when($this->vinculacionFilter === 'no_vinculada', fn ($q) => $q->whereDoesntHave('solicitudSicBorrador'))
            ->when($this->fechaDesde !== '', fn ($q) => $q->whereDate('fecha_creacion', '>=', $this->fechaDesde))
            ->when($this->fechaHasta !== '', fn ($q) => $q->whereDate('fecha_creacion', '<=', $this->fechaHasta))
            ->orderByDesc('fecha_creacion')
            ->paginate(15);

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

        return view('gestionti::livewire.mesa-servicio.ebs-requisiciones', [
            'records' => $records,
            'estatusOptions' => EbsRequisition::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'solicitudOptions' => $solicitudOptions,
        ]);
    }
}
