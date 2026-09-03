<?php

namespace Modules\GestionTI\Livewire\Catalogos;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Concerns\MergesCatalogDuplicates;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\AssetCompliance;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Licencia;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\PeriodicidadMantenimiento;
use Modules\GestionTI\Models\Propiedad;
use Modules\GestionTI\Models\SistemaOperativo;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;

#[Layout('layouts.app')]
class Inventario extends Component
{
    use WithPagination;
    use MergesCatalogDuplicates;

    public string $tab = 'tipo_equipo';

    public ?int $editingId = null;

    public array $form = [];

    public string $search = '';

    public bool $showModal = false;

    /**
     * Config por cada catálogo de inventario "simple" (incluye Estatus de
     * Activo, que solo se diferencia por tener `codigo` además de `nombre`):
     * modelo, campos del formulario, reglas base de validación y por qué
     * columna mostrar/buscar en la tabla. Las 2 pestañas "regla" (Periodicidad
     * de Mantenimiento y Stock Mínimo) también se describen aquí para
     * reutilizar el mismo `setTab`/`create`/`edit`/`toggleActivo`/`cancel`
     * genéricos, pero sus reglas de unicidad dinámica (dependen de
     * `$editingId`/de otro campo del formulario) se resuelven aparte en
     * `rules()`, y su formulario/columnas de tabla tienen ramas propias en la
     * vista en vez de forzar el patrón `nombre` genérico.
     */
    protected function catalogos(): array
    {
        return [
            'tipo_equipo' => [
                'label' => 'Tipo de Equipo',
                'model' => TipoEquipo::class,
                'fields' => ['nombre', 'nombre_conocido', 'en_alcance'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.nombre_conocido' => 'nullable|string|max:255',
                    'form.en_alcance' => 'boolean',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre', 'nombre_conocido'],
                'mergeReferences' => [
                    ['model' => ArticuloSolicitud::class, 'column' => 'tipo_equipo_id'],
                    ['model' => PeriodicidadMantenimiento::class, 'column' => 'tipo_equipo_id'],
                    ['model' => StockMinimo::class, 'column' => 'tipo_equipo_id'],
                    ['model' => Asset::class, 'column' => 'tipo_equipo_id'],
                ],
            ],
            'marcas' => [
                'label' => 'Marca',
                'model' => Marca::class,
                'fields' => ['nombre'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                'mergeReferences' => [
                    ['model' => Modelo::class, 'column' => 'marca_id'],
                    ['model' => Asset::class, 'column' => 'marca_id'],
                ],
            ],
            'modelos' => [
                'label' => 'Modelo',
                'model' => Modelo::class,
                'fields' => ['nombre', 'marca_id'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.marca_id' => 'required|exists:marcas,id',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                'mergeReferences' => [
                    ['model' => Asset::class, 'column' => 'modelo_id'],
                ],
            ],
            'sistemas_operativos' => [
                'label' => 'Sistema Operativo',
                'model' => SistemaOperativo::class,
                'fields' => ['nombre'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                // Sin FK real en Asset (ver nota de esquema de Fase 2 — el
                // valor queda como texto crudo dentro de
                // `especificaciones->sistema_operativo`) — fusionar solo
                // elimina el duplicado, sin reasignar nada.
                'mergeReferences' => [],
            ],
            'licencias' => [
                'label' => 'Licencia',
                'model' => Licencia::class,
                'fields' => ['nombre'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                'mergeReferences' => [
                    ['model' => AssetCompliance::class, 'column' => 'licencia_1_id'],
                    ['model' => AssetCompliance::class, 'column' => 'licencia_2_id'],
                ],
            ],
            'propiedades' => [
                'label' => 'Propiedad',
                'model' => Propiedad::class,
                'fields' => ['nombre'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                'mergeReferences' => [
                    ['model' => Asset::class, 'column' => 'propiedad_id'],
                ],
            ],
            'validadores' => [
                'label' => 'Validador',
                'model' => Validador::class,
                'fields' => ['nombre'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                'mergeReferences' => [
                    ['model' => Asset::class, 'column' => 'dado_de_alta_por_id'],
                    ['model' => AssetCompliance::class, 'column' => 'validado_por_id'],
                    ['model' => AssetAssignment::class, 'column' => 'responsable_entrega_id'],
                    ['model' => Mantenimiento::class, 'column' => 'realizado_por_id'],
                ],
            ],
            'estatus_activo' => [
                'label' => 'Estatus de Activo',
                'model' => EstatusActivo::class,
                'fields' => ['codigo', 'nombre'],
                'rules' => [
                    'form.codigo' => 'required|string|max:100',
                    'form.nombre' => 'required|string|max:255',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['codigo', 'nombre'],
                'mergeReferences' => [
                    ['model' => Asset::class, 'column' => 'estatus_id'],
                ],
            ],
            // Periodicidad de Mantenimiento y Stock Mínimo NO tienen
            // `mergeReferences` a propósito — son tablas "regla" sin nada
            // más que las referencie, y su propia constraint de unicidad ya
            // hace que "fusionar duplicados" no sea un concepto aplicable
            // (dos filas para el mismo tipo de equipo/ubicación no pueden
            // coexistir para empezar). El botón "Fusionar duplicados" se
            // oculta para estos 2 tabs en la vista.
            'periodicidad_mantenimiento' => [
                'label' => 'Periodicidad de Mantenimiento',
                'model' => PeriodicidadMantenimiento::class,
                'fields' => ['tipo_equipo_id', 'meses_sugeridos'],
                'rules' => [
                    'form.tipo_equipo_id' => 'required|exists:tipos_equipo,id',
                    'form.meses_sugeridos' => 'required|integer|min:1',
                ],
                'orderBy' => 'id',
                'searchColumns' => [],
            ],
            'stock_minimo' => [
                'label' => 'Stock Mínimo',
                'model' => StockMinimo::class,
                'fields' => ['tipo_equipo_id', 'ubicacion_id', 'cantidad_minima'],
                'rules' => [
                    'form.tipo_equipo_id' => 'required|exists:tipos_equipo,id',
                    'form.ubicacion_id' => 'required|exists:ubicaciones,id',
                    'form.cantidad_minima' => 'required|integer|min:0',
                ],
                'orderBy' => 'id',
                'searchColumns' => [],
            ],
        ];
    }

    /**
     * Reglas de validación efectivas para la pestaña actual. La mayoría vienen
     * tal cual de `catalogos()`; las 3 excepciones con una restricción
     * `unique` que depende de `$editingId` (y, en Stock Mínimo, de otro campo
     * del formulario) se resuelven aquí en vez de en el arreglo estático.
     */
    protected function rules(): array
    {
        $rules = $this->catalogos()[$this->tab]['rules'];

        if ($this->tab === 'estatus_activo') {
            $rules['form.codigo'] = [
                'required', 'string', 'max:100',
                Rule::unique('estatus_activo', 'codigo')->ignore($this->editingId),
            ];
        }

        if ($this->tab === 'periodicidad_mantenimiento') {
            $rules['form.tipo_equipo_id'] = [
                'required', 'exists:tipos_equipo,id',
                Rule::unique('periodicidades_mantenimiento', 'tipo_equipo_id')->ignore($this->editingId),
            ];
        }

        if ($this->tab === 'stock_minimo') {
            $rules['form.tipo_equipo_id'] = [
                'required', 'exists:tipos_equipo,id',
                Rule::unique('stocks_minimos', 'tipo_equipo_id')
                    ->where(fn ($query) => $query->where('ubicacion_id', $this->form['ubicacion_id'] ?? null))
                    ->ignore($this->editingId),
            ];
        }

        return $rules;
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

        // "En alcance" es una decisión de negocio real (no un flag de ciclo
        // de vida activo/inactivo), pero la mayoría de los tipos de equipo sí
        // están en alcance — arranca marcado por default, igual que la
        // columna en la migración (`default(true)`).
        if ($this->tab === 'tipo_equipo') {
            $this->form['en_alcance'] = true;
        }

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
        $this->validate($this->rules());

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
            ->when($this->tab === 'modelos', fn ($q) => $q->with('marca'))
            ->when($this->tab === 'periodicidad_mantenimiento', fn ($q) => $q->with('tipoEquipo'))
            ->when($this->tab === 'stock_minimo', fn ($q) => $q->with(['tipoEquipo', 'ubicacion']))
            ->when($this->search !== '' && ! empty($config['searchColumns']), function ($q) use ($config) {
                $q->where(function ($q) use ($config) {
                    foreach ($config['searchColumns'] as $column) {
                        $q->orWhere($column, 'like', "%{$this->search}%");
                    }
                });
            })
            ->orderBy($config['orderBy'])
            ->paginate(10);

        return view('gestionti::livewire.catalogos.inventario', [
            'catalogos' => $catalogos,
            'config' => $config,
            'records' => $records,
            'marcaOptions' => $this->tab === 'modelos'
                ? Marca::where('activo', true)->orderBy('nombre')->get()
                : null,
            'tipoEquipoOptions' => in_array($this->tab, ['periodicidad_mantenimiento', 'stock_minimo'], true)
                ? TipoEquipo::where('activo', true)->orderBy('nombre')->get()
                : null,
            'ubicacionOptions' => $this->tab === 'stock_minimo'
                ? Ubicacion::where('activo', true)->orderBy('nombre')->get()
                : null,
            'mergeOptions' => array_key_exists('mergeReferences', $config)
                ? $config['model']::orderBy($config['orderBy'])->get()
                : null,
        ]);
    }
}
