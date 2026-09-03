<?php

namespace Modules\GestionTI\Livewire\Catalogos;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\Puesto;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\UnidadNegocio;

#[Layout('layouts.app')]
class Empleados extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [];

    #[Url(as: 'search')]
    public string $search = '';

    public bool $showModal = false;

    protected function rules(): array
    {
        return [
            'form.numero_empleado' => [
                'required', 'string', 'max:255',
                Rule::unique('empleados', 'numero_empleado')->ignore($this->editingId),
            ],
            'form.nombre' => 'required|string|max:255',
            'form.correo' => 'nullable|email|max:255',
            'form.rfc' => 'nullable|string|max:255',
            'form.puesto_id' => 'nullable|exists:puestos,id',
            'form.area_id' => 'nullable|exists:areas,id',
            'form.ubicacion_id' => 'nullable|exists:ubicaciones,id',
            'form.unidad_negocio_id' => 'nullable|exists:unidades_negocio,id',
            'form.empresa_id' => 'nullable|exists:empresas,id',
            'form.jefe_inmediato_id' => 'nullable|exists:empleados,id',
            'form.director_id' => 'nullable|exists:empleados,id',
            'form.director_ejecutivo_id' => 'nullable|exists:empleados,id',
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
            'numero_empleado' => null,
            'nombre' => null,
            'correo' => null,
            'rfc' => null,
            'puesto_id' => null,
            'area_id' => null,
            'ubicacion_id' => null,
            'unidad_negocio_id' => null,
            'empresa_id' => null,
            'jefe_inmediato_id' => null,
            'director_id' => null,
            'director_ejecutivo_id' => null,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = Empleado::findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'numero_empleado' => $record->numero_empleado,
            'nombre' => $record->nombre,
            'correo' => $record->correo,
            'rfc' => $record->rfc,
            'puesto_id' => $record->puesto_id,
            'area_id' => $record->area_id,
            'ubicacion_id' => $record->ubicacion_id,
            'unidad_negocio_id' => $record->unidad_negocio_id,
            'empresa_id' => $record->empresa_id,
            'jefe_inmediato_id' => $record->jefe_inmediato_id,
            'director_id' => $record->director_id,
            'director_ejecutivo_id' => $record->director_ejecutivo_id,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Los selects de FK opcionales mandan '' para "Selecciona.../Sin
     * asignar" — normalizar a null antes de validar/guardar, si no la regla
     * `exists` los rechaza (una cadena vacía nunca hace match con un id) y
     * el usuario no puede guardar dejando el campo sin asignar.
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach (['puesto_id', 'area_id', 'ubicacion_id', 'unidad_negocio_id', 'empresa_id', 'jefe_inmediato_id', 'director_id', 'director_ejecutivo_id'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }
    }

    public function save(): void
    {
        $this->nullifyEmptyForeignKeys();
        $this->validate();

        if ($this->editingId) {
            Empleado::findOrFail($this->editingId)->update($this->form);
        } else {
            Empleado::create($this->form);
        }

        $this->showModal = false;
        session()->flash('status', 'Guardado correctamente.');
    }

    public function toggleActivo(int $id): void
    {
        $record = Empleado::findOrFail($id);
        $record->update(['activo' => ! $record->activo]);
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
        $records = Empleado::query()
            ->with(['puesto', 'area', 'ubicacion', 'unidadNegocio', 'empresa', 'jefeInmediato', 'director', 'directorEjecutivo'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('numero_empleado', 'like', "%{$this->search}%")
                        ->orWhere('nombre', 'like', "%{$this->search}%")
                        ->orWhere('correo', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(10);

        return view('gestionti::livewire.catalogos.empleados', [
            'records' => $records,
            'puestoOptions' => Puesto::where('activo', true)->orderBy('nombre')->get(),
            'areaOptions' => Area::where('activo', true)->orderBy('nombre')->get(),
            'ubicacionOptions' => Ubicacion::where('activo', true)->orderBy('nombre')->get(),
            'unidadNegocioOptions' => UnidadNegocio::where('activo', true)->orderBy('nombre')->get(),
            'empresaOptions' => Empresa::where('activo', true)->orderBy('nombre_comercial')->get(),
            // Reutilizada por los 3 selects de línea de mando (Jefe inmediato o
            // Gerente / Director / Director Ejecutivo) — misma lógica de filtrado
            // (activos, excluyendo el propio registro al editar) para los tres.
            'empleadoOptions' => Empleado::where('activo', true)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->orderBy('nombre')
                ->get(),
        ]);
    }
}
