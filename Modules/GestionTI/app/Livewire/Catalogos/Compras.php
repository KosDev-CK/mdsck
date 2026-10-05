<?php

namespace Modules\GestionTI\Livewire\Catalogos;

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Concerns\MergesCatalogDuplicates;
use Modules\GestionTI\Models\Almacenamiento;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\CategoriaArticulo;
use Modules\GestionTI\Models\EbsArticulo;
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Procesador;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\Ram;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\RecepcionLinea;
use Modules\GestionTI\Models\SolicitudProveedorLinea;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;

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
                'fields' => [
                    'codigo', 'descripcion', 'unidad_medida', 'categoria_id', 'tipo_equipo_id',
                    'marca_id', 'modelo_id', 'procesador_id', 'ram_id', 'almacenamiento_id', 'es_inventariable',
                ],
                'rules' => [
                    'form.codigo' => 'required|string|max:100',
                    'form.descripcion' => 'required|string|max:255',
                    'form.unidad_medida' => 'required|string|max:50',
                    'form.categoria_id' => 'nullable|exists:categorias_articulo,id',
                    'form.tipo_equipo_id' => 'nullable|exists:tipos_equipo,id',
                    'form.marca_id' => 'nullable|exists:marcas,id',
                    'form.modelo_id' => 'nullable|exists:modelos,id',
                    'form.procesador_id' => 'nullable|exists:procesadores,id',
                    'form.ram_id' => 'nullable|exists:rams,id',
                    'form.almacenamiento_id' => 'nullable|exists:almacenamientos,id',
                    'form.es_inventariable' => 'boolean',
                ],
                'orderBy' => 'codigo',
                'searchColumns' => ['codigo', 'descripcion'],
                // Todas estas tablas tienen FK nullOnDelete hacia
                // articulos_solicitud: sin reasignar, eliminar el artículo
                // las dejaría en blanco sin aviso (se pierde la trazabilidad
                // del activo/solicitud/recepción).
                'mergeReferences' => [
                    ['model' => Asset::class, 'column' => 'articulo_id'],
                    ['model' => SolicitudSicBorrador::class, 'column' => 'articulo_id'],
                    ['model' => SolicitudProveedorLinea::class, 'column' => 'articulo_id'],
                    ['model' => RecepcionLinea::class, 'column' => 'articulo_id'],
                    ['model' => EbsArticulo::class, 'column' => 'articulo_id'],
                ],
            ],
            'categorias' => [
                'label' => 'Categoría',
                'model' => CategoriaArticulo::class,
                // `slug` NUNCA es un campo del formulario (ni crear ni
                // editar) — es interno, ver `save()` y el docblock del
                // modelo `CategoriaArticulo`.
                'fields' => ['nombre', 'es_compra'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.es_compra' => 'boolean',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                'mergeReferences' => [
                    ['model' => ArticuloSolicitud::class, 'column' => 'categoria_id'],
                    ['model' => ProyectoPresupuestoArticulo::class, 'column' => 'categoria_id'],
                ],
            ],
            'lugares_entrega' => [
                'label' => 'Lugar de entrega',
                'model' => LugarEntrega::class,
                'fields' => ['nombre', 'ubicacion_id'],
                'rules' => [
                    'form.nombre' => 'required|string|max:255',
                    'form.ubicacion_id' => 'nullable|exists:ubicaciones,id',
                ],
                'orderBy' => 'nombre',
                'searchColumns' => ['nombre'],
                'mergeReferences' => [
                    ['model' => SolicitudProveedorLinea::class, 'column' => 'lugar_entrega_id'],
                    ['model' => Validador::class, 'column' => 'lugar_entrega_id'],
                    ['model' => Recepcion::class, 'column' => 'lugar_entrega_id'],
                ],
            ],
            // Mapeo EBS -> Artículo estándar (Fase 5) — las filas las crea
            // automáticamente `EbsRequisitionSyncService` (un `firstOrCreate`
            // por cada `item_id` nuevo visto, sin mapear), este tab SOLO
            // sirve para revisar/corregir el `articulo_id` mapeado de cada
            // una — sin "Nuevo" (`create()` no-opea para este tab) ni
            // "Eliminar" (`delete()` ídem), ver el blade.
            'ebs_articulos' => [
                'label' => 'Artículos EBS',
                'model' => EbsArticulo::class,
                'fields' => ['articulo_id'],
                'rules' => [
                    'form.articulo_id' => 'nullable|exists:articulos_solicitud,id',
                ],
                'orderBy' => 'ebs_item_id',
                'searchColumns' => ['ebs_item_id', 'ebs_item_description'],
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
        // Tab "Artículos EBS" — las filas las crea el sync, nunca el
        // usuario; sin botón "Nuevo" en el blade, este no-op es la segunda
        // capa de defensa.
        if ($this->tab === 'ebs_articulos') {
            return;
        }

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

    /**
     * Los selects opcionales mandan '' para la opción "Sin asignar" —
     * normalizarlos a null antes de validar/guardar para que la FK nullable
     * no reciba una cadena vacía. Generalizado desde solo `tipo_equipo_id`
     * para cubrir también `marca_id`/`modelo_id`/`procesador_id`/`ram_id`/
     * `almacenamiento_id`/`categoria_id` del tab de Artículos.
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach (['tipo_equipo_id', 'marca_id', 'modelo_id', 'procesador_id', 'ram_id', 'almacenamiento_id', 'categoria_id', 'articulo_id', 'ubicacion_id'] as $field) {
            if (array_key_exists($field, $this->form) && $this->form[$field] === '') {
                $this->form[$field] = null;
            }
        }
    }

    /**
     * Genera un `slug` único (`Str::slug($nombre, '_')`) para una Categoría
     * nueva — nunca se vuelve a tocar después de crearse (protege el
     * matcheo de lógica de negocio real contra `slug`, ver el docblock de
     * `Modules\GestionTI\Models\CategoriaArticulo`). Si el slug generado ya
     * existe, le agrega un sufijo numérico incremental hasta que sea único
     * — mismo criterio defensivo que `Asset::generateCodigo()`.
     */
    private function generarSlugUnico(string $nombre): string
    {
        $base = Str::slug($nombre, '_');
        $slug = $base;
        $sufijo = 1;

        while (CategoriaArticulo::where('slug', $slug)->exists()) {
            $sufijo++;
            $slug = "{$base}_{$sufijo}";
        }

        return $slug;
    }

    public function save(): void
    {
        $config = $this->catalogos()[$this->tab];

        $this->nullifyEmptyForeignKeys();

        // El toggle "Es inventariable" no se toca por default en `create()`
        // (arranca en null, mismo `array_fill_keys(...)` genérico que usan
        // todos los campos) — normalizarlo a un booleano real antes de
        // validar, para que la regla `boolean` no lo rechace.
        if (array_key_exists('es_inventariable', $this->form)) {
            $this->form['es_inventariable'] = (bool) $this->form['es_inventariable'];
        }

        // Mismo tratamiento para el toggle "Va a Compras" del tab Categoría.
        if (array_key_exists('es_compra', $this->form)) {
            $this->form['es_compra'] = (bool) $this->form['es_compra'];
        }

        $this->validate($config['rules']);

        if ($this->editingId) {
            $config['model']::findOrFail($this->editingId)->update($this->form);
        } else {
            if ($this->tab === 'categorias') {
                $this->form['slug'] = $this->generarSlugUnico($this->form['nombre']);
            }

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
        // Tab "Artículos EBS" — sin "Eliminar" (ver docblock de `create()`
        // arriba); si se borrara, el sync la recrearía sin mapear en la
        // siguiente corrida de todos modos.
        if ($this->tab === 'ebs_articulos') {
            return;
        }

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
            ->when($this->tab === 'articulos_solicitud', fn ($q) => $q->with(['tipoEquipo', 'marca', 'modelo', 'categoria']))
            ->when($this->tab === 'ebs_articulos', fn ($q) => $q->with('articulo'))
            ->when($this->tab === 'lugares_entrega', fn ($q) => $q->with('ubicacion'))
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
            'categoriaOptions' => $this->tab === 'articulos_solicitud'
                ? CategoriaArticulo::where('activo', true)->orderBy('nombre')->get()
                : null,
            'marcaOptions' => $this->tab === 'articulos_solicitud'
                ? Marca::where('activo', true)->orderBy('nombre')->get()
                : null,
            'modeloOptions' => $this->tab === 'articulos_solicitud'
                ? Modelo::where('activo', true)->with('marca')->orderBy('nombre')->get()
                : null,
            'procesadorOptions' => $this->tab === 'articulos_solicitud'
                ? Procesador::where('activo', true)->orderBy('nombre')->get()
                : null,
            'ramOptions' => $this->tab === 'articulos_solicitud'
                ? Ram::where('activo', true)->orderBy('nombre')->get()
                : null,
            'ubicacionOptions' => $this->tab === 'lugares_entrega'
                ? Ubicacion::where('activo', true)->orderBy('nombre')->get()
                : null,
            'articuloMapeadoOptions' => $this->tab === 'ebs_articulos'
                ? ArticuloSolicitud::where('activo', true)->orderBy('descripcion')->get()
                : null,
            'almacenamientoOptions' => $this->tab === 'articulos_solicitud'
                ? Almacenamiento::where('activo', true)->orderBy('nombre')->get()
                : null,
            'mergeOptions' => array_key_exists('mergeReferences', $config)
                ? $config['model']::orderBy($config['orderBy'])->get()
                : null,
        ]);
    }
}
