<?php

namespace Modules\GestionTI\Livewire\MesaServicio;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Ticket;

#[Layout('layouts.app')]
class Tickets extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [];

    public string $search = '';

    public bool $showModal = false;

    protected function rules(): array
    {
        return [
            'form.sdp_id' => 'nullable|string|max:255',
            'form.sdp_display_id' => 'nullable|string|max:255',
            'form.fecha' => 'required|date',
            'form.empleado_id' => 'required|exists:empleados,id',
            'form.observaciones' => 'nullable|string',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [
            'sdp_id' => null,
            'sdp_display_id' => null,
            'fecha' => now()->format('Y-m-d'),
            'empleado_id' => null,
            'observaciones' => null,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = Ticket::findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'sdp_id' => $record->sdp_id,
            'sdp_display_id' => $record->sdp_display_id,
            'fecha' => optional($record->fecha)->format('Y-m-d'),
            'empleado_id' => $record->empleado_id,
            'observaciones' => $record->observaciones,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            Ticket::findOrFail($this->editingId)->update($this->form);
        } else {
            Ticket::create($this->form);
        }

        $this->showModal = false;
        session()->flash('status', 'Guardado correctamente.');
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->form = [];
        $this->resetValidation();
    }

    public function render()
    {
        $records = Ticket::query()
            ->with('empleado')
            ->withCount('solicitudesSic')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('sdp_id', 'like', "%{$this->search}%")
                        ->orWhere('sdp_display_id', 'like', "%{$this->search}%")
                        ->orWhereHas('empleado', fn ($q) => $q->where('nombre', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('fecha')
            ->paginate(10);

        return view('gestionti::livewire.mesa-servicio.tickets', [
            'records' => $records,
            'empleadoOptions' => Empleado::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
