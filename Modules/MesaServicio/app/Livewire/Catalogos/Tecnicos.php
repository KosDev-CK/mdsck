<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
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

    /**
     * es_nivel_1 es el único campo editable de este catálogo — nombre,
     * correo, puesto y activo se sobreescriben en cada corrida de
     * sdp:sync-technicians, este toggle nunca se toca desde ahí.
     */
    public function toggleNivel1(int $id): void
    {
        $technician = SdpTechnician::findOrFail($id);
        $technician->update(['es_nivel_1' => ! $technician->es_nivel_1]);
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
            ->orderBy('nombre')
            ->paginate(15);

        return view('mesaservicio::livewire.catalogos.tecnicos', [
            'records' => $records,
        ]);
    }
}
