<?php

namespace Modules\GestionTI\Livewire\Inventarios\FichaActivo;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Asset;

/**
 * Ficha de Activo / Trazabilidad (sección 7.13 del spec original) — punto
 * de entrada de la pantalla. Es un buscador simple, no un inventario: sin
 * filtros de estatus/ubicación (a diferencia de `Inventarios\Stock`), solo
 * búsqueda libre por `codigo`/`numero_serie`/`service_tag` y un link "Ver
 * ficha" por fila hacia el detalle. Ver docs/gestionti-progreso.md, Fase 3
 * etapa 10, para el diseño completo (incluye la línea de tiempo, construida
 * en `Show.php`).
 */
#[Layout('layouts.app')]
class Buscar extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $records = Asset::query()
            ->with(['tipoEquipo', 'marca', 'modelo', 'estatus'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('codigo', 'like', "%{$this->search}%")
                        ->orWhere('numero_serie', 'like', "%{$this->search}%")
                        ->orWhere('service_tag', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('codigo')
            ->paginate(10);

        return view('gestionti::livewire.inventarios.ficha-activo.buscar', [
            'records' => $records,
        ]);
    }
}
