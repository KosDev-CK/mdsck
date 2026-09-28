<?php

namespace Modules\GestionTI\Livewire\Compras;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\ConfiguracionCategorias;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Support\Catalogos\CategoriaArticulo;

/**
 * "Solicitud a Proveedores" — origen "de 1 a N SICs" (`lineas.*.sic_id`,
 * ver `SolicitudProveedorLinea`) O un artículo de Proyecto de Presupuesto
 * (`form.proyecto_presupuesto_articulo_id`), nunca ambos — regla de negocio
 * validada en `validateOrigenUnico()`. Ver docs/gestionti-progreso.md,
 * entrada del rediseño "Solicitud a Proveedores: selección de 1 a N SICs
 * autorizadas" para el diseño completo.
 *
 * Cada SIC seleccionada en el picker (`$sicIdsSeleccionados`, checkboxes
 * poblados por `sicPickerOptions()`) se convierte automáticamente en una
 * línea nueva — no hace falta una tabla pivote aparte, "de 1 a N SICs" surge
 * de "N líneas, cada una con su propia SIC opcional". También es posible
 * capturar una línea manual con `folio_sic_manual` (texto libre) cuando no
 * existe todavía el registro real de la SIC.
 */
#[Layout('layouts.app')]
class SolicitudesProveedor extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [];

    /**
     * 'sic' (una o más SICs, default) | 'proyecto' (un artículo de
     * Proyecto de Presupuesto) — puramente un organizador de la UI, no una
     * columna real: la validación de exclusividad real
     * (`validateOrigenUnico()`) mira los datos (`lineas.*.sic_id` vs.
     * `form.proyecto_presupuesto_articulo_id`), no este campo.
     */
    public string $origen = 'sic';

    /**
     * IDs de SIC marcados en el picker de "SICs autorizadas y disponibles"
     * — cada cambio (marcar/desmarcar) sincroniza `$lineas` vía
     * `syncLineasFromSicSeleccionadas()`, llamado desde el catch-all
     * `updated()`.
     *
     * @var array<int, int>
     */
    public array $sicIdsSeleccionados = [];

    /** @var array<int, array{id: ?int, sic_id: ?int, folio_sic_manual: ?string, articulo_id: ?int, descripcion_libre: ?string, cantidad_solicitada: int, precio_unitario_cotizado: ?float, es_activo_inventariable: bool}> */
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
            'form.proyecto_presupuesto_articulo_id' => 'nullable|exists:proyecto_presupuesto_articulos,id',
            'form.tipo_solicitud' => ['required', Rule::in(SolicitudProveedor::TIPOS)],
            'lineas' => 'required|array|min:1',
            'lineas.*.sic_id' => 'nullable|exists:solicitudes_sic_borrador,id',
            'lineas.*.folio_sic_manual' => 'nullable|string|max:100',
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
     * Los selects opcionales de Ticket/Artículo de Proyecto mandan '' para
     * "Sin asignar" — normalizar a null antes de validar/guardar (mismo
     * patrón ya documentado en Compras.php/SolicitudesSic.php de fases
     * previas). `sic_id` ya no vive en la cabecera, no aplica aquí.
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach (['ticket_id', 'proyecto_presupuesto_articulo_id'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }
    }

    public function addLinea(): void
    {
        $this->lineas[] = [
            'id' => null,
            'sic_id' => null,
            'folio_sic_manual' => null,
            'articulo_id' => null,
            'descripcion_libre' => null,
            'cantidad_solicitada' => 1,
            'precio_unitario_cotizado' => null,
            'es_activo_inventariable' => false,
        ];
    }

    /**
     * Si la línea removida venía de una SIC marcada en el picker, la
     * desmarca también (`sicIdsSeleccionados`) — evita que el checkbox
     * siga viéndose marcado después de quitar la línea que generó.
     */
    public function removeLinea(int $index): void
    {
        $sicId = $this->lineas[$index]['sic_id'] ?? null;

        if (! empty($sicId)) {
            $this->sicIdsSeleccionados = array_values(array_diff($this->sicIdsSeleccionados, [$sicId]));
        }

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
            'proyecto_presupuesto_articulo_id' => null,
            'tipo_solicitud' => 'regular',
        ];
        $this->origen = 'sic';
        $this->sicIdsSeleccionados = [];
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
            'proyecto_presupuesto_articulo_id' => $record->proyecto_presupuesto_articulo_id,
            'tipo_solicitud' => $record->tipo_solicitud,
        ];
        $this->lineas = $record->lineas->map(fn ($linea) => [
            'id' => $linea->id,
            'sic_id' => $linea->sic_id,
            'folio_sic_manual' => $linea->folio_sic_manual,
            'articulo_id' => $linea->articulo_id,
            'descripcion_libre' => $linea->descripcion_libre,
            'cantidad_solicitada' => $linea->cantidad_solicitada,
            'precio_unitario_cotizado' => $linea->precio_unitario_cotizado,
            'es_activo_inventariable' => $linea->es_activo_inventariable,
        ])->all();
        $this->origen = $record->proyecto_presupuesto_articulo_id ? 'proyecto' : 'sic';
        $this->sicIdsSeleccionados = collect($this->lineas)->pluck('sic_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Catch-all de Livewire — reacciona a cambios de propiedades de primer
     * nivel. `sicIdsSeleccionados` es la única que necesita lógica propia
     * aquí: cada marca/desmarca del picker resincroniza `$lineas`.
     *
     * Bug real encontrado en verificación visual (2026-09-28): el checkbox
     * del picker (`wire:model.live="sicIdsSeleccionados"` repetido) llega
     * aquí con `$name` como `"sicIdsSeleccionados.N"` (path con el índice
     * tocado), no como el nombre plano de la propiedad — el `===` estricto
     * de abajo nunca coincidía, así que marcar una SIC nunca generaba su
     * línea en un navegador real (los tests con `->set('sicIdsSeleccionados',
     * [...])` sí disparaban esto porque `set()` reemplaza la propiedad
     * completa, sin sufijo de índice — por eso la suite pasaba en verde pero
     * la pantalla real no funcionaba). Se agrega además el hook específico
     * `updatedSicIdsSeleccionados()` (más abajo), que Livewire sí resuelve de
     * forma confiable sin importar si el path viene con índice o no — es la
     * fuente de verdad real; este catch-all se deja también, ya cubriendo
     * ambas formas del path, por si algún otro camino lo dispara distinto.
     */
    public function updated($name, $value): void
    {
        if ($name === 'sicIdsSeleccionados' || str_starts_with($name, 'sicIdsSeleccionados.')) {
            $this->syncLineasFromSicSeleccionadas();
        }
    }

    public function updatedSicIdsSeleccionados(): void
    {
        $this->syncLineasFromSicSeleccionadas();
    }

    /**
     * Cambiar de origen es puramente de UI (qué sección se muestra), pero
     * para no dejar datos "fantasma" inconsistentes con lo que el usuario ve:
     * pasar a "proyecto" limpia el picker de SICs y las líneas que hubiera
     * generado; pasar a "sic" limpia el artículo de proyecto elegido.
     * Decisión tomada sobre la marcha, no especificada 100% en el plan.
     */
    public function updatedOrigen(string $value): void
    {
        if ($value === 'proyecto') {
            $this->sicIdsSeleccionados = [];
            $this->syncLineasFromSicSeleccionadas();
        } else {
            $this->form['proyecto_presupuesto_articulo_id'] = null;
        }
    }

    /**
     * Reconstruye las líneas derivadas de SICs a partir de
     * `$sicIdsSeleccionados`: agrega una línea nueva por cada SIC recién
     * marcada (heredando `articulo_id` — siempre presente, ver
     * `sicPickerOptions()` — y `es_activo_inventariable` del Artículo) y
     * quita las líneas de SICs que ya no están marcadas. Las líneas
     * manuales (sin `sic_id`) nunca se tocan aquí.
     */
    private function syncLineasFromSicSeleccionadas(): void
    {
        $seleccionados = array_map('intval', $this->sicIdsSeleccionados);

        $this->lineas = array_values(array_filter(
            $this->lineas,
            fn ($linea) => empty($linea['sic_id']) || in_array((int) $linea['sic_id'], $seleccionados, true)
        ));

        $yaPresentes = collect($this->lineas)->pluck('sic_id')->filter()->map(fn ($id) => (int) $id)->all();

        foreach ($seleccionados as $sicId) {
            if (in_array($sicId, $yaPresentes, true)) {
                continue;
            }

            $sic = SolicitudSicBorrador::with('articulo')->find($sicId);

            if (! $sic) {
                continue;
            }

            $this->lineas[] = [
                'id' => null,
                'sic_id' => $sic->id,
                'folio_sic_manual' => null,
                'articulo_id' => $sic->articulo_id,
                'descripcion_libre' => null,
                'cantidad_solicitada' => 1,
                'precio_unitario_cotizado' => null,
                'es_activo_inventariable' => $sic->articulo?->es_inventariable ?? true,
            ];
        }

        // `create()` siempre arranca con 1 línea manual en blanco (ver
        // `addLinea()`), pensada para cuando el usuario captura a mano sin
        // usar el picker. En cuanto hay al menos 1 línea real derivada de
        // una SIC, esa línea en blanco (nunca tocada por el usuario) deja
        // de tener sentido y, sin quitarla, `validateLineas()` la rechazaría
        // igual al guardar ("elige un artículo o descripción") — se limpia
        // aquí para que marcar una SIC sea suficiente por sí solo, sin
        // obligar al usuario a borrar manualmente el renglón vacío que él
        // nunca pidió.
        if (collect($this->lineas)->contains(fn ($linea) => ! empty($linea['sic_id']))) {
            $this->lineas = array_values(array_filter($this->lineas, fn ($linea) => ! $this->esLineaEnBlancoSinTocar($linea)));
        }
    }

    /**
     * Línea "nueva" que nadie ha tocado todavía — mismos valores que
     * `addLinea()` produce. Usada por `syncLineasFromSicSeleccionadas()`
     * para no dejar un renglón fantasma inválido cuando el usuario arma la
     * solicitud completa desde el picker de SICs.
     */
    private function esLineaEnBlancoSinTocar(array $linea): bool
    {
        return empty($linea['id'])
            && empty($linea['sic_id'])
            && empty($linea['folio_sic_manual'])
            && empty($linea['articulo_id'])
            && trim((string) ($linea['descripcion_libre'] ?? '')) === ''
            && empty($linea['precio_unitario_cotizado'])
            && empty($linea['es_activo_inventariable']);
    }

    /**
     * "El origen de una Solicitud a Proveedor es una o más SICs O un
     * artículo de proyecto, no ambos" — regla de negocio del spec,
     * generalizada de "una SIC" a "de 1 a N SICs": ahora se detecta
     * mirando si ALGUNA línea trae `sic_id` (antes miraba el campo de
     * cabecera `form.sic_id`, ya eliminado). El error se agrega sobre
     * `origen` (el selector visual) y sobre el campo de proyecto para que
     * el mensaje aparezca sin importar cuál mire el usuario primero.
     */
    private function validateOrigenUnico(): void
    {
        $tieneSic = collect($this->lineas)->contains(fn ($linea) => ! empty($linea['sic_id']));

        if ($tieneSic && ! empty($this->form['proyecto_presupuesto_articulo_id'] ?? null)) {
            $mensaje = 'El origen debe ser una o más SICs o un artículo de proyecto, no ambos.';
            $this->addError('origen', $mensaje);
            $this->addError('form.proyecto_presupuesto_articulo_id', $mensaje);
        }
    }

    /**
     * Cada línea necesita exactamente uno de articulo_id/descripcion_libre
     * (no ambos, no ninguno) — regla dependiente entre 2 campos del mismo
     * renglón que una regla wildcard simple no puede expresar, mismo patrón
     * que `validateSubFieldOptions()` en Modules\FormBuilder\Livewire\Forms\Builder.
     * Las líneas generadas desde el picker de SICs siempre traen
     * `articulo_id` (la SIC no aparece en el picker si no lo tiene, ver
     * `sicPickerOptions()`), así que pasan esta validación sin intervención
     * manual.
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
                'sic_id' => $linea['sic_id'] ?: null,
                'folio_sic_manual' => ($linea['folio_sic_manual'] ?? '') !== '' ? $linea['folio_sic_manual'] : null,
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
        $this->origen = 'sic';
        $this->sicIdsSeleccionados = [];
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

    /**
     * Etiqueta legible para el picker de SICs — pública porque se invoca
     * desde la vista Blade.
     */
    public function sicPickerLabel(SolicitudSicBorrador $sic): string
    {
        $folio = $sic->folio_sic ?: "SIC #{$sic->id}";
        $categoria = CategoriaArticulo::LABELS[$sic->articulo?->categoria] ?? $sic->articulo?->categoria;

        return "{$folio} — {$sic->empleado?->nombre} — {$sic->articulo?->descripcion} ({$categoria})";
    }

    /**
     * "SICs autorizadas y disponibles" — mismo criterio `whereDoesntHave(...)`
     * ya usado en `Asignaciones::render()`/`Stock::sicReservationOptions()`
     * para "SIC autorizada y aún no consumida", extendido con el filtro de
     * categoría-va-a-compra (`ConfiguracionCategorias::current()`). Una SIC
     * sin `articulo_id` todavía (típico de una recién sincronizada de EBS,
     * sin clasificar) o cuya categoría no esté marcada como "va a Compra"
     * no aparece — sigue disponible solo por captura manual (folio de
     * texto). En modo edición, las SICs ya recogidas por ESTA MISMA
     * solicitud se siguen mostrando (y marcadas, ver `edit()`) aunque ya
     * tengan una línea — de lo contrario desaparecerían del picker al
     * reabrir la solicitud para editarla.
     */
    private function sicPickerOptions()
    {
        $categoriasCompra = ConfiguracionCategorias::current()->categorias_compra ?? [];

        return SolicitudSicBorrador::where('estatus', SolicitudSicBorrador::ESTATUS_AUTORIZADA)
            ->whereHas('articulo', fn ($q) => $q->whereIn('categoria', $categoriasCompra))
            ->where(function ($q) {
                $q->whereDoesntHave('solicitudProveedorLineas')
                    ->when($this->editingId, fn ($q2) => $q2->orWhereHas(
                        'solicitudProveedorLineas',
                        fn ($q3) => $q3->where('solicitud_id', $this->editingId)
                    ));
            })
            ->with(['empleado', 'ticket', 'articulo'])
            ->orderByDesc('fecha_solicitud')
            ->get();
    }

    public function render()
    {
        $records = SolicitudProveedor::query()
            ->with(['vendor', 'ticket'])
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
            'sicPickerOptions' => $this->sicPickerOptions(),
            'articuloOptions' => ArticuloSolicitud::where('activo', true)->orderBy('descripcion')->get(),
            // Artículos de categoría "laptops_desktops" de proyectos ya
            // autorizados y que ninguna otra Solicitud a Proveedor haya
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
