<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\MesaServicio\Models\GrupoAnalitico;

/**
 * Catálogo local, 100% manual (sin sync con SDP), de "grupos analíticos" —
 * ver Modules\MesaServicio\Models\GrupoAnalitico para el porqué. CRUD
 * simple con alta + edición inline. Tiene dos formas de retirar un grupo:
 * toggleActivo (lo oculta como opción nueva sin tocar a los técnicos que ya
 * lo tenían asignado) y delete() (borrado físico real, ver su docblock).
 */
#[Layout('layouts.app')]
class GruposAnaliticos extends Component
{
    // --- Alta de un grupo nuevo ---
    public string $newNombre = '';

    public string $newDescripcion = '';

    // --- Edición inline por fila ---
    public ?int $editingId = null;

    public string $editNombre = '';

    public string $editDescripcion = '';

    /**
     * Reglas compartidas entre alta y edición — $prefix distingue el set de
     * propiedades ("new"/"edit"), $ignoreId excluye el propio registro de la
     * validación de unicidad al editar.
     */
    private function reglas(string $prefix, ?int $ignoreId): array
    {
        return [
            "{$prefix}Nombre" => ['required', 'string', 'max:255', Rule::unique('grupos_analiticos', 'nombre')->ignore($ignoreId)],
            "{$prefix}Descripcion" => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addGrupo(): void
    {
        $this->validate($this->reglas('new', null));

        GrupoAnalitico::create([
            'nombre' => $this->newNombre,
            'descripcion' => $this->newDescripcion !== '' ? $this->newDescripcion : null,
        ]);

        $this->reset(['newNombre', 'newDescripcion']);
        session()->flash('status', 'Grupo analítico agregado.');
    }

    public function edit(int $id): void
    {
        $grupo = GrupoAnalitico::findOrFail($id);

        $this->editingId = $id;
        $this->editNombre = $grupo->nombre;
        $this->editDescripcion = (string) ($grupo->descripcion ?? '');
        $this->resetValidation();
    }

    public function update(): void
    {
        $this->validate($this->reglas('edit', $this->editingId));

        GrupoAnalitico::findOrFail($this->editingId)->update([
            'nombre' => $this->editNombre,
            'descripcion' => $this->editDescripcion !== '' ? $this->editDescripcion : null,
        ]);

        $this->cancelEdit();
        session()->flash('status', 'Grupo analítico actualizado.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset(['editNombre', 'editDescripcion']);
        $this->resetValidation();
    }

    public function toggleActivo(int $id): void
    {
        $grupo = GrupoAnalitico::findOrFail($id);
        $grupo->update(['activo' => ! $grupo->activo]);
    }

    /**
     * Borrado físico del catálogo (a diferencia de toggleActivo, que solo
     * desactiva) — pedido explícito de UI para "mantenimiento de catálogo".
     * Sin `mergeReferences` declarado aquí (no es config-driven como
     * Nucleo/Compras/Inventario), así que la red de seguridad es el
     * try/catch sobre la restricción de integridad referencial: hoy
     * sdp_technicians.grupo_analitico_id es nullOnDelete (no debería
     * fallar), pero el catch cubre cualquier FK futura que se agregue como
     * restrict sin actualizar este método.
     */
    public function delete(int $id): void
    {
        $grupo = GrupoAnalitico::findOrFail($id);

        try {
            $grupo->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            session()->flash('error', 'No se puede eliminar: este registro está en uso en otro lugar del sistema.');

            return;
        }

        session()->flash('status', 'Eliminado correctamente.');
    }

    public function render()
    {
        return view('mesaservicio::livewire.catalogos.grupos-analiticos', [
            'grupos' => GrupoAnalitico::orderBy('nombre')->get(),
        ]);
    }
}
