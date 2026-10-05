<?php

namespace Modules\GestionTI\Livewire\Compras;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
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
 * "Solicitud a Proveedores" — origen "de 1 a N SICs/EBS" (`$seleccion`) O un
 * artículo de Proyecto de Presupuesto (`form.proyecto_presupuesto_articulo_id`),
 * nunca ambos — regla de negocio validada en `validateOrigenUnico()`.
 * Ver docs/gestionti-progreso.md, entrada "Pantalla única con pool paginado y
 * selección que se conserva" para el diseño completo.
 *
 * Pantalla única: "Nuevo"/"Editar" reemplaza el listado por el formulario
 * (`$showForm`), sin modal ni pestañas. En origen "sic" el formulario muestra
 * una tabla PAGINADA con el pool de SICs/EBS elegibles (`pool()`, computed,
 * NUNCA una propiedad pública — no viaja serializado en cada request); lo
 * único que persiste en el estado del componente es `$seleccion`: las filas
 * del pool que el usuario marcó, indexadas por clave estable (`s{sic_id}` o
 * `e{ebs_requisition_id}`), así la selección sobrevive a cambiar de página,
 * buscar o filtrar. Las líneas que no vienen de ninguna SIC/EBS real (folio
 * de SIC a mano, o captura 100% libre en origen proyecto) viven aparte, en
 * `$lineasManuales`.
 */
#[Layout('layouts.app')]
class SolicitudesProveedor extends Component
{
    use WithPagination;

    /** Filas del pool por página en la tabla de selección de SICs/EBS. */
    private const POR_PAGINA = 15;

    /** Nombre del paginador de la tabla de SICs (distinto del `page` del listado). */
    private const PAGINADOR_SICS = 'sicsPage';

    public ?int $editingId = null;

    public array $form = [];

    /**
     * 'sic' (una o más SICs, default) | 'proyecto' (un artículo de
     * Proyecto de Presupuesto) — puramente un organizador de la UI, no una
     * columna real: la validación de exclusividad real
     * (`validateOrigenUnico()`) mira los datos (`$seleccion` vs.
     * `form.proyecto_presupuesto_articulo_id`), no este campo.
     */
    public string $origen = 'sic';

    /**
     * SOLO las filas del pool marcadas por el usuario (el pool completo
     * nunca vive aquí, ver `pool()`), indexadas por clave estable de cadena
     * sin puntos: `'s'.$sicId` para una SIC local y `'e'.$ebsRequisitionId`
     * para una requisición de EBS directa. `ebs_item_description`/
     * `folio_sic_display`/`articulo_descripcion_preview` son puramente
     * informativos (no se guardan en la BD): la descripción original de EBS
     * para detectar un mapeo automático incorrecto, y la etiqueta de la SIC
     * a mostrar en la tabla.
     *
     * @var array<string, array{id: ?int, sic_id: ?int, folio_sic_manual: ?string, ebs_requisition_id: ?int, lugar_entrega_id: ?int, articulo_id: ?int, descripcion_libre: ?string, cantidad_solicitada: int, precio_unitario_cotizado: ?float, observaciones_especificaciones: ?string, ebs_item_description: ?string, folio_sic_display: ?string, articulo_descripcion_preview: ?string}>
     */
    public array $seleccion = [];

    /**
     * Líneas SIN `sic_id` ni `ebs_requisition_id`: captura manual del folio
     * de una SIC que aún no existe como registro (origen sic) o líneas
     * libres de un pedido de proyecto (origen proyecto). Mismo shape que una
     * fila de `$seleccion`.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $lineasManuales = [];

    #[Url(as: 'search')]
    public string $search = '';

    public string $estatusFilter = '';

    /** Buscador de la tabla de SICs del formulario (distinto de `$search`, el del listado). */
    public string $sicSearch = '';

    /** Muestra solo las filas ya seleccionadas (revisar una selección que abarca varias páginas). */
    public bool $soloSeleccionadas = false;

    /** `true` = el formulario de Nuevo/Editar reemplaza al listado. */
    public bool $showForm = false;

