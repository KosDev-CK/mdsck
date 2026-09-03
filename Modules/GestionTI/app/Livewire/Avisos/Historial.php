<?php

namespace Modules\GestionTI\Livewire\Avisos;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\TipoAviso;

/**
 * Historial/auditoría administrativa de TODO lo que `AvisoDispatcher` ha
 * enviado, cruzando canales — solo lectura. No es la bandeja in-app (esa ya
 * existe, `App\Livewire\Notifications\Bell`).
 */
#[Layout('layouts.app')]
class Historial extends Component
{
    use WithPagination;

    #[Url(as: 'tipo')]
    public string $tipoAvisoFilter = '';

    #[Url(as: 'canal')]
    public string $canalFilter = '';

    #[Url(as: 'estatus')]
    public string $estatusFilter = '';

    #[Url(as: 'desde')]
    public string $fechaDesde = '';

    #[Url(as: 'hasta')]
    public string $fechaHasta = '';

    public function updatingTipoAvisoFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCanalFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEstatusFilter(): void
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

    public function render()
    {
        $records = AvisoEnviado::query()
            ->with(['tipoAviso', 'destinatario'])
            ->when($this->tipoAvisoFilter !== '', fn ($q) => $q->where('tipo_aviso_id', $this->tipoAvisoFilter))
            ->when($this->canalFilter !== '', fn ($q) => $q->where('canal', $this->canalFilter))
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('estatus_envio', $this->estatusFilter))
            ->when($this->fechaDesde !== '', fn ($q) => $q->whereDate('fecha_envio', '>=', $this->fechaDesde))
            ->when($this->fechaHasta !== '', fn ($q) => $q->whereDate('fecha_envio', '<=', $this->fechaHasta))
            ->orderByDesc('fecha_envio')
            ->paginate(15);

        return view('gestionti::livewire.avisos.historial', [
            'records' => $records,
            'tipoAvisoOptions' => TipoAviso::orderBy('codigo')->get(),
        ]);
    }
}
