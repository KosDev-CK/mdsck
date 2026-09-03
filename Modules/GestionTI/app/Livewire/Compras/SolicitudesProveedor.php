<?php

namespace Modules\GestionTI\Livewire\Compras;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;

#[Layout('layouts.app')]
class SolicitudesProveedor extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [];

    /** @var array<int, array{id: ?int, articulo_id: ?int, descripcion_libre: ?string, cantidad_solicitada: int, precio_unitario_cotizado: ?float, es_activo_inventariable: bool}> */
    public array $lineas = [];

    #[Url(as: 'search')]
    public string $search = '';

    public string $estatusFilter = '';

    public bool $showModal = false;

    protected function rules(): array
    {
        return [
            'form.folio' => ['required', 'string', 'max:100', Rule::unique('solicitudes_proveedor', 'folio')->ignore($this->editingId)],
            'form.vendor_id' => 'required|exists:proveedores,id',
            'form.fecha_solicitud' => 'required|date',
            'form.ticket_id' => 'nullable|exists:tickets,id',
            'form.sic_id' => 'nullable|exists:solicitudes_sic_borrador,id',
            'form.proyecto_presupuesto_articulo_id' => 'nullable|exists:proyecto_presupuesto_articulos,id',
            'form.tipo_solicitud' => ['required', Rule::in(SolicitudProveedor::TIPOS)],
            'lineas' => 'required|array|min:1',
            'lineas.*.articulo_id' => 'nullable|exists:articulos_solicitud,id',
            'lineas.*.descripcion_libre' => 'nullable|string|max:255',
            'lineas.*.cantidad_solicitada' => 'required|integer|min:1',
            'lineas.*.precio_unitario_cotizado' => 'nullable|numeric|min:0',
            'lineas.*.es_activo_inventariable' => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingEstatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Folio sugerido (SP-YYYYMMDD-###, secuencial por día) precargado en el
     * formulario de creación pero completamente editable — el usuario puede
     * capturar cualquier otro folio manualmente antes de guardar. La regla
     * `unique` de `rules()` valida el valor final, no la sugerencia.
     */
    private function suggestFolio(): string
    {
        $prefix = 'SP-'.now()->format('Ymd').'-';
        $seq = SolicitudProveedor::where('folio', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Los selects opcionales de Ticket/SIC/Artículo de Proyecto mandan '' para
     * "Sin asignar" — normalizar a null antes de validar/guardar (mismo
     * patrón ya documentado en Compras.php/SolicitudesSic.php de fases
     * previas).
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach (['ticket_id', 'sic_id', 'proyecto_presupuesto_articulo_id'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }
    }

    public function addLinea(): void
    {
        $this->lineas[] = [
            'id' => null,
            'articulo_id' => null,
            'descripcion_libre' => null,
            'cantidad_solicitada' => 1,
            'precio_unitario_cotizado' => null,
            'es_activo_inventariable' => false,
        ];
    }

    public function removeLinea(int $index): void
    {
        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [
            'folio' => $this->suggestFolio(),
            'vendor_id' => null,
            'fecha_solicitud' => now()->format('Y-m-d'),
            'ticket_id' => null,
            'sic_id' => null,
            'proyecto_presupuesto_articulo_id' => null,
            'tipo_solicitud' => 'regular',
        ];
        $this->lineas = [];
        $this->addLinea();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = SolicitudProveedor::with('lineas')->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'folio' => $record->folio,
            'vendor_id' => $record->vendor_id,
            'fecha_solicitud' => optional($record->fecha_solicitud)->format('Y-m-d'),
            'ticket_id' => $record->ticket_id,
            'sic_id' => $record->sic_id,
            'proyecto_presupuesto_articulo_id' => $record->proyecto_presupuesto_articulo_id,
            'tipo_solicitud' => $record->tipo_solicitud,
        ];
        $this->lineas = $record->lineas->map(fn ($linea) => [
            'id' => $linea->id,
            'articulo_id' => $linea->articulo_id,
            'descripcion_libre' => $linea->descripcion_libre,
            'cantidad_solicitada' => $linea->cantidad_solicitada,
            'precio_unitario_cotizado' => $linea->precio_unitario_cotizado,
            'es_activo_inventariable' => $linea->es_activo_inventariable,
        ])->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * "El origen de una Solicitud a Proveedor es una SIC O un artículo de
     * proyecto, no ambos" — regla de negocio del spec. El error se agrega
     * sobre los 2 campos a la vez para que el mensaje aparezca sin importar
     * cuál de los 2 selects mire el usuario primero.
     */
    private function validateOrigenUnico(): void
    {
        if (! empty($this->form['sic_id']) && ! empty($this->form['proyecto_presupuesto_articulo_id'] ?? null)) {
            $mensaje = 'El origen debe ser una SIC o un artículo de proyecto, no ambos.';
            $this->addError('form.sic_id', $mensaje);
            $this->addError('form.proyecto_presupuesto_articulo_id', $mensaje);
        }
    }

    /**
     * Cada línea necesita exactamente uno de articulo_id/descripcion_libre
     * (no ambos, no ninguno) — regla dependiente entre 2 campos del mismo
     * renglón que una regla wildcard simple no puede expresar, mismo patrón
     * que `validateSubFieldOptions()` en Modules\FormBuilder\Livewire\Forms\Builder.
     */
    private function validateLineas(): void
    {
        foreach ($this->lineas as $i => $linea) {
            $tieneArticulo = ! empty($linea['articulo_id']);
            $tieneDescripcion = trim((string) ($linea['descripcion_libre'] ?? '')) !== '';

            if ($tieneArticulo && $tieneDescripcion) {
                $this->addError("lineas.$i.articulo_id", 'Elige un artículo del catálogo o captura una descripción libre, no ambos.');
            } elseif (! $tieneArticulo && ! $tieneDescripcion) {
                $this->addError("lineas.$i.articulo_id", 'Elige un artículo del catálogo o captura una descripción libre.');
            }
        }
    }

    public function save(): void
    {
        $this->nullifyEmptyForeignKeys();
        $this->validate($this->rules());
        $this->validateOrigenUnico();
        $this->validateLineas();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($this->editingId) {
            $record = SolicitudProveedor::findOrFail($this->editingId);
            $record->update($this->form);
        } else {
            $record = SolicitudProveedor::create(array_merge($this->form, [
                'estatus' => SolicitudProveedor::ESTATUS_SOLICITADA,
            ]));
        }

        $keptIds = [];
        foreach ($this->lineas as $linea) {
            $attributes = [
                'articulo_id' => $linea['articulo_id'] ?: null,
                'descripcion_libre' => $linea['descripcion_libre'] ?: null,
                'cantidad_solicitada' => $linea['cantidad_solicitada'],
                'precio_unitario_cotizado' => $linea['precio_unitario_cotizado'] !== '' ? $linea['precio_unitario_cotizado'] : null,
                'es_activo_inventariable' => (bool) ($linea['es_activo_inventariable'] ?? false),
            ];

            if (! empty($linea['id'])) {
                $record->lineas()->where('id', $linea['id'])->update($attributes);
                $keptIds[] = $linea['id'];
            } else {
                $nueva = $record->lineas()->create($attributes);
                $keptIds[] = $nueva->id;
            }
        }

        $record->lineas()->whereNotIn('id', $keptIds)->delete();

        $this->showModal = false;
        session()->flash('status', 'Guardado correctamente.');
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->form = [];
        $this->lineas = [];
        $this->resetValidation();
    }

    /**
     * Único estatus alcanzable desde esta pantalla: solicitada -> cancelada.
     * Los demás (parcialmente_recibida/recibida/facturada) los escribirán
     * las futuras etapas de Recepción y Facturación — sin UI aquí.
     */
    public function cancelarSolicitud(int $id): void
    {
        $record = SolicitudProveedor::findOrFail($id);

        if ($record->estatus !== SolicitudProveedor::ESTATUS_SOLICITADA) {
            return;
        }

        $record->update(['estatus' => SolicitudProveedor::ESTATUS_CANCELADA]);
        session()->flash('status', 'Solicitud cancelada.');
    }

    public function render()
    {
        $records = SolicitudProveedor::query()
            ->with(['vendor', 'ticket', 'sic'])
            ->withCount('lineas')
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('estatus', $this->estatusFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('folio', 'like', "%{$this->search}%")
                        ->orWhereHas('vendor', fn ($q) => $q->where('nombre_comercial', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('fecha_solicitud')
            ->paginate(10);

        return view('gestionti::livewire.compras.solicitudes-proveedor', [
            'records' => $records,
            'vendorOptions' => Proveedor::where('activo', true)->orderBy('nombre_comercial')->get(),
            'ticketOptions' => Ticket::orderByDesc('fecha')->get(),
            'sicOptions' => SolicitudSicBorrador::with('ticket')->orderByDesc('fecha_solicitud')->get(),
            'articuloOptions' => ArticuloSolicitud::where('activo', true)->orderBy('descripcion')->get(),
            // Artículos de categoría "laptops_desktops" de proyectos ya
            // autorizados y que ningún otra Solicitud a Proveedor haya
            // recogido todavía — ver docs/gestionti-progreso.md, decisión de
            // diseño "disparar la generación de Solicitud a Proveedor".
            'proyectoArticuloOptions' => ProyectoPresupuestoArticulo::where('categoria', 'laptops_desktops')
                ->whereHas('proyecto', fn ($q) => $q->where('estatus', ProyectoPresupuesto::ESTATUS_AUTORIZADO))
                ->whereDoesntHave('solicitudProveedor')
                ->with('proyecto')
                ->get(),
        ]);
    }
}