    /**
     * Modal de solo lectura "ver detalle de la SIC" al dar clic sobre el
     * número de SIC en la tabla de selección — mismo detalle que ya existe
     * en "SIC en EBS" (`MesaServicio\EbsRequisiciones::openDetalle()`),
     * reutilizado vía el partial compartido `partials.ebs-requisicion-detalle`.
     * Para una fila con requisición de EBS resuelta (directa, o heredada de
     * su SIC local vinculada) se abre ESE detalle; para una SIC puramente
     * local (sin EBS) se abre en su lugar `partials.sic-local-detalle`.
     */
    public bool $showDetalleModal = false;

    public ?int $detalleEbsRequisitionId = null;

    public ?int $detalleSicLocalId = null;

    protected function rules(): array
    {
        $rules = [
            'form.folio' => ['required', 'string', 'max:100', Rule::unique('solicitudes_proveedor', 'folio')->ignore($this->editingId)],
            'form.vendor_id' => 'required|exists:proveedores,id',
            'form.fecha_solicitud' => 'required|date',
            'form.ticket_id' => 'nullable|exists:tickets,id',
            'form.proyecto_presupuesto_articulo_id' => 'nullable|exists:proyecto_presupuesto_articulos,id',
            'form.tipo_solicitud' => ['required', Rule::in(SolicitudProveedor::TIPOS)],
        ];

        $reglasLinea = [
            'sic_id' => 'nullable|exists:solicitudes_sic_borrador,id',
            'folio_sic_manual' => 'nullable|string|max:100',
            'ebs_requisition_id' => 'nullable|exists:ebs_requisitions,id',
            'lugar_entrega_id' => 'nullable|exists:lugares_entrega,id',
            'articulo_id' => 'nullable|exists:articulos_solicitud,id',
            'descripcion_libre' => 'nullable|string|max:255',
            'cantidad_solicitada' => 'required|integer|min:1',
            'precio_unitario_cotizado' => 'nullable|numeric|min:0',
            'observaciones_especificaciones' => 'nullable|string|max:1000',
        ];

        foreach (['seleccion', 'lineasManuales'] as $conjunto) {
            foreach ($reglasLinea as $campo => $regla) {
                $rules["$conjunto.*.$campo"] = $regla;
            }
        }

        return $rules;
    }

