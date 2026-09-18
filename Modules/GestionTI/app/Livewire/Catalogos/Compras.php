<?php

namespace Modules\GestionTI\Livewire\Catalogos;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Concerns\MergesCatalogDuplicates;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\TipoEquipo;

#[Layout('layouts.app')]
class Compras extends Component
{
    use WithPagination;
    use MergesCatalogDuplicates;

    public string $tab = 'proveedores';

    public ?int $editingId = null;

    public array $form = [];

    public string $search = '';

    public bool $showModal = false;

    /**
     * Config por cada catálogo de compras: modelo, campos del formulario,
     * reglas de validación y por qué columna mostrar/buscar en la tabla.
     */
    protected function catalogos(): array
    {
        return [
            'proveedores' => [
                'label' => 'Proveedor',
                'model' => Proveedor::class,
                'fields' => [
                    'razon_social', 'nombre_comercial', 'rfc',
                    'contacto_nombre', 'contacto_telefono', 'contacto_correo',
                ],
                'rules' => [
                    'form.razon_social' => 'required|string|max:255',
                    'form.nombre_comercial' => 'required|string|max:255',
                    'form.rfc' => 'nullable|string|max:20',
                    'form.contacto_nombre' => 'nullable|string|max:255',
                    'form.contacto_telefono' => 'nullable|string|max:50',
                    'form.contacto_correo' => 'nullable|email|max:255',
                ],
                'orderBy' => 'nombre_comercial',
                'searchColumns' => ['razon_social', 'nombre_comercial', 'rfc', 'contacto_nombre'],
                'mergeReferences' => [
                    ['model' => Asset::class, 'column' => 'vendor_id'],
                    ['model' => Mantenimiento::class, 'column' => 'vendor_id'],
                ],
            ],
            'articulos_solicitud' => [
                'label' => 'Artículo de Solicitud',
                'model' => ArticuloSolicitud::class,
                'fields' => ['codigo', 'descripcion', 'unidad_medida', 'categoria', 'tipo_equipo_id'],
                'rules' => [
                    'form.codigo' => 'required|string|max:100',
                    'form.descripcion' => 'required|string|max:255',
                    'form.unidad_medida' => 'required|string|max:50',
                    'form.categoria' => 'nullable|string|max:255',
                    'form.tipo_equipo_id' => 'nullable|exists:tipos_equipo,id',
                ],
                'orderBy' => 'codigo',
                'searchColumns' => ['codigo', 'descripcion', 'categoria'],
                // Ninguna otra tabla del módulo tiene FK hacia
                // articulos_solicitud todavía — fusionar solo elimina el
                // duplicado, sin reasignar nada.
                'mergeReferences' => [],
            ],
        ];
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
        $this->resetPage();
        $this->cancel();
        $this->cancelMerge();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = array_fill_keys($this->catalogos()[$this->tab]['fields'], null);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $config = $this->catalogos()[$this->tab];
        $record = $config['model']::findOrFail($id);

        $this->editingId = $id;
        $this->form = collect($config['fields'])->mapWithKeys(fn ($field) => [$field => $record->{$field}])->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $config = $this->catalogos()[$this->tab];

        // El select de "Tipo de equipo" manda '' para la opción "Sin
        // asignar" — normalizarlo a null antes de validar/guardar para que
        // la FK nullable no reciba una cadena vacía.
        if (array_key_exists('tipo_equipo_id', $this->form) && $this->form['tipo_equipo_id'] === '') {
            $this->form['tipo_equipo_id'] = null;
        }

        $this->validate($config['rules']);

        if ($this->editingId) {
            $config['model']::findOrFail($this->editingId)->update($this->form);
        } else {
            $config['model']::create($this->form);
        }

        $this->showModal = false;
        session()->flash('status', 'Guardado correctamente.');
    }

    public function toggleActivo(int $id): void
    {
        $record = $this->catalogos()[$this->tab]['model']::findOrFail($id);
        $record->update(['activo' => ! $record->activo]);
    }

    /**
     * Borrado físico (a diferencia de toggleActivo) — pedido explícito de UI
     * para "mantenimiento de catálogo". Reutiliza el mismo `mergeReferences`
     * ya declarado en `catalogos()` para "Fusionar duplicados": si algo lo
     * referencia, se rechaza el borrado en vez de dejar huérfanos.
     */
    public function delete(int $id): void
    {
        $config = $this->catalogos()[$this->tab];
        $record = $config['model']::findOrFail($id);

        if (array_key_exists('mergeReferences', $config)) {
            $total = 0;

            foreach ($config['mergeReferences'] as $reference) {
                $total += $reference['model']::where($reference['column'], $id)->count();
            }

            if ($total > 0) {
                session()->flash('error', "No se puede eliminar: {$total} registro(s) lo están usando. Usa \"Fusionar duplicados\" para reasignarlos primero.");

                return;
            }
        }

        try {
            $record->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            session()->flash('error', 'No se puede eliminar: este registro está en uso en otro lugar del sistema.');

            return;
        }

        session()->flash('status', 'Eliminado correctamente.');
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
        $catalogos = $this->catalogos();
        $config = $catalogos[$this->tab];

        $records = $config['model']::query()
            ->when($this->tab === 'articulos_solicitud', fn ($q) => $q->with('tipoEquipo'))
            ->when($this->search !== '', function ($q) use ($config) {
                $q->where(function ($q) use ($config) {
                    foreach ($config['searchColumns'] as $column) {
                        $q->orWhere($column, 'like', "%{$this->search}%");
                    }
                });
            })
            ->orderBy($config['orderBy'])
            ->paginate(10);

        return view('gestionti::livewire.catalogos.compras', [
            'catalogos' => $catalogos,
            'config' => $config,
            'records' => $records,
            'tipoEquipoOptions' => $this->tab === 'articulos_solicitud'
                ? TipoEquipo::where('activo', true)->orderBy('nombre')->get()
                : null,
            'mergeOptions' => array_key_exists('mergeReferences', $config)
                ? $config['model']::orderBy($config['orderBy'])->get()
                : null,
        ]);
    }
}
