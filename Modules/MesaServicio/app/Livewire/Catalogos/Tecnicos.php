<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\MesaServicio\Models\GrupoAnalitico;
use Modules\MesaServicio\Models\SdpTechnician;

#[Layout('layouts.app')]
class Tecnicos extends Component
{
    use WithPagination;

    public string $search = '';

    /** todos | activos | inactivos */
    public string $filterActivo = 'todos';

    /** todos | si | no */
    public string $filterNivel1 = 'todos';

    /** todos | sin-asignar | <id de grupo_analitico> */
    public string $filterGrupoAnalitico = 'todos';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActivo(): void
    {
        $this->resetPage();
    }

    public function updatingFilterNivel1(): void
    {
        $this->resetPage();
    }

    public function updatingFilterGrupoAnalitico(): void
    {
        $this->resetPage();
    }

    /**
     * es_nivel_1 es uno de los dos únicos campos editables de este catálogo
     * — nombre, correo, puesto y activo se sobreescriben en cada corrida de
     * sdp:sync-technicians, este toggle nunca se toca desde ahí.
     */
    public function toggleNivel1(int $id): void
    {
        $technician = SdpTechnician::findOrFail($id);
        $technician->update(['es_nivel_1' => ! $technician->es_nivel_1]);
    }

    /**
     * grupo_analitico_id es el segundo campo editable a mano de este
     * catálogo (junto con es_nivel_1) — igual que ese, sdp:sync-technicians
     * nunca lo sobreescribe. $grupoAnaliticoId null = "Sin asignar".
     */
    public function asignarGrupoAnalitico(int $id, ?int $grupoAnaliticoId): void
    {
        $technician = SdpTechnician::findOrFail($id);
        $technician->update(['grupo_analitico_id' => $grupoAnaliticoId]);
    }

    public function render()
    {
        $records = SdpTechnician::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('nombre', 'like', "%{$this->search}%")
                        ->orWhere('correo', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterActivo === 'activos', fn ($query) => $query->where('activo', true))
            ->when($this->filterActivo === 'inactivos', fn ($query) => $query->where('activo', false))
            ->when($this->filterNivel1 === 'si', fn ($query) => $query->where('es_nivel_1', true))
            ->when($this->filterNivel1 === 'no', fn ($query) => $query->where('es_nivel_1', false))
            ->when($this->filterGrupoAnalitico === 'sin-asignar', fn ($query) => $query->whereNull('grupo_analitico_id'))
            ->when(
                $this->filterGrupoAnalitico !== 'todos' && $this->filterGrupoAnalitico !== 'sin-asignar',
                fn ($query) => $query->where('grupo_analitico_id', $this->filterGrupoAnalitico)
            )
            ->orderBy('nombre')
            ->paginate(15);

        return view('mesaservicio::livewire.catalogos.tecnicos', [
            'records' => $records,
            // Para el <select> de asignación por fila: solo grupos activos
            // (no se debe poder asignar uno desactivado desde aquí).
            'gruposAnaliticosActivos' => GrupoAnalitico::activos()->orderBy('nombre')->get(),
            // Para el filtro de la barra superior: todos, incluidos
            // inactivos — un técnico puede seguir teniendo asignado un
            // grupo que después se desactivó, y debe poder filtrarse por él.
            'gruposAnaliticos' => GrupoAnalitico::orderBy('nombre')->get(),
        ]);
    }
}