    /**
     * Nombres legibles para los mensajes de validación de las líneas (el
     * resumen de errores del formulario los muestra tal cual).
     */
    protected function validationAttributes(): array
    {
        $atributos = [];
        $nombres = [
            'lugar_entrega_id' => 'lugar de entrega',
            'articulo_id' => 'artículo',
            'descripcion_libre' => 'descripción libre',
            'cantidad_solicitada' => 'cantidad',
            'precio_unitario_cotizado' => 'precio unitario',
            'observaciones_especificaciones' => 'observaciones',
            'folio_sic_manual' => 'folio de SIC',
        ];

        foreach (['seleccion', 'lineasManuales'] as $conjunto) {
            foreach ($nombres as $campo => $nombre) {
                $atributos["$conjunto.*.$campo"] = $nombre;
            }
        }

        return $atributos;
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
     * formulario de creación ya precargado con esas filas dentro de
     * `$seleccion` (aunque caigan en otra página de la tabla, la selección
     * ya está hecha). Si los 2 query params vienen vacíos/ausentes, el
     * `mount()` no hace nada distinto de siempre (la pantalla abre en su
     * estado normal de listado).
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

    public function updatingSicSearch(): void
    {
        $this->resetPage(self::PAGINADOR_SICS);
    }

    public function updatingSoloSeleccionadas(): void
    {
        $this->resetPage(self::PAGINADOR_SICS);
    }

    /**
     * Elegir un artículo del catálogo en una fila del pool descarta una
     * descripción libre heredada (líneas guardadas con el diseño anterior,
     * donde la fila de pool también aceptaba texto libre) — la tabla de
     * selección solo ofrece el select, así que si no se limpiara la
     * validación "no ambos" fallaría con un campo que el usuario no ve.
     */
    public function updatedSeleccion($value = null, ?string $key = null): void
    {
        if ($key === null || ! str_ends_with($key, '.articulo_id') || $value === null || $value === '') {
            return;
        }

        $clave = explode('.', $key, 2)[0];

        if (isset($this->seleccion[$clave])) {
            $this->seleccion[$clave]['descripcion_libre'] = null;
        }
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
     * previas).
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
     * 100% libre (origen proyecto), sin `sic_id`/`ebs_requisition_id`.
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
            'observaciones_especificaciones' => null,
            'ebs_item_description' => null,
            'folio_sic_display' => null,
            'articulo_descripcion_preview' => null,
        ];
    }

    public function addLinea(): void
    {
        $this->lineasManuales[] = $this->lineaManualVacia();
    }

    /** Solo alcanzable desde el blade para líneas manuales (`$lineasManuales`). */
    public function removeLinea(int $index): void
    {
        unset($this->lineasManuales[$index]);
        $this->lineasManuales = array_values($this->lineasManuales);
    }

    /**
     * Limpia TODO el estado del formulario (también el del pool: búsqueda,
     * filtro, página y el computed cacheado) — compartido por `create()`,
     * `edit()` y `cancel()`/`save()`.
     */
    private function resetFormState(): void
    {
        $this->editingId = null;
        $this->form = [];
        $this->origen = 'sic';
        $this->seleccion = [];
        $this->lineasManuales = [];
        $this->sicSearch = '';
        $this->soloSeleccionadas = false;
        $this->resetPage(self::PAGINADOR_SICS);
        unset($this->pool, $this->restringidaASusSics);
        $this->resetValidation();
    }

    public function create(array $sicIdsPreseleccionados = [], array $ebsIdsPreseleccionados = []): void
    {
        $this->resetFormState();

        $this->form = [
            'folio' => $this->suggestFolio(),
            'vendor_id' => null,
            'fecha_solicitud' => now()->format('Y-m-d'),
            'ticket_id' => null,
            'proyecto_presupuesto_articulo_id' => null,
            'tipo_solicitud' => 'regular',
        ];

        if (! empty($sicIdsPreseleccionados) || ! empty($ebsIdsPreseleccionados)) {
            foreach ($this->pool as $clave => $fila) {
                $preseleccionada = $fila['tipo'] === 'sic'
                    ? in_array($fila['sic_id'], $sicIdsPreseleccionados, true)
                    : in_array($fila['ebs_requisition_id'], $ebsIdsPreseleccionados, true);

                if ($preseleccionada) {
                    $this->seleccion[$clave] = $this->filaDeSeleccion($fila);
                }
            }
        }

        $this->showForm = true;
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

        $this->resetFormState();

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

        foreach ($record->lineas as $linea) {
            $clave = $this->claveDeLinea($linea);

            if ($clave === null) {
                $this->lineasManuales[] = $this->lineaDesdeRegistro($linea);

                continue;
            }

            // El pool ya incluye las SICs/EBS consumidas por ESTA solicitud
            // (`$exceptSolicitudId`) y, como red de seguridad, inyecta
            // cualquier línea guardada que no apareciera — ver `pool()`.
            $this->seleccion[$clave] = $this->lineaDesdeRegistro($linea, $this->pool->get($clave));
        }

        $this->showForm = true;
    }

    /**
     * Clave estable de pool (`s{sic_id}` / `e{ebs_requisition_id}`) de una
     * línea guardada, o `null` si es una línea manual.
     */
    private function claveDeLinea(SolicitudProveedorLinea $linea): ?string
    {
        if (! empty($linea->sic_id)) {
            return 's'.$linea->sic_id;
        }

        if (! empty($linea->ebs_requisition_id)) {
            return 'e'.$linea->ebs_requisition_id;
        }

        return null;
    }

    /**
     * Descripción original de EBS de referencia: la de la PRIMERA línea (por
     * `line_number`) de la requisición — se muestra de solo lectura junto al
     * select de artículo para detectar a simple vista si el mapeo
     * automático (`EbsArticulo`) eligió mal. Requiere `lines` cargadas.
     */
    private function descripcionEbs(?EbsRequisition $ebsRequisicion): ?string
    {
        return $ebsRequisicion?->lines->sortBy('line_number')->first()?->item_description;
    }

    /**
     * Cantidad que trae la SIC en EBS (primera línea, la misma de la que
     * sale la descripción y el artículo mapeado) — precarga la cantidad
     * solicitada al marcar la fila. Una SIC puramente local no tiene
     * cantidad propia: 1. Requiere `lines` cargadas.
     */
    private function cantidadEbs(?EbsRequisition $ebsRequisicion): int
    {
        $cantidad = $ebsRequisicion?->lines->sortBy('line_number')->first()?->quantity;

        return max(1, (int) round((float) ($cantidad ?? 1)));
    }

    /**
     * Cambiar de origen es puramente de UI (qué sección se muestra), pero
     * para no dejar datos "fantasma" inconsistentes con lo que el usuario ve:
     * pasar a "proyecto" descarta la selección del pool y deja una sola
     * línea manual en blanco; pasar a "sic" limpia el artículo de proyecto
     * elegido y arranca limpio (sin selección ni líneas manuales).
     */
    public function updatedOrigen(string $value): void
    {
        $this->seleccion = [];

        if ($value === 'proyecto') {
            $this->lineasManuales = [$this->lineaManualVacia()];
        } else {
            $this->form['proyecto_presupuesto_articulo_id'] = null;
            $this->lineasManuales = [];
        }
    }

    /**
     * Línea de `$seleccion` recién marcada: los valores por defecto de una
     * línea nunca guardada (cantidad 1, artículo mapeado, inventariable
     * según el artículo, sin precio/lugar/observaciones).
     */
    private function filaDeSeleccion(array $filaPool): array
    {
        return [
            'id' => null,
            'sic_id' => $filaPool['sic_id'],
            'folio_sic_manual' => null,
            'ebs_requisition_id' => $filaPool['ebs_requisition_id'],
            'lugar_entrega_id' => null,
            'articulo_id' => $filaPool['articulo_id'],
            'descripcion_libre' => null,
            'cantidad_solicitada' => $filaPool['cantidad'],
            'precio_unitario_cotizado' => null,
            'observaciones_especificaciones' => null,
            'ebs_item_description' => $filaPool['ebs_item_description'],
            'folio_sic_display' => $filaPool['folio_sic_display'],
            'articulo_descripcion_preview' => $filaPool['articulo_descripcion_preview'],
        ];
    }

    /**
     * Arma el array de una línea YA GUARDADA (con su `id` real y sus valores
     * reales). Los campos informativos salen de la fila del pool cuando se
     * tiene (`$filaPool`), para no consultar nada por línea; las líneas
     * manuales no tienen.
     */
    private function lineaDesdeRegistro(SolicitudProveedorLinea $linea, ?array $filaPool = null): array
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
            'observaciones_especificaciones' => $linea->observaciones_especificaciones,
            'ebs_item_description' => $filaPool['ebs_item_description'] ?? null,
            'folio_sic_display' => $filaPool['folio_sic_display'] ?? null,
            'articulo_descripcion_preview' => $filaPool['articulo_descripcion_preview'] ?? null,
        ];
    }

    /**
     * Fila normalizada del pool (no es una línea del pedido, es la fuente
     * de la tabla de selección): clave estable, origen, ids, etiquetas para
     * mostrar, defaults para cuando se marque y un texto en minúsculas para
     * el buscador.
     */
    private function filaDePool(string $tipo, int $id, ?string $folio, ?EbsRequisition $ebsRequisicion, ?ArticuloSolicitud $articulo, ?int $fecha): array
    {
        $descripcionEbs = $this->descripcionEbs($ebsRequisicion);

        return [
            'clave' => ($tipo === 'sic' ? 's' : 'e').$id,
            'tipo' => $tipo,
            'sic_id' => $tipo === 'sic' ? $id : null,
            'ebs_requisition_id' => $tipo === 'ebs' ? $id : null,
            'folio_sic_display' => $folio,
            'ebs_item_description' => $descripcionEbs,
            'articulo_id' => $articulo?->id,
            'articulo_descripcion_preview' => $articulo?->descripcion,
            'cantidad' => $this->cantidadEbs($ebsRequisicion),
            'fecha' => $fecha,
            'busqueda' => mb_strtolower(implode(' ', array_filter([$folio, $descripcionEbs, $articulo?->descripcion]))),
        ];
    }

    /**
     * Solicitud ya enviada al proveedor que se está editando (solo un
     * Administrador puede, ver `puedeEditar()`): ya no admite SICs/EBS nuevas,
     * únicamente las que se asignaron originalmente. Se lee de la BD (no de
     * estado del cliente) para que no se pueda manipular desde el navegador.
     */
    #[Computed]
    public function restringidaASusSics(): bool
    {
        return $this->editingId !== null
            && SolicitudProveedor::whereKey($this->editingId)->whereNotNull('enviada_at')->exists();
    }

    /**
     * El pool completo (punto central del diseño): una fila por cada SIC
     * local elegible (`sicPickerOptions()`) y por cada requisición de EBS
     * elegible sin SIC local (`ebsPickerOptions()`), ordenadas de la más
     * reciente a la más antigua e indexadas por clave. `#[Computed]` lo
     * cachea durante el request (render + acciones lo comparten sin repetir
     * queries) y NO se serializa al cliente. Se arma sin N+1: las SICs traen
     * `articulo` y `ebsRequisition.lines` precargados, y el artículo mapeado
     * de cada requisición de EBS se resuelve UNA sola vez
     * (`ebsPickerOptions()`), no por render ni por fila.
     *
     * En modo edición, red de seguridad: cualquier línea guardada de ESTA
     * solicitud con SIC/EBS que no aparezca en el pool (no debería pasar,
     * `$exceptSolicitudId` la incluye) se inyecta para que se vea y no se
     * pierda.
     *
     * @return Collection<string, array<string, mixed>>
     */
    #[Computed]
    public function pool(): Collection
    {
        $filas = collect();

        // Solicitud ya enviada: no se ofrecen SICs/EBS nuevas — el pool queda
        // solo con las líneas guardadas (se inyectan más abajo).
        $candidatas = $this->restringidaASusSics;

        foreach ($candidatas ? [] : $this->sicPickerOptions() as $sic) {
            $filas->push($this->filaDePool(
                'sic',
                $sic->id,
                $sic->folio_sic,
                $sic->ebsRequisition,
                $sic->articulo,
                $sic->fecha_solicitud?->timestamp,
            ));
        }

        foreach ($candidatas ? [] : $this->ebsPickerOptions() as ['requisicion' => $ebsRequisicion, 'articulo' => $articulo]) {
            $filas->push($this->filaDePool(
                'ebs',
                $ebsRequisicion->id,
                $ebsRequisicion->code,
                $ebsRequisicion,
                $articulo,
                $ebsRequisicion->fecha_creacion?->timestamp,
            ));
        }

        $filas = $filas->sortByDesc(fn ($fila) => $fila['fecha'] ?? 0)->keyBy('clave');

        if ($this->editingId) {
            $guardadas = SolicitudProveedorLinea::where('solicitud_id', $this->editingId)
                ->where(fn ($q) => $q->whereNotNull('sic_id')->orWhereNotNull('ebs_requisition_id'))
                ->get()
                ->reject(fn ($linea) => $filas->has($this->claveDeLinea($linea)));

            if ($guardadas->isNotEmpty()) {
                $guardadas->load(['articulo', 'sic.ebsRequisition.lines', 'ebsRequisition.lines']);

                foreach ($guardadas as $linea) {
                    $fila = $this->filaDePool(
                        $linea->sic_id ? 'sic' : 'ebs',
                        $linea->sic_id ?: $linea->ebs_requisition_id,
                        $linea->folioSicDisplay(),
                        $linea->ebsRequisition ?? $linea->sic?->ebsRequisition,
                        $linea->articulo,
                        PHP_INT_MAX,
                    );
                    $filas->prepend($fila, $fila['clave']);
                }
            }
        }

        return $filas;
    }

    /**
     * Marca/desmarca una fila del pool. Marcar agrega a `$seleccion` la fila
     * con sus defaults; desmarcar la quita. Una clave que no exista en el
     * pool (manipulada, o ya no elegible) se ignora.
     */
    public function toggleSeleccion(string $clave): void
    {
        if (isset($this->seleccion[$clave])) {
            unset($this->seleccion[$clave]);

            return;
        }

        $fila = $this->pool->get($clave);

        if ($fila !== null) {
            $this->seleccion[$clave] = $this->filaDeSeleccion($fila);
        }
    }

    /**
     * Pool filtrado (buscador + "solo seleccionadas") y paginado a mano —
     * 15 por página con su propio paginador (`sicsPage`) integrado con
     * `WithPagination`, así `links()` dispara `gotoPage(N, 'sicsPage')`. Una
     * página fuera de rango (p. ej. tras filtrar) se corrige a la última.
     */
    private function sicsPaginadas(): LengthAwarePaginator
    {
        $termino = mb_strtolower(trim($this->sicSearch));

        $filtradas = $this->pool
            ->when($termino !== '', fn ($filas) => $filas->filter(fn ($fila) => str_contains($fila['busqueda'], $termino)))
            ->when($this->soloSeleccionadas, fn ($filas) => $filas->filter(fn ($fila) => isset($this->seleccion[$fila['clave']])))
            ->values();

        $total = $filtradas->count();
        $ultimaPagina = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, (int) ($this->paginators[self::PAGINADOR_SICS] ?? 1)), $ultimaPagina);
        $this->paginators[self::PAGINADOR_SICS] = $pagina;

        return new LengthAwarePaginator(
            $filtradas->forPage($pagina, self::POR_PAGINA)->values(),
            $total,
            self::POR_PAGINA,
            $pagina,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => self::PAGINADOR_SICS],
        );
    }

    /**
     * Línea manual "nueva" que nadie ha tocado todavía — mismos valores que
     * `lineaManualVacia()` produce. Usada para no persistir (ni validar) un
     * renglón manual fantasma que el usuario agregó sin capturar nada.
     */
    private function esLineaEnBlancoSinTocar(array $linea): bool
    {
        return empty($linea['id'])
            && empty($linea['folio_sic_manual'])
            && empty($linea['articulo_id'])
            && trim((string) ($linea['descripcion_libre'] ?? '')) === ''
            && empty($linea['precio_unitario_cotizado'])
            && empty($linea['lugar_entrega_id'])
            && trim((string) ($linea['observaciones_especificaciones'] ?? '')) === '';
    }

    /**
     * Líneas que realmente se persisten: TODAS las de `$seleccion` (estén o
     * no en la página que se está viendo) más las manuales con contenido. El
     * origen real (`sic_id`/`ebs_requisition_id`) se re-deriva de la clave de
     * la fila — nunca se confía en lo que traiga el cliente dentro del valor
     * — y las manuales siempre salen sin ninguno de los dos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineasParaPersistir(): array
    {
        $deSeleccion = collect($this->seleccion)
            ->filter(fn ($linea, $clave) => preg_match('/^[se]\d+$/', (string) $clave) === 1)
            ->map(fn ($linea, $clave) => array_merge($linea, [
                'sic_id' => $clave[0] === 's' ? (int) substr($clave, 1) : null,
                'ebs_requisition_id' => $clave[0] === 'e' ? (int) substr($clave, 1) : null,
            ]));

        $manuales = collect($this->lineasManuales)
            ->reject(fn ($linea) => $this->esLineaEnBlancoSinTocar($linea))
            ->map(fn ($linea) => array_merge($linea, ['sic_id' => null, 'ebs_requisition_id' => null]));

        return $deSeleccion->values()->merge($manuales->values())->all();
    }

    /**
     * Abre el detalle de solo lectura de una fila al dar clic en su número
     * de SIC (tabla de selección) — resuelve cuál de los 2 partials
     * mostrar: si hay una requisición de EBS resoluble (directa, o heredada
     * de su SIC local vinculada) se abre `partials.ebs-requisicion-detalle`
     * (mismo detalle que "SIC en EBS"); si no, pero sí hay una SIC local
     * pura, se abre `partials.sic-local-detalle`.
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
     * mirando si hay alguna fila en `$seleccion`. El error se agrega sobre
     * `origen` (el selector visual) y sobre el campo de proyecto para que el
     * mensaje aparezca sin importar cuál mire el usuario primero.
     */
    private function validateOrigenUnico(): void
    {
        if (! empty($this->seleccion) && ! empty($this->form['proyecto_presupuesto_articulo_id'] ?? null)) {
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
     * en Modules\FormBuilder\Livewire\Forms\Builder. Aplica a las filas de
     * `$seleccion` (en todas las páginas) y a las manuales con contenido (las
     * manuales en blanco se descartan al guardar, no se validan).
     */
    private function validateLineas(): void
    {
        foreach (['seleccion' => $this->seleccion, 'lineasManuales' => $this->lineasManuales] as $conjunto => $filas) {
            foreach ($filas as $clave => $linea) {
                if ($conjunto === 'lineasManuales' && $this->esLineaEnBlancoSinTocar($linea)) {
                    continue;
                }

                if (empty($linea['lugar_entrega_id'])) {
                    $this->addError("$conjunto.$clave.lugar_entrega_id", 'Elige el lugar de entrega.');
                }

                $tieneArticulo = ! empty($linea['articulo_id']);
                $tieneDescripcion = trim((string) ($linea['descripcion_libre'] ?? '')) !== '';

                if ($tieneArticulo && $tieneDescripcion) {
                    $this->addError("$conjunto.$clave.articulo_id", 'Elige un artículo del catálogo o captura una descripción libre, no ambos.');
                } elseif (! $tieneArticulo && ! $tieneDescripcion) {
                    $this->addError("$conjunto.$clave.articulo_id", $conjunto === 'seleccion'
                        ? 'Elige un artículo del catálogo.'
                        : 'Elige un artículo del catálogo o captura una descripción libre.');
                }
            }
        }
    }

    /**
     * Solicitud ya enviada: solo se conservan las SICs/EBS asignadas
     * originalmente — se rechaza cualquier SIC/EBS o línea manual de SIC nueva
     * (defensa del servidor; la UI ya no las ofrece).
     */
    private function validateSinOrigenesNuevos(): void
    {
        if (! $this->restringidaASusSics) {
            return;
        }

        $originales = SolicitudProveedorLinea::where('solicitud_id', $this->editingId)->get()
            ->map(fn ($linea) => $this->claveDeLinea($linea))
            ->filter()
            ->all();

        $nuevas = collect(array_keys($this->seleccion))->reject(fn ($clave) => in_array($clave, $originales, true));
        $manualesNuevas = $this->origen === 'sic'
            && collect($this->lineasManuales)->contains(fn ($linea) => empty($linea['id']) && ! $this->esLineaEnBlancoSinTocar($linea));

        if ($nuevas->isNotEmpty() || $manualesNuevas) {
            $this->addError('lineas', 'Esta solicitud ya se envió al proveedor: no se pueden agregar SICs nuevas, solo conservar las asignadas originalmente.');
        }
    }

    /**
     * La validación real de "hay algo que guardar": ni la selección ni las
     * manuales con contenido producen ninguna línea.
     */
    private function validateAlMenosUnaLinea(): void
    {
        if (empty($this->lineasParaPersistir())) {
            $this->addError('lineas', 'Agrega al menos una línea: marca una SIC/EBS del listado o agrega una línea manual.');
        }
    }

    /**
     * Resumen de errores de las líneas, para mostrarlo junto al botón
     * Guardar: una fila seleccionada con error puede estar en otra página de
     * la tabla (o fuera del filtro actual), donde su borde rojo no se ve —
     * este resumen la nombra con su mensaje para que el usuario la pueda
     * ubicar.
     *
     * @return array<int, array{etiqueta: string, mensajes: array<int, string>}>
     */
    private function erroresDeLineas(): array
    {
        $errores = $this->getErrorBag();
        $resumen = [];

        foreach ($this->seleccion as $clave => $linea) {
            $mensajes = collect($errores->get("seleccion.$clave.*"))->flatten()->unique()->values()->all();

            if (! empty($mensajes)) {
                $resumen[] = ['etiqueta' => $linea['folio_sic_display'] ?? $clave, 'mensajes' => $mensajes];
            }
        }

        foreach ($this->lineasManuales as $i => $linea) {
            $mensajes = collect($errores->get("lineasManuales.$i.*"))->flatten()->unique()->values()->all();

            if (! empty($mensajes)) {
                $resumen[] = ['etiqueta' => 'Línea manual '.($i + 1), 'mensajes' => $mensajes];
            }
        }

        return $resumen;
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
        $this->validateSinOrigenesNuevos();
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

        // `es_activo_inventariable` ya no se captura: es informativo y sale del
        // atributo `es_inventariable` del artículo elegido (sin artículo, o con
        // descripción libre, es no inventariable).
        $lineas = $this->lineasParaPersistir();
        $inventariablePorArticulo = ArticuloSolicitud::whereIn('id', collect($lineas)->pluck('articulo_id')->filter()->unique())
            ->pluck('es_inventariable', 'id');

        $keptIds = [];
        foreach ($lineas as $linea) {
            $attributes = [
                'sic_id' => $linea['sic_id'] ?: null,
                'folio_sic_manual' => ($linea['folio_sic_manual'] ?? '') !== '' ? $linea['folio_sic_manual'] : null,
                'ebs_requisition_id' => $linea['ebs_requisition_id'] ?: null,
                'lugar_entrega_id' => $linea['lugar_entrega_id'] ?: null,
                'articulo_id' => $linea['articulo_id'] ?: null,
                'descripcion_libre' => $linea['descripcion_libre'] ?: null,
                'cantidad_solicitada' => $linea['cantidad_solicitada'],
                'precio_unitario_cotizado' => ($linea['precio_unitario_cotizado'] ?? '') !== '' ? $linea['precio_unitario_cotizado'] : null,
                'es_activo_inventariable' => (bool) ($inventariablePorArticulo[$linea['articulo_id']] ?? false),
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

        $this->resetFormState();
        $this->showForm = false;
        session()->flash('status', 'Guardado correctamente.');
    }

    /** "← Volver al listado": descarta el formulario sin guardar. */
    public function cancel(): void
    {
        $this->resetFormState();
        $this->showForm = false;
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

        if ($record->lineas->contains(fn ($linea) => empty($linea->lugar_entrega_id))) {
            session()->flash('error', 'Todas las líneas deben tener lugar de entrega antes de enviar — edita la solicitud y captúralo.');

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
     * sin clasificar) o cuya categoría no esté marcada como "va a Compra" no
     * aparece — sigue disponible solo por captura manual (folio de texto).
     * En modo edición, las SICs ya recogidas por ESTA MISMA solicitud se
     * siguen mostrando (y marcadas, ver `edit()`) aunque ya tengan una
     * línea — de lo contrario desaparecerían del pool al reabrir la
     * solicitud para editarla.
     */
    private function sicPickerOptions()
    {
        return SolicitudSicBorrador::autorizadaYSeleccionable($this->editingId)
            ->with(['articulo', 'ebsRequisition.lines'])
            ->orderByDesc('fecha_solicitud')
            ->get();
    }

    /**
     * Segunda fuente del mismo pool (unión con `sicPickerOptions()`) —
     * requisiciones de EBS que NUNCA tuvieron SIC local, aprobadas, con su
     * artículo mapeado de categoría "va a Compra" y sin asignar todavía por
     * el camino directo. Mismo criterio de elegibilidad que "SIC en EBS"
     * (`EbsRequisition::scopeElegibleDirectoSinSic()`). Devuelve cada
     * requisición junto con su artículo mapeado, resuelto UNA sola vez
     * (`articuloMapeadoDeCompra()` consulta la BD por requisición): quien
     * arma el pool reutiliza ese artículo en vez de resolverlo otra vez.
     *
     * @return Collection<int, array{requisicion: EbsRequisition, articulo: ArticuloSolicitud}>
     */
    private function ebsPickerOptions(): Collection
    {
        return EbsRequisition::elegibleDirectoSinSic($this->editingId)
            ->with('lines')
            ->orderByDesc('fecha_creacion')
            ->get()
            ->map(fn ($ebsRequisicion) => ['requisicion' => $ebsRequisicion, 'articulo' => $ebsRequisicion->articuloMapeadoDeCompra()])
            ->filter(fn ($par) => $par['articulo'] !== null)
            ->values();
    }

    public function render()
    {
        $data = [
            'ebsEstatusColors' => [
                'APPROVED' => 'emerald',
                'REJECTED' => 'red',
                'IN PROCESS' => 'indigo',
            ],
        ];

        if ($this->showForm) {
            $data += [
                'sics' => $this->origen === 'sic' ? $this->sicsPaginadas() : null,
                'restringidaASusSics' => $this->restringidaASusSics,
                'erroresLineas' => $this->erroresDeLineas(),
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
            ];
        } else {
            $data['records'] = SolicitudProveedor::query()
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
        }

        $data['detalleEbsRequisicion'] = $this->showDetalleModal && $this->detalleEbsRequisitionId
            ? EbsRequisition::with([
                'lines', 'notes',
                'solicitudSicBorrador.ticket',
                'solicitudSicBorrador.solicitudProveedorLineas.solicitud',
                'solicitudProveedorLineas.solicitud',
            ])->find($this->detalleEbsRequisitionId)
            : null;

        $data['detalleSicLocal'] = $this->showDetalleModal && $this->detalleSicLocalId
            ? SolicitudSicBorrador::with(['empleado', 'ticket', 'tipoEquipo', 'articulo.categoria', 'centroCosto', 'solicitudProveedorLineas.solicitud'])->find($this->detalleSicLocalId)
            : null;

        return view('gestionti::livewire.compras.solicitudes-proveedor', $data);
    }
}
