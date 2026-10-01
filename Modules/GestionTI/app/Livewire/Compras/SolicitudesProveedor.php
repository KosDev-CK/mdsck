<?php

namespace Modules\GestionTI\Livewire\Compras;

use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Mail\SolicitudProveedorMail;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\CategoriaArticulo;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;

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
     * (camino "SIC local") — cada cambio (marcar/desmarcar) sincroniza
     * `$lineas` vía `syncLineasFromSeleccionados()`, llamado desde el
     * catch-all `updated()`.
     *
     * @var array<int, int>
     */
    public array $sicIdsSeleccionados = [];

    /**
     * IDs de `EbsRequisition` marcados en el mismo picker (camino "EBS
     * directo, sin SIC local" — ver `EbsRequisition::scopeElegibleDirectoSinSic()`)
     * — mismo mecanismo de sincronización que `$sicIdsSeleccionados`. Nunca
     * se solapan: una opción del picker es una SIC local O una requisición
     * de EBS, nunca ambas.
     *
     * @var array<int, int>
     */
    public array $ebsIdsSeleccionados = [];

    /**
     * `ebs_item_description` es puramente informativo (no se guarda en la
     * BD, no es un campo de `SolicitudProveedorLinea`) — se cachea aquí al
     * construir el array en `edit()`/`syncLineasFromSeleccionados()` para
     * no repetir la consulta en cada render: la descripción original de EBS
     * de la línea, mostrada de solo lectura junto al artículo de catálogo
     * cuando la línea (por SIC con `ebs_requisition_id`, o por EBS directo)
     * viene de una requisición de EBS, para detectar si el mapeo automático
     * (`EbsArticulo`) eligió mal.
     *
     * @var array<int, array{id: ?int, sic_id: ?int, folio_sic_manual: ?string, ebs_requisition_id: ?int, articulo_id: ?int, descripcion_libre: ?string, cantidad_solicitada: int, precio_unitario_cotizado: ?float, es_activo_inventariable: bool, observaciones_especificaciones: ?string, ebs_item_description: ?string}>
     */
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
            'lineas.*.ebs_requisition_id' => 'nullable|exists:ebs_requisitions,id',
            'lineas.*.articulo_id' => 'nullable|exists:articulos_solicitud,id',
            'lineas.*.descripcion_libre' => 'nullable|string|max:255',
            'lineas.*.cantidad_solicitada' => 'required|integer|min:1',
            'lineas.*.precio_unitario_cotizado' => 'nullable|numeric|min:0',
            'lineas.*.es_activo_inventariable' => 'boolean',
            'lineas.*.observaciones_especificaciones' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Parsea una lista de ids separados por coma de un query param — mismo
     * saneo para `crear_desde_sics` y `crear_desde_ebs`.
     *
     * @return array<int, int>
     */
    private function parseIdsQueryParam(string $name): array
    {
        $raw = (string) request()->query($name, '');

        if ($raw === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Creación directa desde "SIC en EBS" (punto 6/7 del rediseño) — cuando
     * llega `?crear_desde_sics=1,2,3` y/o `?crear_desde_ebs=4,5` en la URL
     * (pueden venir ambos a la vez si se seleccionó una mezcla de orígenes),
     * abre el formulario de creación ya precargado con esos ids marcados y
     * sus líneas generadas, reutilizando el 100% de la lógica ya construida
     * (`create()` + `syncLineasFromSeleccionados()`), sin duplicar nada. Si
     * los 2 query params vienen vacíos/ausentes, el `mount()` no hace nada
     * distinto de siempre (la pantalla abre en su estado normal de listado).
     */
    public function mount(): void
    {
        $sicIds = $this->parseIdsQueryParam('crear_desde_sics');
        $ebsIds = $this->parseIdsQueryParam('crear_desde_ebs');

        if (empty($sicIds) && empty($ebsIds)) {
            return;
        }

        $this->create();
        $this->sicIdsSeleccionados = $sicIds;
        $this->ebsIdsSeleccionados = $ebsIds;
        $this->syncLineasFromSeleccionados();
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
     * Folio sugerido (SP-YYMMDD-###, año a 2 dígitos, secuencial por día)
     * precargado en el formulario de creación pero completamente editable —
     * el usuario puede capturar cualquier otro folio manualmente antes de
     * guardar. La regla `unique` de `rules()` valida el valor final, no la
     * sugerencia.
     */
    private function suggestFolio(): string
    {
        $prefix = 'SP-'.now()->format('ymd').'-';
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
            'ebs_requisition_id' => null,
            'articulo_id' => null,
            'descripcion_libre' => null,
            'cantidad_solicitada' => 1,
            'precio_unitario_cotizado' => null,
            'es_activo_inventariable' => false,
            'observaciones_especificaciones' => null,
            'ebs_item_description' => null,
        ];
    }

    /**
     * Si la línea removida venía de una SIC o de una requisición de EBS
     * directa marcada en el picker, la desmarca también
     * (`sicIdsSeleccionados`/`ebsIdsSeleccionados`) — evita que el checkbox
     * siga viéndose marcado después de quitar la línea que generó.
     */
    public function removeLinea(int $index): void
    {
        $sicId = $this->lineas[$index]['sic_id'] ?? null;
        $ebsRequisitionId = $this->lineas[$index]['ebs_requisition_id'] ?? null;

        if (! empty($sicId)) {
            $this->sicIdsSeleccionados = array_values(array_diff($this->sicIdsSeleccionados, [$sicId]));
        }

        if (! empty($ebsRequisitionId)) {
            $this->ebsIdsSeleccionados = array_values(array_diff($this->ebsIdsSeleccionados, [$ebsRequisitionId]));
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
        $this->ebsIdsSeleccionados = [];
        $this->lineas = [];
        $this->addLinea();
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Bloqueado (sin efecto) para un usuario normal cuando la solicitud ya
     * se envió al proveedor por correo (`enviada_at` no nulo) — ver
     * `puedeEditar()`. El botón "Editar" ya se oculta en el blade para ese
     * caso, esto es la segunda capa de defensa del lado del servidor.
     */
    public function edit(int $id): void
    {
        $record = SolicitudProveedor::with('lineas')->findOrFail($id);

        if (! $this->puedeEditar($record)) {
            return;
        }

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
            'ebs_requisition_id' => $linea->ebs_requisition_id,
            'articulo_id' => $linea->articulo_id,
            'descripcion_libre' => $linea->descripcion_libre,
            'cantidad_solicitada' => $linea->cantidad_solicitada,
            'precio_unitario_cotizado' => $linea->precio_unitario_cotizado,
            'es_activo_inventariable' => $linea->es_activo_inventariable,
            'observaciones_especificaciones' => $linea->observaciones_especificaciones,
            'ebs_item_description' => $this->ebsItemDescriptionFor($linea->sic_id, $linea->ebs_requisition_id),
        ])->all();
        $this->origen = $record->proyecto_presupuesto_articulo_id ? 'proyecto' : 'sic';
        $this->sicIdsSeleccionados = collect($this->lineas)->pluck('sic_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $this->ebsIdsSeleccionados = collect($this->lineas)->pluck('ebs_requisition_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Descripción original de EBS de referencia (punto 8 del rediseño) —
     * para los 2 orígenes posibles de una línea con rastro de EBS: una SIC
     * con `ebs_requisition_id` (camino "SIC local"), o `ebs_requisition_id`
     * directo en la propia línea (camino "EBS directo, sin SIC local"). Se
     * muestra de solo lectura junto al select de "Artículo del catálogo"
     * para detectar a simple vista si el mapeo automático (`EbsArticulo`)
     * eligió mal el artículo estándar.
     */
    private function ebsItemDescriptionFor(?int $sicId, ?int $ebsRequisitionId = null): ?string
    {
        if ($ebsRequisitionId) {
            $ebsRequisicion = EbsRequisition::with('lines')->find($ebsRequisitionId);

            return $ebsRequisicion?->lines->sortBy('line_number')->first()?->item_description;
        }

        if (! $sicId) {
            return null;
        }

        $sic = SolicitudSicBorrador::with('ebsRequisition.lines')->find($sicId);

        if (! $sic || ! $sic->ebs_requisition_id) {
            return null;
        }

        return $sic->ebsRequisition?->lines->sortBy('line_number')->first()?->item_description;
    }

    /**
     * Catch-all de Livewire — reacciona a cambios de propiedades de primer
     * nivel. `sicIdsSeleccionados`/`ebsIdsSeleccionados` son las únicas que
     * necesitan lógica propia aquí: cada marca/desmarca del picker
     * resincroniza `$lineas`.
     *
     * Bug real encontrado en verificación visual (2026-09-28): el checkbox
     * del picker (`wire:model.live="sicIdsSeleccionados"` repetido) llega
     * aquí con `$name` como `"sicIdsSeleccionados.N"` (path con el índice
     * tocado), no como el nombre plano de la propiedad — el `===` estricto
     * de abajo nunca coincidía, así que marcar una SIC nunca generaba su
     * línea en un navegador real (los tests con `->set('sicIdsSeleccionados',
     * [...])` sí disparaban esto porque `set()` reemplaza la propiedad
     * completa, sin sufijo de índice — por eso la suite pasaba en verde pero
     * la pantalla real no funcionaba). Se agregan además los hooks
     * específicos `updatedSicIdsSeleccionados()`/`updatedEbsIdsSeleccionados()`
     * (más abajo), que Livewire sí resuelve de forma confiable sin importar
     * si el path viene con índice o no — son la fuente de verdad real; este
     * catch-all se deja también, ya cubriendo ambas formas del path, por si
     * algún otro camino lo dispara distinto.
     */
    public function updated($name, $value): void
    {
        if ($name === 'sicIdsSeleccionados' || str_starts_with($name, 'sicIdsSeleccionados.')
            || $name === 'ebsIdsSeleccionados' || str_starts_with($name, 'ebsIdsSeleccionados.')) {
            $this->syncLineasFromSeleccionados();
        }
    }

    public function updatedSicIdsSeleccionados(): void
    {
        $this->syncLineasFromSeleccionados();
    }

    public function updatedEbsIdsSeleccionados(): void
    {
        $this->syncLineasFromSeleccionados();
    }

    /**
     * Cambiar de origen es puramente de UI (qué sección se muestra), pero
     * para no dejar datos "fantasma" inconsistentes con lo que el usuario ve:
     * pasar a "proyecto" limpia el picker de SICs/EBS y las líneas que
     * hubiera generado; pasar a "sic" limpia el artículo de proyecto
     * elegido. Decisión tomada sobre la marcha, no especificada 100% en el
     * plan.
     */
    public function updatedOrigen(string $value): void
    {
        if ($value === 'proyecto') {
            $this->sicIdsSeleccionados = [];
            $this->ebsIdsSeleccionados = [];
            $this->syncLineasFromSeleccionados();
        } else {
            $this->form['proyecto_presupuesto_articulo_id'] = null;
        }
    }

    /**
     * Reconstruye las líneas derivadas de SICs/EBS directo a partir de
     * `$sicIdsSeleccionados`/`$ebsIdsSeleccionados`: agrega una línea nueva
     * por cada id recién marcado (heredando `articulo_id` y
     * `es_activo_inventariable` del Artículo — siempre resuelto, ver
     * `sicPickerOptions()`/`EbsRequisition::articuloMapeadoDeCompra()`) y
     * quita las líneas de ids que ya no están marcados. Las líneas manuales
     * (sin `sic_id` ni `ebs_requisition_id`) nunca se tocan aquí.
     */
    private function syncLineasFromSeleccionados(): void
    {
        $sicSeleccionados = array_map('intval', $this->sicIdsSeleccionados);
        $ebsSeleccionados = array_map('intval', $this->ebsIdsSeleccionados);

        $this->lineas = array_values(array_filter(
            $this->lineas,
            fn ($linea) => (empty($linea['sic_id']) || in_array((int) $linea['sic_id'], $sicSeleccionados, true))
                && (empty($linea['ebs_requisition_id']) || in_array((int) $linea['ebs_requisition_id'], $ebsSeleccionados, true))
        ));

        $sicYaPresentes = collect($this->lineas)->pluck('sic_id')->filter()->map(fn ($id) => (int) $id)->all();
        $ebsYaPresentes = collect($this->lineas)->pluck('ebs_requisition_id')->filter()->map(fn ($id) => (int) $id)->all();

        foreach ($sicSeleccionados as $sicId) {
            if (in_array($sicId, $sicYaPresentes, true)) {
                continue;
            }

            $sic = SolicitudSicBorrador::with(['articulo', 'ebsRequisition.lines'])->find($sicId);

            if (! $sic) {
                continue;
            }

            $this->lineas[] = [
                'id' => null,
                'sic_id' => $sic->id,
                'folio_sic_manual' => null,
                'ebs_requisition_id' => null,
                'articulo_id' => $sic->articulo_id,
                'descripcion_libre' => null,
                'cantidad_solicitada' => 1,
                'precio_unitario_cotizado' => null,
                'es_activo_inventariable' => $sic->articulo?->es_inventariable ?? true,
                'observaciones_especificaciones' => null,
                'ebs_item_description' => $sic->ebs_requisition_id
                    ? $sic->ebsRequisition?->lines->sortBy('line_number')->first()?->item_description
                    : null,
            ];
        }

        foreach ($ebsSeleccionados as $ebsRequisitionId) {
            if (in_array($ebsRequisitionId, $ebsYaPresentes, true)) {
                continue;
            }

            $ebsRequisicion = EbsRequisition::with('lines')->find($ebsRequisitionId);

            if (! $ebsRequisicion) {
                continue;
            }

            $articulo = $ebsRequisicion->articuloMapeadoDeCompra();

            $this->lineas[] = [
                'id' => null,
                'sic_id' => null,
                'folio_sic_manual' => null,
                'ebs_requisition_id' => $ebsRequisicion->id,
                'articulo_id' => $articulo?->id,
                'descripcion_libre' => null,
                'cantidad_solicitada' => 1,
                'precio_unitario_cotizado' => null,
                'es_activo_inventariable' => $articulo?->es_inventariable ?? true,
                'observaciones_especificaciones' => null,
                'ebs_item_description' => $ebsRequisicion->lines->sortBy('line_number')->first()?->item_description,
            ];
        }

        // `create()` siempre arranca con 1 línea manual en blanco (ver
        // `addLinea()`), pensada para cuando el usuario captura a mano sin
        // usar el picker. En cuanto hay al menos 1 línea real derivada de
        // una SIC o de EBS directo, esa línea en blanco (nunca tocada por el
        // usuario) deja de tener sentido y, sin quitarla, `validateLineas()`
        // la rechazaría igual al guardar ("elige un artículo o descripción")
        // — se limpia aquí para que marcar una opción del picker sea
        // suficiente por sí solo, sin obligar al usuario a borrar
        // manualmente el renglón vacío que él nunca pidió.
        if (collect($this->lineas)->contains(fn ($linea) => ! empty($linea['sic_id']) || ! empty($linea['ebs_requisition_id']))) {
            $this->lineas = array_values(array_filter($this->lineas, fn ($linea) => ! $this->esLineaEnBlancoSinTocar($linea)));
        }
    }

    /**
     * Línea "nueva" que nadie ha tocado todavía — mismos valores que
     * `addLinea()` produce. Usada por `syncLineasFromSeleccionados()` para
     * no dejar un renglón fantasma inválido cuando el usuario arma la
     * solicitud completa desde el picker de SICs/EBS.
     */
    private function esLineaEnBlancoSinTocar(array $linea): bool
    {
        return empty($linea['id'])
            && empty($linea['sic_id'])
            && empty($linea['folio_sic_manual'])
            && empty($linea['ebs_requisition_id'])
            && empty($linea['articulo_id'])
            && trim((string) ($linea['descripcion_libre'] ?? '')) === ''
            && empty($linea['precio_unitario_cotizado'])
            && empty($linea['es_activo_inventariable'])
            && trim((string) ($linea['observaciones_especificaciones'] ?? '')) === '';
    }

    /**
     * "El origen de una Solicitud a Proveedor es una o más SICs/requisiciones
     * de EBS O un artículo de proyecto, no ambos" — regla de negocio del
     * spec, generalizada de "una SIC" a "de 1 a N SICs/EBS": ahora se
     * detecta mirando si ALGUNA línea trae `sic_id` o `ebs_requisition_id`
     * (antes miraba el campo de cabecera `form.sic_id`, ya eliminado). El
     * error se agrega sobre `origen` (el selector visual) y sobre el campo
     * de proyecto para que el mensaje aparezca sin importar cuál mire el
     * usuario primero.
     */
    private function validateOrigenUnico(): void
    {
        $tieneSic = collect($this->lineas)->contains(fn ($linea) => ! empty($linea['sic_id']) || ! empty($linea['ebs_requisition_id']));

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

    /**
     * Bloqueo de edición tras el envío (punto 12 del rediseño): una vez que
     * `enviada_at` no es `null`, solo un Administrador puede seguir
     * modificando la solicitud (agregar/quitar SICs, cambiar artículos,
     * cantidades, etc.) — un usuario normal ya no puede, aunque siga
     * pudiendo reenviar el correo o generar el PDF (acciones que no
     * modifican el contenido). Pública porque también la consulta el blade
     * para ocultar los botones "Editar"/"Cancelar".
     */
    public function puedeEditar(SolicitudProveedor $record): bool
    {
        return $record->enviada_at === null || auth()->user()->hasRole('Administrador');
    }

    public function save(): void
    {
        if ($this->editingId) {
            $existente = SolicitudProveedor::findOrFail($this->editingId);

            if (! $this->puedeEditar($existente)) {
                return;
            }
        }

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
                'creado_por_user_id' => auth()->id(),
            ]));
        }

        $keptIds = [];
        foreach ($this->lineas as $linea) {
            $attributes = [
                'sic_id' => $linea['sic_id'] ?: null,
                'folio_sic_manual' => ($linea['folio_sic_manual'] ?? '') !== '' ? $linea['folio_sic_manual'] : null,
                'ebs_requisition_id' => $linea['ebs_requisition_id'] ?: null,
                'articulo_id' => $linea['articulo_id'] ?: null,
                'descripcion_libre' => $linea['descripcion_libre'] ?: null,
                'cantidad_solicitada' => $linea['cantidad_solicitada'],
                'precio_unitario_cotizado' => $linea['precio_unitario_cotizado'] !== '' ? $linea['precio_unitario_cotizado'] : null,
                'es_activo_inventariable' => (bool) ($linea['es_activo_inventariable'] ?? false),
                'observaciones_especificaciones' => ($linea['observaciones_especificaciones'] ?? '') !== '' ? $linea['observaciones_especificaciones'] : null,
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
        $this->ebsIdsSeleccionados = [];
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

        if (! $this->puedeEditar($record)) {
            return;
        }

        $record->update(['estatus' => SolicitudProveedor::ESTATUS_CANCELADA]);
        session()->flash('status', 'Solicitud cancelada.');
    }

    /**
     * Envío/reenvío por correo al proveedor (punto 11 del rediseño) — el
     * momento real en que una Solicitud a Proveedor "se comparte con el
     * proveedor" es este, no un paso de "validar" aparte. Disponible para
     * cualquier usuario con acceso a la pantalla (reenviar no modifica el
     * contenido, así que no se restringe a Administrador, a diferencia de
     * `edit()`/`save()`/`cancelarSolicitud()`). `enviada_at` solo se escribe
     * la primera vez (es el campo que determina el bloqueo de edición);
     * `ultimo_envio_at` se actualiza siempre, en cada envío y reenvío.
     */
    public function enviarAProveedor(int $id): void
    {
        $record = SolicitudProveedor::with(['vendor', 'ticket', 'creadoPor', 'lineas.articulo', 'lineas.sic', 'lineas.ebsRequisition'])->findOrFail($id);

        if (empty($record->vendor?->contacto_correo)) {
            session()->flash('error', 'El proveedor no tiene correo de contacto capturado — agrégalo desde "Catálogos de Compras" antes de enviar.');

            return;
        }

        Mail::to($record->vendor->contacto_correo)->send(new SolicitudProveedorMail($record));

        if (! $record->enviada_at) {
            $record->enviada_at = now();
        }
        $record->ultimo_envio_at = now();
        $record->save();

        session()->flash('status', 'Correo enviado al proveedor.');
    }

    /**
     * Etiqueta legible para el picker de SICs — pública porque se invoca
     * desde la vista Blade.
     */
    public function sicPickerLabel(SolicitudSicBorrador $sic): string
    {
        $folio = $sic->folio_sic ?: "SIC #{$sic->id}";
        $categoria = $sic->articulo?->categoria?->nombre;

        return "{$folio} — {$sic->empleado?->nombre} — {$sic->articulo?->descripcion} ({$categoria})";
    }

    /**
     * Etiqueta legible para el picker de requisiciones de EBS sin SIC local
     * (camino directo) — mismo espíritu que `sicPickerLabel()`, pero sin
     * solicitante/folio de SIC (no existen para este camino): código de la
     * requisición + artículo mapeado + categoría.
     */
    public function ebsPickerLabel(EbsRequisition $ebsRequisicion): string
    {
        $articulo = $ebsRequisicion->articuloMapeadoDeCompra();
        $categoria = $articulo?->categoria?->nombre;

        return "EBS {$ebsRequisicion->code} — {$articulo?->descripcion} ({$categoria})";
    }

    /**
     * "SICs autorizadas y disponibles" — mismo criterio `whereDoesntHave(...)`
     * ya usado en `Asignaciones::render()`/`Stock::sicReservationOptions()`
     * para "SIC autorizada y aún no consumida", extendido con el filtro de
     * categoría-va-a-compra (`CategoriaArticulo::where('es_compra', true)`,
     * pestaña "Categoría" de Catálogos de Compras). Una SIC
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
        return SolicitudSicBorrador::autorizadaYSeleccionable($this->editingId)
            ->with(['empleado', 'ticket', 'articulo.categoria'])
            ->orderByDesc('fecha_solicitud')
            ->get();
    }

    /**
     * Segunda fuente del mismo picker (unión con `sicPickerOptions()`,
     * punto 6 del rediseño) — requisiciones de EBS que NUNCA tuvieron SIC
     * local, aprobadas, con su artículo mapeado de categoría "va a Compra" y
     * sin asignar todavía por el camino directo. Mismo criterio de
     * elegibilidad que "SIC en EBS" (`EbsRequisition::scopeElegibleDirectoSinSic()`).
     */
    private function ebsPickerOptions()
    {
        return EbsRequisition::elegibleDirectoSinSic($this->editingId)
            ->with('lines')
            ->orderByDesc('fecha_creacion')
            ->get()
            ->filter(fn ($ebsRequisicion) => $ebsRequisicion->articuloMapeadoDeCompra() !== null)
            ->values();
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
            'ebsPickerOptions' => $this->ebsPickerOptions(),
            'articuloOptions' => ArticuloSolicitud::where('activo', true)->orderBy('descripcion')->get(),
            // Artículos de categoría "laptops_desktops" de proyectos ya
            // autorizados y que ninguna otra Solicitud a Proveedor haya
            // recogido todavía — ver docs/gestionti-progreso.md, decisión de
            // diseño "disparar la generación de Solicitud a Proveedor". El
            // slug literal 'laptops_desktops' es el mismo que se protege en
            // `Modules\GestionTI\Models\CategoriaArticulo` — nunca se toca
            // de valor, solo cambió de columna (antes `categoria` string,
            // ahora `categoria_id` vía el catálogo real).
            'proyectoArticuloOptions' => ProyectoPresupuestoArticulo::where(
                'categoria_id',
                CategoriaArticulo::where('slug', 'laptops_desktops')->value('id')
            )
                ->whereHas('proyecto', fn ($q) => $q->where('estatus', ProyectoPresupuesto::ESTATUS_AUTORIZADO))
                ->whereDoesntHave('solicitudProveedor')
                ->with('proyecto')
                ->get(),
        ]);
    }
}
