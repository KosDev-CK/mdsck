<?php

namespace Modules\MesaServicio\Livewire\Tecnicos;

use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;

/**
 * Ficha de técnico (Fase 2): tickets pendientes y atendidos de un técnico
 * específico. Ruta con route-model binding sobre SdpTechnician, mismo
 * patrón que Modules\GestionTI\Livewire\Inventarios\FichaActivo\Show sobre
 * Asset. Comparte permiso (screens.mesaservicio-dashboard.manage) con el
 * Dashboard en vez de tener un permiso propio — ver "Ajustes de criterio"
 * en docs/mesaservicio-progreso.md para el porqué.
 *
 * Sin componente de paginación reusable todavía en el repo (ver "Pendiente"
 * en CLAUDE.md) — se acota a los 100 más recientes de cada lista en vez de
 * paginar, mismo criterio de tope defensivo que Dashboard::slaVencidosDetalle().
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public SdpTechnician $tecnico;

    public function mount(SdpTechnician $tecnico): void
    {
        $this->tecnico = $tecnico;
    }

    /**
     * @return Collection<int, SdpTicket>
     */
    private function pendientes(): Collection
    {
        return SdpTicket::where('sdp_technician_id', $this->tecnico->id)
            ->whereHas('ticketStatus', fn ($q) => $q->where('tipo', SdpTicketStatus::TIPO_EN_CURSO))
            ->orderByDesc('created_time')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, SdpTicket>
     */
    private function atendidos(): Collection
    {
        return SdpTicket::where('sdp_technician_id', $this->tecnico->id)
            ->whereHas('ticketStatus', fn ($q) => $q->where('tipo', SdpTicketStatus::TIPO_COMPLETADO))
            ->orderByDesc('completed_time')
            ->limit(100)
            ->get();
    }

    public function render()
    {
        return view('mesaservicio::livewire.tecnicos.show', [
            'pendientes' => $this->pendientes(),
            'atendidos' => $this->atendidos(),
        ]);
    }
}
