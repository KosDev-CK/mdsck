<?php

namespace Modules\GestionTI\Livewire\Catalogos;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Concerns\MergesCatalogDuplicates;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\Puesto;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\UnidadNegocio;

#[Layout('layouts.app')]
class Nucleo extends Component
{
    use WithPagination;
    use MergesCatalogDuplicates;

    public string $tab = 'empresas';

    public ?int $editingId = null;

    public array $form = [];

    public string $search = '';

    public bool $showModal = false;

    /**
     * Config por cada catálogo núcleo: modelo, campos del formulario, reglas
     * de validación y por qué columna mostrar/buscar en la tabla.
     */
    protected function catalogos(): array
    {
        return [
            'empresas' => [
                'label' => 'Empresas',
                'model' => Empresa::class,
                'fields' => ['razon_social', 'nombre_comercial', 'rfc'],
                'rules' => [
                    'form.razon_social' => 'required|string|max:255',
                    'form.nombre_comercial' => 'required|string|max:255',
                    'form.rfc' => 'nullable|string|max:20',
                ],
                'orderBy' => 'nombre_comercial',
                'searchColumns' => ['razon_social', 'nombre_comercial', 'rfc'],
                'mergeReferences' => [
                    ['model' => CentroCosto::class, 'column' => 'empresa_id'],
                    ['model' => Empleado::class, 'column' => 'empresa_id'],
                ],
            ],
            'ubicaciones' => [
                'label' => 'Ubicaciones',
                'model' => Ubicacion::class,
                'fields' => ['nombre', 'nombre_conocido'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.nombre_conocido' => 'nullable|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre', 'nombre_conocido'],
                'mergeReferences' => [
                    ['model' => Empleado::class, 'column' => 'ubicacion_id'],
                    ['model' => StockMinimo::class, 'column' => 'ubicacion_id'],
                    ['model' => Asset::class, 'column' => 'ubicacion_actual_id'],
                ],
            ],
            'areas' => [
                'label' => 'Áreas',
                'model' => Area::class,
                'fields' => ['nombre', 'nombre_conocido'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.nombre_conocido' => 'nullable|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre', 'nombre_conocido'],
                'mergeReferences' => [
                    ['model' => Empleado::class, 'column' => 'area_id'],
                ],
            ],
            'unidades_negocio' => [
                'label' => 'Unidades de Negocio',
                'model' => UnidadNegocio::class,
                'fields' => ['nombre', 'nombre_conocido'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.nombre_conocido' => 'nullable|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre', 'nombre_conocido'],
                'mergeReferences' => [
                    ['model' => Empleado::class, 'column' => 'unidad_negocio_id'],
                ],
            ],
            'puestos' => [
                'label' => 'Puestos',
                'model' => Puesto::class,
                'fields' => ['nombre', 'nombre_conocido'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.nombre_conocido' => 'nullable|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre', 'nombre_conocido'],
                'mergeReferences' => [
                    ['model' => Empleado::class, 'column' => 'puesto_id'],
                ],
            ],
            'centros_costo' => [
                'label' => 'Centros de Costo',
                'model' => CentroCosto::class,
                'fields' => ['codigo', 'nombre', 'nombre_conocido', 'empresa_id'],
                'rules' => [
                    'form.codigo' => 'required|string|max:50',
                    'form.nombre' => 'required|string|max:255',
                    'form.nombre_conocido' => 'nullable|string|max:255',
                    'form.empresa_id' => 'required|exists:empresas,id',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['codigo', 'nombre', 'nombre_conocido'],
                // Ninguna otra tabla del módulo tiene FK hacia centros_costo
                // todavía — fusionar solo elimina el duplicado, sin
                // reasignar nada.
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
            ->when($this->tab === 'centros_costo', fn ($q) => $q->with('empresa'))
            ->when($this->search !== '', function ($q) use ($config) {
                $q->where(function ($q) use ($config) {
                    foreach ($config['searchColumns'] as $column) {
                        $q->orWhere($column, 'like', "%{$this->search}%");
                    }
                });
            })
            ->orderBy($config['orderBy'])
            ->paginate(10);

        return view('gestionti::livewire.catalogos.nucleo', [
            'catalogos' => $catalogos,
            'config' => $config,
            'records' => $records,
            'empresasOptions' => $this->tab === 'centros_costo' ? Empresa::orderBy('nombre_comercial')->get() : null,
            'mergeOptions' => array_key_exists('mergeReferences', $config)
                ? $config['model']::orderBy($config['orderBy'])->get()
                : null,
        ]);
    }
}
