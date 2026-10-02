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
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudProveedorLinea;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;

/**
 * "Solicitud a Proveedores" — origen "de 1 a N SICs" (`lineas.*.sic_id`,
 * ver `SolicitudProveedorLinea`) O un artículo de Proyecto de Presupuesto
 * (`form.proyecto_presupuesto_articulo_id`), nunca ambos — regla de negocio
 * validada en `validateOrigenUnico()`. Ver docs/gestionti-progreso.md,
 * entrada "Una sola tabla: elegir y editar SICs es la misma acción" para el
 * diseño completo de `$lineas` (reemplaza el picker separado + tabla de la
 * entrada anterior del mismo día).
 *
 * En origen "sic", `$lineas` es el POOL COMPLETO de SICs/EBS elegibles (ver
 * `sicPickerOptions()`/`ebsPickerOptions()`) — una fila por cada una, esté
 * marcada o no (`lineas.*.seleccionada`), más cualquier línea manual
 * agregada al final. No hay una lista de "opciones" separada: la misma
 * tabla "Líneas del pedido" sirve para elegir Y editar. Solo las filas
 * marcadas (o manuales) se persisten al guardar (`lineasAGuardar()`).
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
     * En origen "sic": una fila por cada SIC/EBS elegible (todas, marcadas
     * o no, ver `construirLineasDesdePool()`/`construirLineasParaEdicion()`)
     * más cualquier línea manual agregada al final. Una fila "de pool" trae
     * `sic_id` o `ebs_requisition_id`; una fila manual no trae ninguno de
     * los 2. `seleccionada` decide si una fila de pool se guarda
     * (`lineasAGuardar()`) — las manuales siempre se evalúan por contenido
     * (`esLineaEnBlancoSinTocar()`), ese campo no aplica para ellas.
     * `ebs_item_description`/`folio_sic_display`/`articulo_descripcion_preview`
     * son puramente informativos (no se guardan en la BD, no son campos de
     * `SolicitudProveedorLinea`): el primero muestra la descripción
     * original de EBS de solo lectura para detectar un mapeo automático
     * incorrecto; el segundo es la etiqueta de SIC/EBS a mostrar en la
     * columna "SIC"; el tercero es una vista previa liviana del artículo
     * mapeado para filas de pool aún no marcadas (sin controles editables
     * de por medio, para no renderizar selects de 500+ opciones de entrada
     * en 50+ filas).
     *
     * @var array<int, array{id: ?int, sic_id: ?int, folio_sic_manual: ?string, ebs_requisition_id: ?int, lugar_entrega_id: ?int, articulo_id: ?int, descripcion_libre: ?string, cantidad_solicitada: int, precio_unitario_cotizado: ?float, es_activo_inventariable: bool, observaciones_especificaciones: ?string, ebs_item_description: ?string, folio_sic_display: ?string, articulo_descripcion_preview: ?string, seleccionada: bool}>
     */
    public array $lineas = [];

    #[Url(as: 'search')]
    public string $search = '';

    public string $estatusFilter = '';

    public bool $showModal = false;

    /**
     * Modal de solo lectura "ver detalle de la SIC" al dar clic sobre el
     * número de SIC en la tabla de "Líneas del pedido" — mismo detalle que
     * ya existe en "SIC en EBS" (`MesaServicio\EbsRequisiciones::openDetalle()`),
     * reutilizado vía el partial compartido `partials.ebs-requisicion-detalle`.
     * Para una línea con requisición de EBS resuelta (directa, o heredada de
     * su SIC local vinculada) se abre ESE detalle; para una SIC puramente
     * local (sin EBS) se abre en su lugar `partials.sic-local-detalle`. Una
     * línea con solo `folio_sic_manual` (sin registro real) no tiene nada
     * que abrir — su celda se queda como el input editable de siempre.
     */
    public bool $showDetalleModal = false;

    public ?int $detalleEbsRequisitionId = null;

    public ?int $detalleSicLocalId = null;

    protected function rules(): array
    {
        return [
            'form.folio' => ['required', 'string', 'max:100', Rule::unique('solicitudes_proveedor', 'folio')->ignore($this->editingId)],
            'form.vendor_id' => 'required|exists:proveedores,id',
            'form.fecha_solicitud' => 'required|date',
            'form.ticket_id' => 'nullable|exists:tickets,id',
            'form.proyecto_presupuesto_articulo_id' => 'nullable|exists:proyecto_presupuesto_articulos,id',
            'form.tipo_solicitud' => ['required', Rule::in(SolicitudProveedor::TIPOS)],
            'lineas.*.sic_id' => 'nullable|exists:solicitudes_sic_borrador,id',
            'lineas.*.folio_sic_manual' => 'nullable|string|max:100',
            'lineas.*.ebs_requisition_id' => 'nullable|exists:ebs_requisitions,id',
            'lineas.*.lugar_entrega_id' => 'nullable|exists:lugares_entrega,id',
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
     * Creación directa desde "SIC en EBS" — cuando llega
     * `?crear_desde_sics=1,2,3` y/o `?crear_desde_ebs=4,5` en la URL (pueden
     * venir ambos a la vez si se seleccionó una mezcla de orígenes), abre el
     * formulario de creación ya precargado con esas filas del pool
     * premarcadas (`seleccionada = true`). Si los 2 query params vienen
     * vacíos/ausentes, el `mount()` no hace nada distinto de siempre (la
     * pantalla abre en su estado normal de listado).
     */
    public function mount(): void
    {
        $sicIds = $this->parseIdsQueryParam('crear_desde_sics');
        $ebsIds = $this->parseIdsQueryParam('crear_desde_ebs');

        if (empty($sicIds) && empty($ebsIds)) {
            return;
        }

        $this->create($sicIds, $ebsIds);
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

    /**
     * Línea "manual" vacía — folio de SIC a mano (origen sic) o captura
     * 100% libre (origen proyecto), sin `sic_id`/`ebs_requisition_id`. Mismo
     * shape que una fila de pool (incluye `seleccionada` por consistencia,
     * aunque no se usa para decidir si una línea manual se guarda — ver
     * `lineasAGuardar()`).
     */
    private function lineaManualVacia(): array
    {
        return [
            'id' => null,
            'sic_id' => null,
            'folio_sic_manual' => null,
            'ebs_requisition_id' => null,
            'lugar_entrega_id' => null,
            'articulo_id' => null,
            'descripcion_libre' => null,
            'cantidad_solicitada' => 1,
            'precio_unitario_cotizado' => null,
            'es_activo_inventariable' => false,
            'observaciones_especificaciones' => null,
            'ebs_item_description' => null,
            'folio_sic_display' => null,
            'articulo_descripcion_preview' => null,
            'seleccionada' => false,
        ];
    }

    public function addLinea(): void
    {
        $this->lineas[] = $this->lineaManualVacia();
    }

    /**
     * Esta acción solo es alcanzable desde el blade para filas manuales
     * (sin `sic_id` ni `ebs_requisition_id`) — una fila de pool ya no se
     * "quita", se desmarca con su checkbox (`lineas.*.seleccionada`).
     */
    public function removeLinea(int $index): void
    {
        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
    }

    public function create(array $sicIdsPreseleccionados = [], array $ebsIdsPreseleccionados = []): void
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
        $this->lineas = $this->origen === 'sic'
            ? $this->construirLineasDesdePool($sicIdsPreseleccionados, $ebsIdsPreseleccionados)
            : [$this->lineaManualVacia()];
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
        $record = SolicitudProveedor::with('lineas.articulo')->findOrFail($id);

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
        $this->origen = $record->proyecto_presupuesto_articulo_id ? 'proyecto' : 'sic';

        if ($this->origen === 'sic') {
            $this->lineas = $this->construirLineasParaEdicion($record);
        } else {
            $this->lineas = $record->lineas->map(fn ($linea) => $this->lineaArrayDesdeRegistro($linea, true))->all();
        }

        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Descripción original de EBS de referencia — para los 2 orígenes
     * posibles de una línea con rastro de EBS: una SIC con
     * `ebs_requisition_id` (camino "SIC local"), o `ebs_requisition_id`
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
     * Cambiar de origen es puramente de UI (qué sección se muestra), pero
     * para no dejar datos "fantasma" inconsistentes con lo que el usuario ve:
     * pasar a "proyecto" reemplaza `$lineas` por una sola línea manual en
     * blanco (se pierde cualquier marca del pool, consistente con que esa
     * tabla deja de mostrarse); pasar a "sic" limpia el artículo de proyecto
     * elegido y reconstruye el pool completo, sin preselección. Decisión
     * tomada sobre la marcha, no especificada 100% en el plan.
     */
    public function updatedOrigen(string $value): void
    {
        if ($value === 'proyecto') {
            $this->lineas = [$this->lineaManualVacia()];
        } else {
            $this->form['proyecto_presupuesto_articulo_id'] = null;
            $this->lineas = $this->construirLineasDesdePool();
        }
    }

    /**
     * Fila de pool para una SIC local — misma forma que
     * `lineaArrayDesdeRegistro()`, pero con los valores por defecto de una
     * línea nunca guardada (cantidad 1, sin precio/lugar/observaciones).
     */
    private function filaDesdeSic(SolicitudSicBorrador $sic, bool $seleccionada): array
    {
        return [
            'id' => null,
            'sic_id' => $sic->id,
            'folio_sic_manual' => null,
            'ebs_requisition_id' => null,
            'lugar_entrega_id' => null,
            'articulo_id' => $sic->articulo_id,
            'descripcion_libre' => null,
            'cantidad_solicitada' => 1,
            'precio_unitario_cotizado' => null,
            'es_activo_inventariable' => $sic->articulo?->es_inventariable ?? true,
            'observaciones_especificaciones' => null,
            'ebs_item_description' => $sic->ebs_requisition_id
                ? $sic->ebsRequisition?->lines->sortBy('line_number')->first()?->item_description
                : null,
            'folio_sic_display' => $sic->folio_sic,
            'articulo_descripcion_preview' => $sic->articulo?->descripcion,
            'seleccionada' => $seleccionada,
        ];
    }

    /**
     * Fila de pool para una requisición de EBS sin SIC local — espejo de
     * `filaDesdeSic()` para el camino directo.
     */
    private function filaDesdeEbs(EbsRequisition $ebsRequisicion, bool $seleccionada): array
    {
        $articulo = $ebsRequisicion->articuloMapeadoDeCompra();

        return [
            'id' => null,
            'sic_id' => null,
            'folio_sic_manual' => null,
            'ebs_requisition_id' => $ebsRequisicion->id,
            'lugar_entrega_id' => null,
            'articulo_id' => $articulo?->id,
            'descripcion_libre' => null,
            'cantidad_solicitada' => 1,
            'precio_unitario_cotizado' => null,
            'es_activo_inventariable' => $articulo?->es_inventariable ?? true,
            'observaciones_especificaciones' => null,
            'ebs_item_description' => $ebsRequisicion->lines->sortBy('line_number')->first()?->item_description,
            'folio_sic_display' => $ebsRequisicion->code,
            'articulo_descripcion_preview' => $articulo?->descripcion,
            'seleccionada' => $seleccionada,
        ];
    }

    /**
     * El pool completo (punto central del rediseño): una fila por cada SIC
     * local elegible (`sicPickerOptions()`) y por cada requisición de EBS
     * elegible sin SIC local (`ebsPickerOptions()`) — todas, estén
     * premarcadas o no. Usado tanto por `create()`/`updatedOrigen()` (sin
     * preselección) como por `mount()` (con preselección desde
     * `crear_desde_sics`/`crear_desde_ebs`).
     */
    private function construirLineasDesdePool(array $sicIdsPreseleccionados = [], array $ebsIdsPreseleccionados = []): array
    {
        $lineas = [];

        foreach ($this->sicPickerOptions() as $sic) {
            $lineas[] = $this->filaDesdeSic($sic, in_array($sic->id, $sicIdsPreseleccionados, true));
        }

        foreach ($this->ebsPickerOptions() as $ebsRequisicion) {
            $lineas[] = $this->filaDesdeEbs($ebsRequisicion, in_array($ebsRequisicion->id, $ebsIdsPreseleccionados, true));
        }

        return $lineas;
    }

    /**
     * Arma el array de una línea YA GUARDADA (con su `id` real y sus
     * valores reales) — usada tanto para filas de pool ya marcadas en una
     * edición previa como para líneas manuales/de proyecto.
     */
    private function lineaArrayDesdeRegistro(SolicitudProveedorLinea $linea, bool $seleccionada): array
    {
        return [
            'id' => $linea->id,
            'sic_id' => $linea->sic_id,
            'folio_sic_manual' => $linea->folio_sic_manual,
            'ebs_requisition_id' => $linea->ebs_requisition_id,
            'lugar_entrega_id' => $linea->lugar_entrega_id,
            'articulo_id' => $linea->articulo_id,
            'descripcion_libre' => $linea->descripcion_libre,
            'cantidad_solicitada' => $linea->cantidad_solicitada,
            'precio_unitario_cotizado' => $linea->precio_unitario_cotizado,
            'es_activo_inventariable' => $linea->es_activo_inventariable,
            'observaciones_especificaciones' => $linea->observaciones_especificaciones,
            'ebs_item_description' => $this->ebsItemDescriptionFor($linea->sic_id, $linea->ebs_requisition_id),
            'folio_sic_display' => $linea->folioSicDisplay(),
            'articulo_descripcion_preview' => $linea->articulo?->descripcion,
            'seleccionada' => $seleccionada,
        ];
    }

    /**
     * Pool completo para `edit()` (origen sic): recorre el mismo pool de
     * `construirLineasDesdePool()` (que YA incluye, gracias a
     * `$exceptSolicitudId` en `sicPickerOptions()`/`ebsPickerOptions()`, las
     * SICs/EBS consumidas por ESTA MISMA solicitud) y por cada entrada
     * revisa si ya existe una línea guardada con ese `sic_id`/
     * `ebs_requisition_id` — si existe, usa sus valores reales y queda
     * marcada; si no, usa la fila fresca del pool sin marcar. Al final
     * agrega las líneas manuales guardadas (sin `sic_id` ni
     * `ebs_requisition_id`) y, como red de seguridad, cualquier línea
     * guardada con `sic_id`/`ebs_requisition_id` que por algún motivo no
     * haya aparecido en el pool (no debería pasar dado `$exceptSolicitudId`,
     * pero así no se pierde el dato).
     */
    private function construirLineasParaEdicion(SolicitudProveedor $record): array
    {
        $guardadasPorSic = $record->lineas->filter(fn ($l) => ! empty($l->sic_id))->keyBy('sic_id');
        $guardadasPorEbs = $record->lineas->filter(fn ($l) => ! empty($l->ebs_requisition_id))->keyBy('ebs_requisition_id');

        $lineas = [];
        $sicIdsCubiertos = [];
        $ebsIdsCubiertos = [];

        foreach ($this->sicPickerOptions() as $sic) {
            $sicIdsCubiertos[] = $sic->id;
            $guardada = $guardadasPorSic->get($sic->id);
            $lineas[] = $guardada
                ? $this->lineaArrayDesdeRegistro($guardada, true)
                : $this->filaDesdeSic($sic, false);
        }

        foreach ($this->ebsPickerOptions() as $ebsRequisicion) {
            $ebsIdsCubiertos[] = $ebsRequisicion->id;
            $guardada = $guardadasPorEbs->get($ebsRequisicion->id);
            $lineas[] = $guardada
                ? $this->lineaArrayDesdeRegistro($guardada, true)
                : $this->filaDesdeEbs($ebsRequisicion, false);
        }

        foreach ($record->lineas->filter(fn ($l) => empty($l->sic_id) && empty($l->ebs_requisition_id)) as $manual) {
            $lineas[] = $this->lineaArrayDesdeRegistro($manual, true);
        }

        foreach ($guardadasPorSic as $sicId => $guardada) {
            if (! in_array($sicId, $sicIdsCubiertos, true)) {
                $lineas[] = $this->lineaArrayDesdeRegistro($guardada, true);
            }
        }

        foreach ($guardadasPorEbs as $ebsId => $guardada) {
            if (! in_array($ebsId, $ebsIdsCubiertos, true)) {
                $lineas[] = $this->lineaArrayDesdeRegistro($guardada, true);
            }
        }

        return $lineas;
    }

    /**
     * Línea "nueva" que nadie ha tocado todavía — mismos valores que
     * `lineaManualVacia()` produce. Usada por `lineasAGuardar()` para no
     * persistir un renglón manual fantasma que el usuario agregó (o que
     * quedó) sin capturar nada.
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
     * Lista filtrada de líneas que realmente se persisten al guardar: una
     * fila de pool (con `sic_id`/`ebs_requisition_id`) solo si está marcada
     * (`seleccionada`); una fila manual siempre, salvo que esté en blanco
     * sin tocar.
     */
    private function lineasAGuardar(): array
    {
        return array_values(array_filter($this->lineas, function ($linea) {
            if (! empty($linea['sic_id']) || ! empty($linea['ebs_requisition_id'])) {
                return ! empty($linea['seleccionada']);
            }

            return ! $this->esLineaEnBlancoSinTocar($linea);
        }));
    }

    /**
     * Abre el detalle de solo lectura de una línea al dar clic en su número
     * de SIC (tabla "Líneas del pedido") — resuelve cuál de los 2 partials
     * mostrar: si hay una requisición de EBS resoluble (directa en la línea,
     * o heredada de su SIC local vinculada) se abre
     * `partials.ebs-requisicion-detalle` (mismo detalle que "SIC en EBS");
     * si no, pero sí hay una SIC local pura, se abre
     * `partials.sic-local-detalle`. Sin efecto si la línea no tiene ningún
     * origen real (solo `folio_sic_manual`) — esa celda nunca ofrece este
     * botón, ver el blade.
     */
    public function openSicDetalle(int $sicId = 0, int $ebsRequisitionId = 0): void
    {
        // `0` en vez de un parámetro nullable — más seguro que depender de
        // que Livewire parsee un literal `null` dentro de `wire:click="...(…)"`
        // (nunca probado en este codebase); los ids reales nunca son 0.
        $sicId = $sicId ?: null;
        $ebsRequisitionId = $ebsRequisitionId ?: null;

        $ebsRequisitionIdResuelto = $ebsRequisitionId;

        if (! $ebsRequisitionIdResuelto && $sicId) {
            $ebsRequisitionIdResuelto = SolicitudSicBorrador::find($sicId)?->ebs_requisition_id;
        }

        if ($ebsRequisitionIdResuelto) {
            $this->detalleEbsRequisitionId = $ebsRequisitionIdResuelto;
            $this->detalleSicLocalId = null;
            $this->showDetalleModal = true;
        } elseif ($sicId) {
            $this->detalleSicLocalId = $sicId;
            $this->detalleEbsRequisitionId = null;
            $this->showDetalleModal = true;
        }
    }

    public function closeDetalle(): void
    {
        $this->showDetalleModal = false;
        $this->detalleEbsRequisitionId = null;
        $this->detalleSicLocalId = null;
    }

    /**
     * "El origen de una Solicitud a Proveedor es una o más SICs/requisiciones
     * de EBS O un artículo de proyecto, no ambos" — regla de negocio del
     * spec, generalizada de "una SIC" a "de 1 a N SICs/EBS": se detecta
     * mirando si ALGUNA línea trae `sic_id`/`ebs_requisition_id` Y está
     * marcada (`seleccionada`) — una fila de pool sin marcar no cuenta,
     * aunque esté presente en `$lineas` (casi todas lo están, sea cual sea
     * lo que el usuario haya elegido). El error se agrega sobre `origen`
     * (el selector visual) y sobre el campo de proyecto para que el mensaje
     * aparezca sin importar cuál mire el usuario primero.
     */
    private function validateOrigenUnico(): void
    {
        $tieneSic = collect($this->lineas)->contains(
            fn ($linea) => (! empty($linea['sic_id']) || ! empty($linea['ebs_requisition_id'])) && ! empty($linea['seleccionada'])
        );

        if ($tieneSic && ! empty($this->form['proyecto_presupuesto_articulo_id'] ?? null)) {
            $mensaje = 'El origen debe ser una o más SICs o un artículo de proyecto, no ambos.';
            $this->addError('origen', $mensaje);
            $this->addError('form.proyecto_presupuesto_articulo_id', $mensaje);
        }
    }

    /**
     * Cada línea que se va a guardar necesita exactamente uno de
     * articulo_id/descripcion_libre (no ambos, no ninguno) — regla
     * dependiente entre 2 campos del mismo renglón que una regla wildcard
     * simple no puede expresar, mismo patrón que `validateSubFieldOptions()`
     * en Modules\FormBuilder\Livewire\Forms\Builder. Las filas de pool sin
     * marcar se saltan por completo — sus valores son solo los defaults del
     * pool (o, en edición, los de una fila "cubierta" que no se tocó), no
     * importa si "parecen" inválidos porque nunca se van a guardar.
     */
    private function validateLineas(): void
    {
        foreach ($this->lineas as $i => $linea) {
            $esFilaDePool = ! empty($linea['sic_id']) || ! empty($linea['ebs_requisition_id']);

            if ($esFilaDePool && empty($linea['seleccionada'])) {
                continue;
            }

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
     * Reemplaza la vieja regla `'lineas' => 'required|array|min:1'` de
     * `rules()` — ya no tiene sentido ahí porque `$lineas` casi siempre
     * tiene entradas (el pool completo), estén marcadas o no. La validación
     * real de "hay algo que guardar" vive aquí, mirando `lineasAGuardar()`.
     */
    private function validateAlMenosUnaLinea(): void
    {
        if (empty($this->lineasAGuardar())) {
            $this->addError('lineas', 'Agrega al menos una línea: marca una SIC/EBS del listado o agrega una línea manual.');
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
        $this->validateAlMenosUnaLinea();

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
        foreach ($this->lineasAGuardar() as $linea) {
            $attributes = [
                'sic_id' => $linea['sic_id'] ?: null,
                'folio_sic_manual' => ($linea['folio_sic_manual'] ?? '') !== '' ? $linea['folio_sic_manual'] : null,
                'ebs_requisition_id' => $linea['ebs_requisition_id'] ?: null,
                'lugar_entrega_id' => $linea['lugar_entrega_id'] ?: null,
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
     * "SICs autorizadas y disponibles" — mismo criterio `whereDoesntHave(...)`
     * ya usado en `Asignaciones::render()`/`Stock::sicReservationOptions()`
     * para "SIC autorizada y aún no consumida", extendido con el filtro de
     * categoría-va-a-compra (`CategoriaArticulo::where('es_compra', true)`,
     * pestaña "Categoría" de Catálogos de Compras). Una SIC
     * sin `articulo_id` todavía (típico de una recién sincronizada de EBS,
     * sin clasificar) o cuya categoría no esté marcada como "va a Compra"
     * no aparece — sigue disponible solo por captura manual (folio de
     * texto). En modo edición, las SICs ya recogidas por ESTA MISMA
     * solicitud se siguen mostrando (y marcadas, ver `construirLineasParaEdicion()`)
     * aunque ya tengan una línea — de lo contrario desaparecerían del pool
     * al reabrir la solicitud para editarla.
     */
    private function sicPickerOptions()
    {
        return SolicitudSicBorrador::autorizadaYSeleccionable($this->editingId)
            ->with(['empleado', 'ticket', 'articulo.categoria'])
            ->orderByDesc('fecha_solicitud')
            ->get();
    }

    /**
     * Segunda fuente del mismo pool (unión con `sicPickerOptions()`) —
     * requisiciones de EBS que NUNCA tuvieron SIC local, aprobadas, con su
     * artículo mapeado de categoría "va a Compra" y sin asignar todavía por
     * el camino directo. Mismo criterio de elegibilidad que "SIC en EBS"
     * (`EbsRequisition::scopeElegibleDirectoSinSic()`).
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

        $detalleEbsRequisicion = $this->showDetalleModal && $this->detalleEbsRequisitionId
            ? EbsRequisition::with([
                'lines', 'notes',
                'solicitudSicBorrador.ticket',
                'solicitudSicBorrador.solicitudProveedorLineas.solicitud',
                'solicitudProveedorLineas.solicitud',
            ])->find($this->detalleEbsRequisitionId)
            : null;

        $detalleSicLocal = $this->showDetalleModal && $this->detalleSicLocalId
            ? SolicitudSicBorrador::with(['empleado', 'ticket', 'tipoEquipo', 'articulo.categoria', 'centroCosto', 'solicitudProveedorLineas.solicitud'])->find($this->detalleSicLocalId)
            : null;

        return view('gestionti::livewire.compras.solicitudes-proveedor', [
            'detalleEbsRequisicion' => $detalleEbsRequisicion,
            'detalleSicLocal' => $detalleSicLocal,
            // Mismo mapa que `MesaServicio\EbsRequisiciones` — el partial
            // compartido `partials.ebs-requisicion-detalle` lo necesita tal
            // cual para pintar el badge de estatus de la requisición.
            'ebsEstatusColors' => [
                'APPROVED' => 'emerald',
                'REJECTED' => 'red',
                'IN PROCESS' => 'indigo',
            ],
            'records' => $records,
            'vendorOptions' => Proveedor::where('activo', true)->orderBy('nombre_comercial')->get(),
            'ticketOptions' => Ticket::orderByDesc('fecha')->get(),
            'articuloOptions' => ArticuloSolicitud::where('activo', true)->orderBy('descripcion')->get(),
            'lugarEntregaOptions' => LugarEntrega::where('activo', true)->orderBy('nombre')->get(),
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
