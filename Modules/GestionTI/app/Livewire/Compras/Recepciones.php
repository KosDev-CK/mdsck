<?php

namespace Modules\GestionTI\Livewire\Compras;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\EbsArticulo;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\RecepcionLinea;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudProveedorLinea;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;
use Modules\GestionTI\Support\SharePoint\SharePointClient;
use Modules\GestionTI\Support\SharePoint\SharePointException;

/**
 * Recepción de Proveedor (sección 7.7 del spec original) — la pantalla que
 * realmente crea `Asset`s reales a partir de una `SolicitudProveedor`. Ver
 * docs/gestionti-progreso.md, Fase 3 etapa 3, para el diseño completo.
 *
 * No hay `edit()` a propósito: una recepción ya guardada da de alta Assets
 * reales (con `codigo` único ya asignado) — "editarla" implicaría deshacer
 * altas de inventario, algo que el spec original no describe y que se deja
 * fuera de alcance de esta pantalla. Una recepción posterior sobre la misma
 * `SolicitudProveedor` (embarque parcial) es el mecanismo soportado para
 * corregir/completar cantidades, no editar una recepción existente.
 */
#[Layout('layouts.app')]
class Recepciones extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $form = [];

    public ?int $selectedSolicitudId = null;

    /**
     * Una entrada por línea de la SolicitudProveedor seleccionada. Cada
     * entrada trae los datos de solo-lectura de la línea (descripción,
     * cantidades) más los campos capturables de esta recepción —
     * `cantidad_a_recibir` y, si la línea es inventariable y esa cantidad es
     * > 0, `marca_id`/`modelo_id`/fechas de garantía/`tipo_equipo_id`
     * (solo si el artículo no trae uno) + el arreglo `unidades` (1 fila por
     * unidad física, `numero_serie`/`service_tag`).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $lineas = [];

    /** Remisión digitalizada — propiedad de nivel superior, mismo convenio de WithFileUploads ya usado en SolicitudesSic::$adjunto. */
    public $documentoRemision;

    /**
     * Archivo ya existente en SharePoint elegido vía el modal "Buscar en
     * SharePoint" (Fase 5, punto 5) — alternativa a subir un archivo nuevo
     * con `$documentoRemision`. Mutuamente excluyente: elegir uno limpia el
     * otro (ver `elegirArchivoSharePoint()`).
     *
     * @var array{driveItemId: string, nombre: string, webUrl: string}|null
     */
    public ?array $documentoRemisionVinculado = null;

    public bool $showSharePointModal = false;

    public string $sharePointSearch = '';

    /** @var array<int, array{driveItemId: string, nombre: string, webUrl: string}> */
    public array $sharePointArchivos = [];

    /** 'documentoRemision' (modal de crear) | 'attachDocumentoRemision' (modal de adjuntar después de una recepción ya guardada). */
    public string $sharePointTarget = 'documentoRemision';

    public ?int $attachingId = null;

    public bool $showAttachModal = false;

    /** Remisión digitalizada — modal "Adjuntar remisión" sobre una recepción ya guardada (corrige un documento faltante/equivocado sin reabrir la recepción). */
    public $attachDocumentoRemision;

    /** @var array{driveItemId: string, nombre: string, webUrl: string}|null */
    public ?array $attachDocumentoRemisionVinculado = null;

    #[Url(as: 'search')]
    public string $search = '';

    /** Filtro del grid: '' = todas, 'pendientes' = solicitada + parcialmente recibida, o un estatus puntual de SolicitudProveedor. */
    #[Url(as: 'estatus')]
    public string $estatusFiltro = '';

    public bool $showModal = false;

    /**
     * Sitio de entrega que se recibe en esta recepción (una recepción cubre un
     * solo lugar de entrega). Un técnico con sitio asignado queda fijo en el
     * suyo; uno sin sitio asignado elige entre los de las líneas pendientes.
     */
    public ?int $lugarRecepcionId = null;

    /**
     * Línea (índice de `$lineas`) a la que se están escaneando números de
     * serie: se activa al escanear el código de la línea (impreso en el PDF
     * de la solicitud, `{folio}-L{n}`) y recibe cada código de artículo
     * escaneado después.
     */
    public ?int $lineaActiva = null;

    /** Línea (n de `{folio}-Ln`) escaneada antes de elegir al técnico: se activa en cuanto se elige. */
    public ?int $ordinalPendiente = null;

    /**
     * Ubicación de inventario donde quedan los equipos recibidos: una de las
     * que el lugar de entrega agrupa (Catálogos Núcleo → Ubicaciones). Si el
     * sitio solo tiene una, se toma sola.
     */
    public ?int $ubicacionDestinoId = null;

    /** Detalle de solo lectura de la SIC/requisición de una línea (mismos partials que "SIC en EBS"). */
    public bool $showDetalleModal = false;

    public ?int $detalleEbsRequisitionId = null;

    public ?int $detalleSicLocalId = null;

    /**
     * Solo Administrador: técnico (Validador) por el que se captura la
     * recepción cuando quien la recibió no pudo hacerlo. Queda como "Recibido
     * por"; el administrador queda como quien la registró.
     */
    public ?int $tecnicoRecibeId = null;

    /** @var array<int, ArticuloSolicitud|null> */
    private array $articulosCache = [];

    private ?Validador $validadorCache = null;

    private bool $validadorResuelto = false;

    /** Buscador de "Artículo recibido" (modal pequeño): línea que se está resolviendo y texto de búsqueda. */
    public bool $showArticuloModal = false;

    public ?int $articuloLineaIndex = null;

    /** Pieza de la línea cuyo artículo se está eligiendo (`null` = el artículo de toda la línea). */
    public ?int $articuloUnidadIndex = null;

    public string $articuloSearch = '';

    /** Resultados que muestra el buscador de artículos (el resto se alcanza buscando). */
    private const RESULTADOS_ARTICULOS = 10;

    protected function rules(): array
    {
        $fechaSolicitud = $this->selectedSolicitudId
            ? SolicitudProveedor::find($this->selectedSolicitudId)?->fecha_solicitud?->format('Y-m-d')
            : null;

        return [
            'selectedSolicitudId' => 'required|exists:solicitudes_proveedor,id',
            'form.folio_remision' => 'required|string|max:100',
            // La fecha real de llegada alimenta el reporte de entregas del
            // proveedor: ni futura, ni anterior a la fecha de la solicitud.
            'form.fecha_recepcion' => array_filter(['required', 'date', 'before_or_equal:today', $fechaSolicitud ? "after_or_equal:{$fechaSolicitud}" : null]),
            'form.observaciones' => 'nullable|string',
            'documentoRemision' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'lineas.*.cantidad_a_recibir' => 'required|integer|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'selectedSolicitudId.required' => 'Selecciona una solicitud.',
            'form.folio_remision.required' => 'Captura el folio de la remisión del proveedor.',
            'form.folio_remision.max' => 'El folio de remisión no puede pasar de 100 caracteres.',
            'form.fecha_recepcion.required' => 'Captura la fecha de recepción.',
            'form.fecha_recepcion.date' => 'La fecha de recepción no es válida.',
            'form.fecha_recepcion.before_or_equal' => 'La fecha de recepción no puede ser futura.',
            'form.fecha_recepcion.after_or_equal' => 'La fecha de recepción no puede ser anterior a la fecha de la solicitud.',
            'documentoRemision.mimes' => 'La remisión debe ser un PDF, JPG o PNG.',
            'documentoRemision.max' => 'La remisión no puede pesar más de 5 MB.',
            'lineas.*.cantidad_a_recibir.required' => 'Captura la cantidad a recibir.',
            'lineas.*.cantidad_a_recibir.integer' => 'La cantidad a recibir debe ser un número entero.',
            'lineas.*.cantidad_a_recibir.min' => 'La cantidad a recibir no puede ser negativa.',
        ];
    }

    /** Ubicaciones activas que agrupa el sitio que se está recibiendo. */
    private function ubicacionesDelLugar()
    {
        if (! $this->lugarRecepcionId) {
            return collect();
        }

        return Ubicacion::where('lugar_entrega_id', $this->lugarRecepcionId)->where('activo', true)->orderBy('nombre')->get();
    }

    private function resolverUbicacionPorDefecto(): void
    {
        $ubicaciones = $this->ubicacionesDelLugar();

        if ($ubicaciones->count() === 1) {
            $this->ubicacionDestinoId = (int) $ubicaciones->first()->id;

            return;
        }

        if ($this->ubicacionDestinoId !== null && ! $ubicaciones->contains('id', $this->ubicacionDestinoId)) {
            $this->ubicacionDestinoId = null;
        }
    }

    private function esAdministrador(): bool
    {
        return (bool) auth()->user()?->hasRole('Administrador');
    }

    /**
     * Técnico que recibe: quien tiene la sesión iniciada, validado contra el
     * catálogo de técnicos (Validador activo con `user_id` = usuario). Un
     * Administrador puede, además, capturar por otro técnico
     * (`$tecnicoRecibeId`). `null` si no hay técnico válido.
     */
    private function validadorActual(): ?Validador
    {
        if (! $this->validadorResuelto) {
            $this->validadorCache = Validador::with('lugarEntrega')
                ->where('activo', true)
                ->when(
                    $this->esAdministrador() && $this->tecnicoRecibeId,
                    fn ($q) => $q->whereKey($this->tecnicoRecibeId),
                    fn ($q) => $q->where('user_id', auth()->id()),
                )
                ->first();
            $this->validadorResuelto = true;
        }

        return $this->validadorCache;
    }

    /** ¿Puede este técnico recibir en ese lugar? (con sitio asignado solo el suyo; sin sitio, cualquiera). */
    private function puedeRecibirEnLugar(?Validador $validador, ?int $lugarId): bool
    {
        if ($validador === null || $lugarId === null) {
            return false;
        }

        return $validador->lugar_entrega_id === null || (int) $validador->lugar_entrega_id === $lugarId;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Escaneo del folio de la solicitud (lector de código de barras → texto +
     * Enter, o cámara del celular): si coincide EXACTO con un folio abre esa
     * solicitud; si no, avisa y deja el texto en el buscador por si era una
     * búsqueda parcial.
     */
    public function abrirPorCodigo(string $codigo): void
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return;
        }

        $solicitud = SolicitudProveedor::where('folio', $codigo)->first();

        if ($solicitud === null) {
            $this->search = $codigo;
            session()->flash('error', "No se encontró ninguna solicitud con el folio \"{$codigo}\".");

            return;
        }

        $this->search = '';
        $this->abrirSolicitud($solicitud->id);
    }

    /**
     * Punto único de entrada de un código escaneado (lector USB o cámara):
     *  - `{folio}-L{n}`  → abre la solicitud (si no lo está) y deja activa esa línea;
     *  - un folio exacto → abre esa solicitud;
     *  - con una línea activa → es el número de serie de un equipo de esa línea;
     *  - sin nada abierto → se trata como búsqueda por folio (con aviso si no existe).
     */
    public function escanear(string $codigo): void
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return;
        }

        if ($partes = SolicitudProveedor::parsearCodigoLinea($codigo)) {
            $solicitud = is_int($partes[0])
                ? SolicitudProveedor::find($partes[0])
                : SolicitudProveedor::where('folio', $partes[0])->first();

            if ($solicitud) {
                $this->activarLinea($solicitud, $partes[1]);

                return;
            }
        }

        $solicitudPorFolio = SolicitudProveedor::where('folio', $codigo)->first();

        if ($solicitudPorFolio) {
            $this->abrirSolicitud($solicitudPorFolio->id);
            $this->search = '';

            return;
        }

        if ($this->showModal && $this->selectedSolicitudId) {
            $this->capturarSerieEscaneada($codigo);

            return;
        }

        $this->abrirPorCodigo($codigo);
    }

    private function activarLinea(SolicitudProveedor $solicitud, int $ordinal): void
    {
        if (! $this->showModal || $this->selectedSolicitudId !== $solicitud->id) {
            $this->abrirSolicitud($solicitud->id);
            $this->search = '';
        }

        $this->ordinalPendiente = null;

        $indice = collect($this->lineas)->search(fn ($linea) => (int) ($linea['ordinal'] ?? 0) === $ordinal);

        if ($indice === false) {
            session()->flash('error', "La solicitud {$solicitud->folio} no tiene la línea {$ordinal}"
                .($this->admiteRecepcion($solicitud) ? '.' : ' disponible: ya no admite recepciones (estatus: '.$solicitud->estatus.').'));

            return;
        }

        $linea = $this->lineas[$indice];

        if ((int) $linea['cantidad_pendiente'] <= 0) {
            session()->flash('error', "La línea {$ordinal} ya se recibió completa.");

            return;
        }

        if (empty($linea['lugar_entrega_id'])) {
            session()->flash('error', "La línea {$ordinal} no tiene lugar de entrega: no se puede recibir hasta capturarlo en la solicitud.");

            return;
        }

        // Sin técnico no se sabe qué sitio puede recibir: se deja pendiente la
        // línea y se activa sola cuando el administrador elija al técnico.
        $validador = $this->validadorActual();

        if ($validador === null) {
            if ($this->esAdministrador()) {
                $this->ordinalPendiente = $ordinal;
                session()->flash('error', "Elige primero el técnico que recibió (campo \"Recibido por\"): al elegirlo se activa la línea {$ordinal}.");
            } else {
                session()->flash('error', 'Tu usuario no está dado de alta como técnico receptor: pide que lo vinculen en Catálogos de Inventario → Validador.');
            }

            return;
        }

        if (! $this->puedeRecibirEnLugar($validador, (int) $linea['lugar_entrega_id'])) {
            session()->flash('error', "La línea {$ordinal} se entrega en {$linea['lugar_nombre']} y tu sede es {$validador->lugarEntrega?->nombre}: la recibe el técnico de ese sitio.");

            return;
        }

        // Un técnico sin sede elige el sitio: escanear una línea ya lo define.
        if ($this->lugarRecepcionId === null) {
            $this->lugarRecepcionId = (int) $linea['lugar_entrega_id'];
            $this->aplicarLugarRecepcion();
            $this->resolverUbicacionPorDefecto();
        } elseif ($this->lugarRecepcionId !== (int) $linea['lugar_entrega_id']) {
            $actual = LugarEntrega::find($this->lugarRecepcionId)?->nombre;
            session()->flash('error', "Estás recibiendo el sitio {$actual} y la línea {$ordinal} se entrega en {$linea['lugar_nombre']}: se recibe en otra recepción (guarda esta o cambia el sitio).");

            return;
        }

        $linea = $this->lineas[$indice];

        if (! ($linea['recibible'] ?? false)) {
            session()->flash('error', "La línea {$ordinal} no se puede recibir en esta recepción.");

            return;
        }

        $this->lineaActiva = $indice;
        $this->dispatch('recepcion-scroll', id: "recepcion-linea-{$indice}");
        session()->flash('status', ! empty($linea['articulo_generico']) && empty($linea['es_activo_inventariable'])
            ? "Línea {$ordinal} activa: primero elige el artículo recibido (Buscar / cambiar) y luego escanea el número de serie de cada equipo."
            : "Línea {$ordinal} activa: escanea el número de serie de cada equipo que llegó.");
    }

    /** Número de serie escaneado → siguiente unidad vacía de la línea activa. */
    private function capturarSerieEscaneada(string $valor): void
    {
        $i = $this->lineaActiva;

        if ($i === null || ! isset($this->lineas[$i])) {
            session()->flash('error', 'Escanea primero el código de la línea (viene impreso en el PDF de la solicitud) y luego el número de serie de cada equipo.');

            return;
        }

        $linea = $this->lineas[$i];

        if (empty($linea['usa_unidades'])) {
            session()->flash('error', 'Esta línea no pide número de serie.');

            return;
        }

        $serie = $valor;
        $normalizada = mb_strtoupper($serie);

        foreach ($this->lineas as $otra) {
            foreach ($otra['unidades'] ?? [] as $unidad) {
                if (mb_strtoupper(trim((string) ($unidad['numero_serie'] ?? ''))) === $normalizada) {
                    session()->flash('error', "El número de serie {$serie} ya se escaneó en esta recepción.");

                    return;
                }
            }
        }

        // Siguiente pieza sin serie cuyo artículo se da de alta como Activo.
        $buscarDestino = fn () => collect($this->lineas[$i]['unidades'] ?? [])->search(
            fn ($unidad) => trim((string) ($unidad['numero_serie'] ?? '')) === ''
                && $this->unidadEsInventariable($this->lineas[$i], $this->articuloDeUnidad($this->lineas[$i], $unidad))
        );

        $destino = $buscarDestino();

        if ($destino === false) {
            // Piezas ya agregadas, sin serie, pero sin artículo real elegido.
            $sinArticulo = collect($linea['unidades'] ?? [])->search(
                fn ($unidad) => trim((string) ($unidad['numero_serie'] ?? '')) === ''
            );

            if ($sinArticulo === false) {
                // Cada número de serie escaneado es una pieza que llegó en esta
                // remisión: se suma mientras quede pendiente.
                if ((int) $linea['cantidad_a_recibir'] >= (int) $linea['cantidad_pendiente']) {
                    session()->flash('status', 'Esa línea ya no tiene piezas pendientes: escanea el código de otra línea.');

                    return;
                }

                $this->lineas[$i]['cantidad_a_recibir'] = (int) $linea['cantidad_a_recibir'] + 1;
                $this->ajustarUnidades($i);
                $destino = $buscarDestino();
            }

            if ($destino === false) {
                session()->flash('error', ! empty($linea['articulo_generico'])
                    ? 'Primero elige el artículo recibido (de la línea o de cada pieza): con el artículo real se piden los números de serie.'
                    : 'Esta pieza no es inventariable: no pide número de serie.');

                return;
            }
        }

        $this->lineas[$i]['unidades'][$destino]['numero_serie'] = $serie;

        if (Asset::where('numero_serie', $serie)->exists()) {
            session()->flash('error', "Atención: ya existe un activo con el número de serie {$serie} en el inventario. Se capturó, pero revísalo antes de guardar.");

            return;
        }

        $this->dispatch('recepcion-scroll', id: "recepcion-linea-{$i}");
    }

    public function updatingEstatusFiltro(): void
    {
        $this->resetPage();
    }

    /** Una solicitud solo admite recepciones nuevas mientras está pendiente de recibir completa. */
    private function admiteRecepcion(?SolicitudProveedor $solicitud): bool
    {
        return $solicitud !== null && in_array($solicitud->estatus, [
            SolicitudProveedor::ESTATUS_SOLICITADA,
            SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA,
        ], true);
    }

    /**
     * Catch-all de Livewire para reaccionar a cambios de propiedades
     * anidadas en arreglos (`lineas.N.cantidad_a_recibir`) — los hooks
     * mágicos `updated{Propiedad}()` de Livewire solo existen para
     * propiedades públicas de primer nivel, no para índices de arreglo.
     */
    public function updated($name, $value): void
    {
        if ($name === 'tecnicoRecibeId') {
            $this->tecnicoRecibeId = $this->esAdministrador() && $value !== '' && $value !== null ? (int) $value : null;
            $this->validadorResuelto = false;
            $this->resolverLugarPorDefecto();
            $this->aplicarLugarRecepcion();
            $this->resolverUbicacionPorDefecto();

            if ($this->ordinalPendiente !== null && $this->selectedSolicitudId) {
                $solicitud = SolicitudProveedor::find($this->selectedSolicitudId);

                if ($solicitud) {
                    $this->activarLinea($solicitud, $this->ordinalPendiente);
                }
            }

            return;
        }

        if ($name === 'ubicacionDestinoId') {
            $this->ubicacionDestinoId = $value === '' || $value === null ? null : (int) $value;

            return;
        }

        if ($name === 'lugarRecepcionId') {
            $this->lugarRecepcionId = $value === '' || $value === null ? null : (int) $value;

            // Un técnico con sitio asignado no puede cambiarlo.
            if (! $this->puedeRecibirEnLugar($this->validadorActual(), $this->lugarRecepcionId)) {
                $this->lugarRecepcionId = $this->validadorActual()?->lugar_entrega_id;
            }

            $this->aplicarLugarRecepcion();
            $this->resolverUbicacionPorDefecto();

            return;
        }

        if (preg_match('/^lineas\.(\d+)\.cantidad_a_recibir$/', $name, $m)) {
            $this->clampAndResizeLinea((int) $m[1]);
        }

        if (preg_match('/^lineas\.(\d+)\.articulo_id$/', $name, $m)) {
            $this->recalcArticuloDerivedFields((int) $m[1]);
        }
    }

    /**
     * Recalcula los campos de solo-lectura derivados del artículo elegido en
     * una línea — se dispara cuando el usuario cambia el "Artículo recibido"
     * de la línea (puede diferir del que traía la Solicitud a Proveedor, ver
     * docs/gestionti-progreso.md, entrada "Catálogo unificado de
     * Artículos"). Vacío si no hay artículo elegido — en ese caso los
     * selects manuales de marca/modelo/tipo de equipo vuelven a ser el
     * fallback, igual que cuando el artículo original no traía esos datos.
     */
    private function recalcArticuloDerivedFields(int $index): void
    {
        if (! isset($this->lineas[$index])) {
            return;
        }

        $articulo = ! empty($this->lineas[$index]['articulo_id'])
            ? ArticuloSolicitud::find($this->lineas[$index]['articulo_id'])
            : null;

        // Lo inventariable lo define el artículo REAL recibido (el de la
        // solicitud suele ser un genérico no inventariable). Sin artículo
        // elegido se vuelve a lo que traía la línea.
        $this->lineas[$index]['es_activo_inventariable'] = $articulo !== null
            ? (bool) $articulo->es_inventariable
            : (bool) ($this->lineas[$index]['es_activo_inventariable_original'] ?? false);

        // Elegir el artículo de la línea lo aplica a todas sus piezas (cada una
        // se puede cambiar después). Hay piezas individuales si la línea es
        // inventariable o se pidió con un artículo genérico.
        foreach ($this->lineas[$index]['unidades'] ?? [] as $u => $unidad) {
            $this->lineas[$index]['unidades'][$u]['articulo_id'] = null;
        }
        $this->lineas[$index]['usa_unidades'] = (bool) ($this->lineas[$index]['usa_unidades_base'] ?? false)
            || $this->lineas[$index]['es_activo_inventariable'];
        $this->ajustarUnidades($index);

        $this->lineas[$index]['articulo_tipo_equipo_id'] = $articulo?->tipo_equipo_id;
        $this->lineas[$index]['articulo_marca_id'] = $articulo?->marca_id;
        $this->lineas[$index]['articulo_modelo_id'] = $articulo?->modelo_id;
        $this->lineas[$index]['articulo_procesador'] = $articulo?->procesador?->nombre;
        $this->lineas[$index]['articulo_ram'] = $articulo?->ram?->nombre;
        $this->lineas[$index]['articulo_almacenamiento'] = $articulo?->almacenamiento?->nombre;
    }

    private function articuloConSpecs(int $id): ?ArticuloSolicitud
    {
        if (! array_key_exists($id, $this->articulosCache)) {
            $this->articulosCache[$id] = ArticuloSolicitud::with(['procesador', 'ram', 'almacenamiento'])->find($id);
        }

        return $this->articulosCache[$id];
    }

    /**
     * Artículo REAL de una pieza: el que se eligió para ella o, si no, el de la
     * línea. Un artículo genérico (el estándar con el que se pidió) nunca
     * cuenta como real: hay que elegir uno.
     */
    private function articuloDeUnidad(array $linea, array $unidad): ?ArticuloSolicitud
    {
        $id = ! empty($unidad['articulo_id']) ? (int) $unidad['articulo_id'] : (int) ($linea['articulo_id'] ?? 0);

        if ($id === 0) {
            return null;
        }

        $articulo = $this->articuloConSpecs($id);

        return $this->esArticuloGenerico($articulo) ? null : $articulo;
    }

    /** ¿Esta pieza se da de alta como Activo? Lo decide su artículo; sin artículo, la bandera original de la línea. */
    private function unidadEsInventariable(array $linea, ?ArticuloSolicitud $articulo): bool
    {
        $banderaOriginal = (bool) ($linea['es_activo_inventariable_original'] ?? false);

        if ($articulo === null) {
            return $banderaOriginal && empty($linea['articulo_generico']);
        }

        // Si se sustituyó el artículo, manda el del artículo recibido; si es el
        // mismo con el que se pidió, también cuenta la bandera guardada en la línea.
        $sustituido = (int) $articulo->id !== (int) ($linea['articulo_solicitado_id'] ?? 0);

        return (bool) $articulo->es_inventariable || (! $sustituido && $banderaOriginal);
    }

    /** Artículo con el que se pidió la línea, leído de la BD (no del estado del cliente). */
    private function articuloSolicitadoDeLinea(int $index): ?ArticuloSolicitud
    {
        $lineaId = $this->lineas[$index]['solicitud_proveedor_linea_id'] ?? null;

        return $lineaId ? SolicitudProveedorLinea::with('articulo')->find($lineaId)?->articulo : null;
    }

    /**
     * Artículos que se pueden elegir como "recibido" en una línea: activos, sin
     * los genéricos (esos son solo para pedir), y del MISMO tipo de equipo que
     * el artículo solicitado (si se pidió una laptop, solo laptops; si una PC,
     * solo PCs). Si el solicitado no tiene tipo de equipo se acota por su
     * categoría, y si tampoco la tiene no se acota.
     */
    private function consultaArticulosPermitidos(int $index)
    {
        $genericos = $this->idsArticulosGenericos();
        $solicitado = $this->articuloSolicitadoDeLinea($index);

        return ArticuloSolicitud::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNotIn('id', $genericos)->orWhere('es_inventariable', true))
            ->when($solicitado?->tipo_equipo_id, fn ($q, $tipo) => $q->where('tipo_equipo_id', $tipo))
            ->when(! $solicitado?->tipo_equipo_id && $solicitado?->categoria_id, fn ($q) => $q->where('categoria_id', $solicitado->categoria_id));
    }

    private function articuloPermitido(int $index, int $articuloId): bool
    {
        $solicitado = $this->articuloSolicitadoDeLinea($index);

        if ($solicitado !== null && $solicitado->id === $articuloId && ! $this->esArticuloGenerico($solicitado)) {
            return true;
        }

        return $this->consultaArticulosPermitidos($index)->whereKey($articuloId)->exists();
    }

    public function abrirBuscadorArticulo(int $index, ?int $unidad = null): void
    {
        if (! isset($this->lineas[$index]) || ($unidad !== null && ! isset($this->lineas[$index]['unidades'][$unidad]))) {
            return;
        }

        $this->articuloLineaIndex = $index;
        $this->articuloUnidadIndex = $unidad;
        $this->articuloSearch = '';
        $this->showArticuloModal = true;
    }

    public function cerrarBuscadorArticulo(): void
    {
        $this->showArticuloModal = false;
        $this->articuloLineaIndex = null;
        $this->articuloUnidadIndex = null;
        $this->articuloSearch = '';
    }

    public function elegirArticulo(int $articuloId): void
    {
        $index = $this->articuloLineaIndex;

        if ($index === null || ! isset($this->lineas[$index]) || ! $this->articuloPermitido($index, $articuloId)) {
            return;
        }

        if ($this->articuloUnidadIndex !== null) {
            if (isset($this->lineas[$index]['unidades'][$this->articuloUnidadIndex])) {
                $this->lineas[$index]['unidades'][$this->articuloUnidadIndex]['articulo_id'] = $articuloId;
            }
        } else {
            $this->lineas[$index]['articulo_id'] = $articuloId;
            $this->recalcArticuloDerivedFields($index);
        }

        $this->cerrarBuscadorArticulo();
    }

    /** "Sin artículo": solo para líneas que no se pidieron con un artículo genérico. */
    public function quitarArticulo(): void
    {
        $index = $this->articuloLineaIndex;

        if ($index === null || ! isset($this->lineas[$index]) || ! empty($this->lineas[$index]['articulo_generico'])) {
            return;
        }

        if ($this->articuloUnidadIndex !== null) {
            if (isset($this->lineas[$index]['unidades'][$this->articuloUnidadIndex])) {
                $this->lineas[$index]['unidades'][$this->articuloUnidadIndex]['articulo_id'] = null;
            }
        } else {
            $this->lineas[$index]['articulo_id'] = null;
            $this->recalcArticuloDerivedFields($index);
        }

        $this->cerrarBuscadorArticulo();
    }

    /**
     * Deja el arreglo `unidades` de la línea con una fila por unidad a recibir
     * si es inventariable, o vacío si no lo es.
     */
    private function ajustarUnidades(int $index): void
    {
        if (empty($this->lineas[$index]['usa_unidades'])) {
            $this->lineas[$index]['unidades'] = [];

            return;
        }

        $cantidad = max(0, (int) ($this->lineas[$index]['cantidad_a_recibir'] ?? 0));
        $unidades = $this->lineas[$index]['unidades'] ?? [];

        while (count($unidades) < $cantidad) {
            $unidades[] = ['numero_serie' => '', 'service_tag' => '', 'articulo_id' => null];
        }

        $this->lineas[$index]['unidades'] = array_slice($unidades, 0, $cantidad);
    }

    private function clampAndResizeLinea(int $index): void
    {
        if (! isset($this->lineas[$index])) {
            return;
        }

        $pendiente = (int) $this->lineas[$index]['cantidad_pendiente'];
        $cantidad = (int) ($this->lineas[$index]['cantidad_a_recibir'] ?? 0);
        $cantidad = max(0, min($cantidad, $pendiente));
        $this->lineas[$index]['cantidad_a_recibir'] = $cantidad;

        $this->ajustarUnidades($index);
    }

    /**
     * Abre la solicitud elegida en el grid: muestra su historial de
     * recepciones y, si todavía admite recepciones (solicitada o
     * parcialmente recibida), el formulario para registrar la siguiente.
     */
    public function abrirSolicitud(int $id): void
    {
        $solicitud = SolicitudProveedor::findOrFail($id);

        $this->form = [
            'folio_remision' => '',
            'fecha_recepcion' => now()->format('Y-m-d'),
            'observaciones' => null,
        ];
        $this->selectedSolicitudId = $solicitud->id;
        $this->lineas = [];
        $this->lugarRecepcionId = null;
        $this->ubicacionDestinoId = null;
        $this->lineaActiva = null;
        $this->ordinalPendiente = null;
        // Administrador: arranca como él mismo si es técnico; si no, debe elegir al técnico.
        $this->tecnicoRecibeId = $this->esAdministrador()
            ? Validador::where('activo', true)->where('user_id', auth()->id())->value('id')
            : null;
        $this->validadorResuelto = false;
        $this->documentoRemision = null;
        $this->documentoRemisionVinculado = null;
        $this->resetValidation();

        if ($this->admiteRecepcion($solicitud)) {
            $this->loadLineas();
            $this->resolverLugarPorDefecto();
            $this->aplicarLugarRecepcion();
            $this->resolverUbicacionPorDefecto();
        }

        $this->showModal = true;
        $this->dispatch('recepcion-enfocar-escaner');
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->form = [];
        $this->selectedSolicitudId = null;
        $this->lineas = [];
        $this->lugarRecepcionId = null;
        $this->ubicacionDestinoId = null;
        $this->lineaActiva = null;
        $this->ordinalPendiente = null;
        $this->tecnicoRecibeId = null;
        $this->validadorResuelto = false;
        $this->documentoRemision = null;
        $this->documentoRemisionVinculado = null;
        $this->resetValidation();
    }

    /**
     * Sitio que se recibe por defecto: el del técnico si tiene uno asignado; si
     * no, el único lugar de entrega con líneas pendientes (si hay varios, lo
     * elige él).
     */
    private function resolverLugarPorDefecto(): void
    {
        $validador = $this->validadorActual();

        if ($validador?->lugar_entrega_id) {
            $this->lugarRecepcionId = (int) $validador->lugar_entrega_id;

            return;
        }

        $lugares = collect($this->lineas)
            ->filter(fn ($linea) => (int) $linea['cantidad_pendiente'] > 0 && ! empty($linea['lugar_entrega_id']))
            ->pluck('lugar_entrega_id')
            ->unique()
            ->values();

        $this->lugarRecepcionId = $lugares->count() === 1 ? (int) $lugares->first() : null;
    }

    /**
     * Marca qué líneas se pueden recibir ahora (las del sitio elegido, con
     * pendiente y que el técnico puede recibir) y deja el resto en 0: la
     * cantidad a recibir de una línea de otro sitio nunca es editable.
     */
    private function aplicarLugarRecepcion(): void
    {
        $validador = $this->validadorActual();

        foreach ($this->lineas as $i => $linea) {
            $recibible = (int) $linea['cantidad_pendiente'] > 0
                && ! empty($linea['lugar_entrega_id'])
                && $this->lugarRecepcionId !== null
                && (int) $linea['lugar_entrega_id'] === $this->lugarRecepcionId
                && $this->puedeRecibirEnLugar($validador, $this->lugarRecepcionId);

            if (! $recibible && $this->lineaActiva === $i) {
                $this->lineaActiva = null;
            }

            $this->lineas[$i]['recibible'] = $recibible;
            $this->lineas[$i]['cantidad_a_recibir'] = 0;
            $this->ajustarUnidades($i);
        }
    }

    /**
     * Atajo para cuando la remisión trae todo lo pendiente del sitio: pone en
     * cada línea recibible su cantidad pendiente completa.
     */
    public function recibirTodoPendiente(): void
    {
        foreach ($this->lineas as $i => $linea) {
            if ($linea['recibible'] ?? false) {
                $this->lineas[$i]['cantidad_a_recibir'] = (int) $linea['cantidad_pendiente'];
                $this->ajustarUnidades($i);
            }
        }
    }

    /**
     * Detalle de solo lectura de la SIC o requisición de EBS de una línea —
     * mismos partials que "SIC en EBS" / "Solicitud a Proveedores".
     */
    public function openSicDetalle(int $sicId = 0, int $ebsRequisitionId = 0): void
    {
        $sicId = $sicId ?: null;
        $ebsRequisitionId = $ebsRequisitionId ?: null;

        $ebsResuelta = $ebsRequisitionId;

        if (! $ebsResuelta && $sicId) {
            $ebsResuelta = SolicitudSicBorrador::find($sicId)?->ebs_requisition_id;
        }

        if ($ebsResuelta) {
            $this->detalleEbsRequisitionId = $ebsResuelta;
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
     * Abre el modal "Buscar en SharePoint" (Fase 5, punto 5) para vincular un
     * archivo ya existente en la carpeta de "remisión de proveedor" — sin
     * subir nada. Misma idea que `Asignaciones::openSharePointBuscar()`.
     * `$target` indica a qué propiedad de "vinculado" va el archivo elegido:
     * 'documentoRemision' desde el modal de crear, o 'attachDocumentoRemision'
     * desde el modal de adjuntar después. Excluye los archivos que ya
     * quedaron vinculados a otro registro.
     */
    public function openSharePointBuscar(string $target = 'documentoRemision'): void
    {
        $this->sharePointTarget = $target;
        $this->sharePointSearch = '';
        $this->resetValidation('sharePointArchivos');

        try {
            $vinculados = DocumentoDigitalizado::driveItemIdsVinculados('remision_proveedor');
            $this->sharePointArchivos = collect(app(SharePointClient::class)->listarArchivosParaTipo('remision_proveedor'))
                ->reject(fn (array $archivo) => in_array($archivo['driveItemId'], $vinculados, true))
                ->values()
                ->all();
        } catch (SharePointException $e) {
            $this->sharePointArchivos = [];
            $this->addError('sharePointArchivos', 'No se pudo conectar con SharePoint: '.$e->getMessage());
        }

        $this->showSharePointModal = true;
    }

    public function elegirArchivoSharePoint(string $driveItemId): void
    {
        $archivo = collect($this->sharePointArchivos)->firstWhere('driveItemId', $driveItemId);

        if (! $archivo) {
            return;
        }

        if ($this->sharePointTarget === 'attachDocumentoRemision') {
            $this->attachDocumentoRemisionVinculado = $archivo;
            $this->attachDocumentoRemision = null;
        } else {
            $this->documentoRemisionVinculado = $archivo;
            $this->documentoRemision = null;
        }

        $this->cancelSharePointBuscar();
    }

    public function cancelSharePointBuscar(): void
    {
        $this->showSharePointModal = false;
        $this->sharePointSearch = '';
        $this->sharePointArchivos = [];
    }

    /**
     * Quita la remisión vinculada a una recepción ya guardada (el técnico
     * subió/eligió el archivo equivocado) — borra el `DocumentoDigitalizado`
     * (nunca el archivo real, ver `DocumentoDigitalizado::quitar()`) y limpia
     * la FK. Única mutación permitida sobre una recepción ya guardada — todo
     * lo demás (cantidades, folio, fechas) sigue siendo inmutable a
     * propósito, ver el comentario de clase.
     */
    public function quitarRemision(int $id): void
    {
        $recepcion = Recepcion::findOrFail($id);

        if ($recepcion->documento_remision_id === null) {
            return;
        }

        DocumentoDigitalizado::quitar($recepcion->documento_remision_id);
        $recepcion->update(['documento_remision_id' => null]);

        session()->flash('status', 'Remisión desvinculada — puedes adjuntar la correcta desde el listado.');
    }

    public function openAttach(int $id): void
    {
        $recepcion = Recepcion::findOrFail($id);

        if ($recepcion->documento_remision_id !== null) {
            return;
        }

        $this->attachingId = $id;
        $this->attachDocumentoRemision = null;
        $this->attachDocumentoRemisionVinculado = null;
        $this->resetValidation();
        $this->showAttachModal = true;
    }

    public function confirmAttach(): void
    {
        if (! $this->attachDocumentoRemision && ! $this->attachDocumentoRemisionVinculado) {
            $this->addError('attachDocumentoRemision', 'Sube un archivo o vincula uno existente de SharePoint.');

            return;
        }

        if ($this->attachDocumentoRemision) {
            $this->validate(['attachDocumentoRemision' => 'file|mimes:pdf,jpg,jpeg,png|max:5120']);
        }

        $recepcion = Recepcion::findOrFail($this->attachingId);

        if ($recepcion->documento_remision_id !== null) {
            $this->cancelAttach();

            return;
        }

        $documento = $this->attachDocumentoRemision
            ? DocumentoDigitalizado::storeUploaded($this->attachDocumentoRemision, $recepcion, 'remision_proveedor', auth()->id())
            : DocumentoDigitalizado::linkExisting($this->attachDocumentoRemisionVinculado, $recepcion, 'remision_proveedor', auth()->id());

        $recepcion->update(['documento_remision_id' => $documento->id]);

        $this->cancelAttach();
        session()->flash('status', 'Remisión adjuntada correctamente.');
    }

    public function cancelAttach(): void
    {
        $this->showAttachModal = false;
        $this->attachingId = null;
        $this->attachDocumentoRemision = null;
        $this->attachDocumentoRemisionVinculado = null;
        $this->resetValidation();
    }

    /**
     * Reconstruye `$lineas` a partir de la SolicitudProveedor seleccionada.
     * `cantidad_recibida` de `SolicitudProveedorLinea` es la fuente de
     * verdad de "ya recibido" (columna real, ver nota de diseño en
     * docs/gestionti-progreso.md) — no se computa un SUM en vivo sobre
     * `recepcion_lineas` aparte.
     */
    private function loadLineas(): void
    {
        $this->lineas = [];

        if (! $this->selectedSolicitudId) {
            return;
        }

        $solicitud = SolicitudProveedor::with([
            'lineas.articulo.procesador',
            'lineas.articulo.ram',
            'lineas.articulo.almacenamiento',
            'lineas.sic',
            'lineas.ebsRequisition',
            'lineas.lugarEntrega',
        ])->find($this->selectedSolicitudId);

        if (! $solicitud) {
            return;
        }

        foreach ($solicitud->lineas->sortBy('id')->values() as $posicion => $linea) {
            $pendiente = max(0, $linea->cantidad_solicitada - $linea->cantidad_recibida);
            $articulo = $linea->articulo;

            $lineaForm = [
                'solicitud_proveedor_linea_id' => $linea->id,
                // Posición de la línea en la solicitud (1, 2, 3...): es la n del código `{folio}-L{n}` impreso en el PDF.
                'ordinal' => $posicion + 1,
                // De cabecera a línea (rediseño de "Solicitud a
                // Proveedores": de 1 a N SICs) — cada línea puede traer su
                // propia SIC, usada abajo para decidir "reservado" vs.
                // "en_stock" y `sic_reservada_id` por línea, no por
                // solicitud completa.
                'sic_id' => $linea->sic_id,
                'sic_folio' => $linea->sic?->folio_sic,
                'ebs_requisition_id' => $linea->ebs_requisition_id,
                // Folio a mostrar junto al artículo (SIC local, requisición de EBS directa o folio capturado a mano).
                'sic_display' => $linea->folioSicDisplay(),
                'lugar_entrega_id' => $linea->lugar_entrega_id,
                'lugar_nombre' => $linea->lugarEntrega?->nombre,
                'recibible' => false,
                'descripcion' => $articulo?->descripcion ?? $linea->descripcion_libre,
                'cantidad_solicitada' => $linea->cantidad_solicitada,
                'cantidad_ya_recibida' => $linea->cantidad_recibida,
                'cantidad_pendiente' => $pendiente,
                // Una solicitud se entrega en varias remisiones y no se sabe qué
                // llega en cada una: arranca en 0 y se captura (o se escanea)
                // solo lo que realmente llegó en ESTA remisión.
                'cantidad_a_recibir' => 0,
                // El atributo del artículo manda: la bandera guardada en la línea
                // es una foto del momento de la solicitud, y si el artículo se
                // marcó inventariable después, la recepción debe pedir serie,
                // marca, etc. (la bandera de la línea se respeta si ya era sí).
                'es_activo_inventariable' => (bool) $linea->es_activo_inventariable || (bool) $articulo?->es_inventariable,
                // "Artículo recibido" — precargado del que traía la
                // Solicitud a Proveedor, pero editable: lo realmente
                // recibido puede diferir de lo solicitado (sustitución del
                // proveedor, etc.). Los `articulo_*` de abajo son los
                // derivados de solo-lectura de ESTE valor, recalculados por
                // `recalcArticuloDerivedFields()` si el usuario lo cambia.
                'articulo_id' => $linea->articulo_id,
                'articulo_solicitado_id' => $linea->articulo_id,
                'es_activo_inventariable_original' => (bool) $linea->es_activo_inventariable,
                // Piezas individuales (una fila por pieza, cada una con su artículo
                // y su número de serie): líneas inventariables y las pedidas con un
                // artículo genérico, donde cada pieza puede llegar distinta.
                'usa_unidades_base' => (bool) $linea->es_activo_inventariable || (bool) $articulo?->es_inventariable || $this->esArticuloGenerico($articulo),
                'usa_unidades' => (bool) $linea->es_activo_inventariable || (bool) $articulo?->es_inventariable || $this->esArticuloGenerico($articulo),
                // Artículo genérico (estándar mapeado desde EBS y no inventariable):
                // en la recepción es obligatorio elegir el artículo real.
                'articulo_generico' => $this->esArticuloGenerico($articulo),
                'articulo_tipo_equipo_id' => $articulo?->tipo_equipo_id,
                'articulo_marca_id' => $articulo?->marca_id,
                'articulo_modelo_id' => $articulo?->modelo_id,
                'articulo_procesador' => $articulo?->procesador?->nombre,
                'articulo_ram' => $articulo?->ram?->nombre,
                'articulo_almacenamiento' => $articulo?->almacenamiento?->nombre,
                'tipo_equipo_id' => null,
                'marca_id' => null,
                'modelo_id' => null,
                'fecha_inicio_garantia' => null,
                'fecha_fin_garantia' => null,
                'unidades' => [],
            ];

            $this->lineas[] = $lineaForm;
        }
    }

    /**
     * Validaciones que dependen de más de un campo de la misma línea (no
     * expresables con reglas planas de `rules()`) — mismo patrón que
     * `SolicitudesProveedor::validateLineas()`.
     */
    private function validateLineas(): void
    {
        $totalARecibir = 0;

        $validador = $this->validadorActual();
        $lugar = $this->lugarRecepcionId ? LugarEntrega::find($this->lugarRecepcionId) : null;

        if ($validador === null) {
            $this->addError('recibido_por', $this->esAdministrador()
                ? 'Elige el técnico que recibió.'
                : 'Tu usuario no está dado de alta como técnico receptor: pide que lo vinculen en Catálogos de Inventario → Validador.');
        } elseif ($lugar === null) {
            $this->addError('lugarRecepcionId', 'Elige el sitio de entrega que estás recibiendo.');
        } elseif (! $this->puedeRecibirEnLugar($validador, $lugar->id)) {
            $this->addError('lugarRecepcionId', "No tienes asignado el sitio {$lugar->nombre}: solo puedes recibir en {$validador->lugarEntrega?->nombre}.");
        } elseif ($this->ubicacionesDelLugar()->isEmpty()) {
            $this->addError('lugarRecepcionId', "El sitio {$lugar->nombre} no tiene ubicaciones de inventario asignadas (Catálogos Núcleo → Ubicaciones, campo \"Lugar de entrega (Compras)\").");
        } elseif ($this->ubicacionDestinoId === null) {
            $this->addError('ubicacionDestinoId', 'Elige la ubicación donde quedan los equipos.');
        } elseif (! $this->ubicacionesDelLugar()->contains('id', $this->ubicacionDestinoId)) {
            $this->addError('ubicacionDestinoId', "Esa ubicación no pertenece al sitio {$lugar->nombre}.");
        }

        // Lugar real de cada línea, de la BD (no del cliente).
        $lugarPorLinea = SolicitudProveedorLinea::whereIn('id', collect($this->lineas)->pluck('solicitud_proveedor_linea_id'))
            ->pluck('lugar_entrega_id', 'id');

        foreach ($this->lineas as $i => $linea) {
            $cantidad = (int) ($linea['cantidad_a_recibir'] ?? 0);
            $pendiente = (int) ($linea['cantidad_pendiente'] ?? 0);

            if ($cantidad > $pendiente) {
                $this->addError("lineas.$i.cantidad_a_recibir", "No puede recibir más de lo pendiente ({$pendiente}).");

                continue;
            }

            if ($cantidad <= 0) {
                continue;
            }

            $totalARecibir += $cantidad;

            if ($lugar !== null && (int) ($lugarPorLinea[$linea['solicitud_proveedor_linea_id']] ?? 0) !== $lugar->id) {
                $this->addError("lineas.$i.cantidad_a_recibir", 'Esta línea se entrega en otro sitio: la recibe el técnico de ese sitio.');
            }

            // El artículo de la línea es un genérico (nadie lo cambió): no hay nada
            // que validar a ese nivel, cada pieza elige el suyo.
            if (! empty($linea['articulo_id'])
                && ! $this->esArticuloGenerico($this->articuloConSpecs((int) $linea['articulo_id']))
                && ! $this->articuloPermitido($i, (int) $linea['articulo_id'])) {
                $this->addError("lineas.$i.articulo_id", 'El artículo elegido no corresponde al tipo de equipo solicitado.');
            }

            if (empty($linea['usa_unidades'])) {
                continue;
            }

            $unidades = $linea['unidades'] ?? [];

            if (count($unidades) !== $cantidad) {
                $this->addError("lineas.$i.cantidad_a_recibir", 'El número de piezas capturadas no coincide con la cantidad a recibir.');
            }

            $faltaMarca = false;
            $faltaTipo = false;

            foreach ($unidades as $u => $unidad) {
                $articulo = $this->articuloDeUnidad($linea, $unidad);

                // Pedida con un artículo genérico: cada pieza necesita su artículo real.
                if ($articulo === null && ! empty($linea['articulo_generico'])) {
                    $this->addError("lineas.$i.unidades.$u.articulo_id", 'Elige el artículo real de esta pieza.');
                    $this->addError("lineas.$i.articulo_id", 'Elige el artículo real que llegó: la solicitud se hizo con un artículo genérico.');

                    continue;
                }

                if ($articulo !== null && ! empty($unidad['articulo_id']) && ! $this->articuloPermitido($i, (int) $unidad['articulo_id'])) {
                    $this->addError("lineas.$i.unidades.$u.articulo_id", 'El artículo elegido no corresponde al tipo de equipo solicitado.');
                }

                if (! $this->unidadEsInventariable($linea, $articulo)) {
                    continue;
                }

                $faltaMarca = $faltaMarca || empty($articulo?->marca_id);
                $faltaTipo = $faltaTipo || empty($articulo?->tipo_equipo_id);

                if (trim((string) ($unidad['numero_serie'] ?? '')) === '') {
                    $this->addError("lineas.$i.unidades.$u.numero_serie", 'El número de serie es requerido.');
                }
            }

            if ($faltaMarca && empty($linea['marca_id'])) {
                $this->addError("lineas.$i.marca_id", 'La marca es requerida para un activo inventariable — no se pudo determinar automáticamente del artículo.');
            }

            if ($faltaTipo && empty($linea['tipo_equipo_id'])) {
                $this->addError("lineas.$i.tipo_equipo_id", 'Selecciona el tipo de equipo — no se pudo determinar automáticamente del artículo.');
            }
        }

        // Un mismo número de serie no puede capturarse dos veces en la recepción
        // (típico al escanear dos veces la misma caja).
        $vistos = [];
        foreach ($this->lineas as $i => $linea) {
            if ((int) ($linea['cantidad_a_recibir'] ?? 0) <= 0 || empty($linea['usa_unidades'])) {
                continue;
            }

            foreach ($linea['unidades'] ?? [] as $u => $unidad) {
                $serie = mb_strtoupper(trim((string) ($unidad['numero_serie'] ?? '')));

                if ($serie === '') {
                    continue;
                }

                if (isset($vistos[$serie])) {
                    $this->addError("lineas.$i.unidades.$u.numero_serie", 'Este número de serie ya se capturó en otra unidad de esta recepción.');
                }

                $vistos[$serie] = true;
            }
        }

        if (empty($this->lineas)) {
            $this->addError('lineas', 'Selecciona una solicitud a proveedor con líneas.');
        } elseif ($totalARecibir === 0) {
            $this->addError('lineas', 'Captura una cantidad mayor a 0 en al menos una línea.');
        }
    }

    /** Artículos estándar a los que se mapean los ítems de EBS (`EbsArticulo`). */
    private function idsArticulosGenericos(): array
    {
        return EbsArticulo::whereNotNull('articulo_id')->pluck('articulo_id')->all();
    }

    private function esArticuloGenerico(?ArticuloSolicitud $articulo): bool
    {
        return $articulo !== null
            && ! $articulo->es_inventariable
            && in_array($articulo->id, $this->idsArticulosGenericos(), true);
    }

    private function estatusIdPorCodigo(string $codigo): int
    {
        $id = EstatusActivo::where('codigo', $codigo)->value('id');

        if ($id === null) {
            throw new \RuntimeException("Falta el estatus base '{$codigo}' en estatus_activo — corre primero `php artisan module:seed GestionTI`.");
        }

        return $id;
    }

    public function save(): void
    {
        $this->validate($this->rules());
        $this->validateLineas();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $solicitud = SolicitudProveedor::with('lineas')->findOrFail($this->selectedSolicitudId);

        if (! $this->admiteRecepcion($solicitud)) {
            $this->addError('selectedSolicitudId', 'Esta solicitud ya no admite recepciones (estatus: '.$solicitud->estatus.').');

            return;
        }

        $validador = $this->validadorActual();
        $lugar = LugarEntrega::findOrFail($this->lugarRecepcionId);
        $ubicacionId = $this->ubicacionDestinoId;

        DB::transaction(function () use ($solicitud, $validador, $lugar, $ubicacionId) {
            // Arranca siempre de un max(codigo) fresco contra BD — ver nota
            // en Asset::resetCodigoSequenceCache().
            Asset::resetCodigoSequenceCache();

            $recepcion = Recepcion::create([
                'solicitud_proveedor_id' => $solicitud->id,
                'folio_remision' => $this->form['folio_remision'],
                'fecha_recepcion' => $this->form['fecha_recepcion'],
                'recibido_por_id' => $validador->id,
                'ubicacion_id' => $ubicacionId,
                'lugar_entrega_id' => $lugar->id,
                'registrado_por_user_id' => auth()->id(),
                'observaciones' => $this->form['observaciones'] !== '' ? $this->form['observaciones'] : null,
            ]);

            if ($this->documentoRemision) {
                $documento = DocumentoDigitalizado::storeUploaded($this->documentoRemision, $recepcion, 'remision_proveedor', auth()->id());
                $recepcion->update(['documento_remision_id' => $documento->id]);
            } elseif ($this->documentoRemisionVinculado) {
                $documento = DocumentoDigitalizado::linkExisting($this->documentoRemisionVinculado, $recepcion, 'remision_proveedor', auth()->id());
                $recepcion->update(['documento_remision_id' => $documento->id]);
            }

            // Propaga el proyecto de origen (si la SolicitudProveedor viene
            // de un artículo de Presupuesto por Proyecto) al Asset nuevo —
            // `assets.proyecto_presupuesto_id` tiene FK real desde Fase 3
            // etapa 5 y la relación `Asset::proyectoPresupuesto()` ya existe,
            // pero hasta este fix ningún código la llenaba (bug de
            // integración real encontrado en el pase end-to-end: el folio
            // del proyecto se perdía silenciosamente al llegar al Asset).
            $proyectoPresupuestoId = $solicitud->proyectoPresupuestoArticulo?->proyecto_id;

            foreach ($this->lineas as $linea) {
                $cantidad = (int) $linea['cantidad_a_recibir'];

                if ($cantidad <= 0) {
                    continue;
                }

                $solicitudLinea = SolicitudProveedorLinea::findOrFail($linea['solicitud_proveedor_linea_id']);

                if (empty($linea['usa_unidades'])) {
                    RecepcionLinea::create([
                        'recepcion_id' => $recepcion->id,
                        'solicitud_proveedor_linea_id' => $solicitudLinea->id,
                        'cantidad_recibida' => $cantidad,
                        'asset_id' => null,
                        'articulo_id' => $linea['articulo_id'] ?: null,
                    ]);
                } else {
                    // Reservación por línea (de 1 a N SICs por solicitud): si ESTA
                    // línea trae una SIC, los Asset que genera se reservan contra
                    // ella (apartado, no la asignación formal); si no, quedan libres
                    // en_stock.
                    $sicIdLinea = $linea['sic_id'] ?? null;
                    $estatusInventariableId = $sicIdLinea
                        ? $this->estatusIdPorCodigo('reservado')
                        : $this->estatusIdPorCodigo('en_stock');

                    // Piezas cuyo artículo no es inventariable: una línea de
                    // recepción por artículo, solo con la cantidad.
                    $sinActivo = [];

                    foreach ($linea['unidades'] as $unidad) {
                        // El artículo REALMENTE recibido de ESTA pieza (puede
                        // diferir del de las demás y del solicitado) es el que se
                        // hereda hacia el Asset.
                        $articulo = $this->articuloDeUnidad($linea, $unidad);

                        if (! $this->unidadEsInventariable($linea, $articulo)) {
                            $clave = $articulo?->id ?? 0;
                            $sinActivo[$clave] = ($sinActivo[$clave] ?? 0) + 1;

                            continue;
                        }

                        $tipoEquipo = TipoEquipo::findOrFail($articulo?->tipo_equipo_id ?: $linea['tipo_equipo_id']);
                        $marcaId = $articulo?->marca_id ?: ($linea['marca_id'] ?: null);
                        $modeloId = $articulo?->modelo_id ?: ($linea['modelo_id'] ?: null);
                        $especificaciones = array_filter([
                            'procesador' => $articulo?->procesador?->nombre,
                            'ram' => $articulo?->ram?->nombre,
                            'almacenamiento' => $articulo?->almacenamiento?->nombre,
                        ]) ?: null;

                        $asset = Asset::create([
                            'codigo' => Asset::generateCodigo($tipoEquipo),
                            'articulo_id' => $articulo?->id,
                            'tipo_equipo_id' => $tipoEquipo->id,
                            'marca_id' => $marcaId,
                            'modelo_id' => $modeloId,
                            'numero_serie' => $unidad['numero_serie'],
                            'service_tag' => $unidad['service_tag'] !== '' ? $unidad['service_tag'] : null,
                            'especificaciones' => $especificaciones,
                            'costo_adquisicion' => $solicitudLinea->precio_unitario_cotizado,
                            'origen_tipo' => 'compra',
                            'vendor_id' => $solicitud->vendor_id,
                            'fecha_alta_stock' => $this->form['fecha_recepcion'],
                            'fecha_inicio_garantia' => $linea['fecha_inicio_garantia'] ?: null,
                            'fecha_fin_garantia' => $linea['fecha_fin_garantia'] ?: null,
                            'ubicacion_actual_id' => $ubicacionId,
                            'sic_reservada_id' => $sicIdLinea,
                            'proyecto_presupuesto_id' => $proyectoPresupuestoId,
                            'estatus_id' => $estatusInventariableId,
                            'nota_adquisicion_original' => null,
                        ]);

                        $recepcionLinea = RecepcionLinea::create([
                            'recepcion_id' => $recepcion->id,
                            'solicitud_proveedor_linea_id' => $solicitudLinea->id,
                            'cantidad_recibida' => 1,
                            'asset_id' => $asset->id,
                            'articulo_id' => $articulo?->id,
                        ]);

                        $asset->update(['recepcion_linea_id' => $recepcionLinea->id]);
                    }

                    foreach ($sinActivo as $articuloId => $piezas) {
                        RecepcionLinea::create([
                            'recepcion_id' => $recepcion->id,
                            'solicitud_proveedor_linea_id' => $solicitudLinea->id,
                            'cantidad_recibida' => $piezas,
                            'asset_id' => null,
                            'articulo_id' => $articuloId ?: null,
                        ]);
                    }
                }

                $solicitudLinea->increment('cantidad_recibida', $cantidad);
            }

            $this->actualizarEstatusSolicitud($solicitud);
        });

        $this->showModal = false;
        $this->documentoRemision = null;
        $this->documentoRemisionVinculado = null;
        session()->flash('status', 'Recepción registrada correctamente.');
    }

    private function actualizarEstatusSolicitud(SolicitudProveedor $solicitud): void
    {
        $lineas = $solicitud->lineas()->get();

        $completo = $lineas->isNotEmpty()
            && $lineas->every(fn ($l) => $l->cantidad_recibida >= $l->cantidad_solicitada);

        $solicitud->update([
            'estatus' => $completo ? SolicitudProveedor::ESTATUS_RECIBIDA : SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA,
        ]);
    }

    /**
     * Acta de entrega-recepción de proveedor — a diferencia de la
     * Responsiva y el Formato de SIC (sin firmas), esta SÍ lleva 2 firmas
     * porque documenta una entrega física real entre el proveedor y quien
     * recibe, no un trámite interno de captura. Mismo patrón:
     * `Pdf::loadView(...)` + `streamDownload(...)`.
     */
    public function exportActaPdf(int $id)
    {
        $recepcion = Recepcion::with([
            'lineas.solicitudProveedorLinea.articulo',
            'lineas.asset',
            'solicitudProveedor.vendor',
            'ubicacion',
            'recibidoPor',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('gestionti::pdf.acta-recepcion', ['recepcion' => $recepcion]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'acta-recepcion-'.($recepcion->folio_remision ?: $recepcion->id).'.pdf'
        );
    }

    /**
     * Texto en minúsculas, sin acentos y SIN separadores (espacios, guiones,
     * puntos...) para comparar de forma tolerante: "20 RV", "20-rv" y "20rv"
     * quedan iguales.
     */
    private function normalizarBusqueda(string $texto): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(Str::ascii($texto)));
    }

    /**
     * Búsqueda tolerante de artículos: cada palabra escrita debe aparecer en
     * algún punto de código + descripción + marca + modelo, en cualquier orden
     * y sin importar mayúsculas, acentos ni separadores. Se resuelve en PHP
     * sobre el catálogo ya acotado por tipo de equipo (unos cientos de filas
     * con 3 columnas), que es más rápido y portable que armar LIKE/REPLACE por
     * palabra en SQL. Primero salen los que coinciden por marca/modelo.
     */
    /**
     * Por cada pieza de cada línea: su artículo real (o `null`) y si se da de
     * alta como Activo — para pintar sus campos y los datos que faltan.
     *
     * @return array<int, array<int, array{articulo: ?ArticuloSolicitud, inventariable: bool}>>
     */
    private function infoUnidades(): array
    {
        $info = [];

        foreach ($this->lineas as $i => $linea) {
            foreach ($linea['unidades'] ?? [] as $u => $unidad) {
                $articulo = $this->articuloDeUnidad($linea, $unidad);
                $info[$i][$u] = ['articulo' => $articulo, 'inventariable' => $this->unidadEsInventariable($linea, $articulo)];
            }
        }

        return $info;
    }

    /**
     * Por línea: si alguna pieza se da de alta como Activo y qué datos (marca,
     * modelo, tipo de equipo) le faltan a los artículos de esas piezas — para
     * mostrar los selects manuales solo cuando hacen falta.
     *
     * @return array<int, array{hay: bool, marca: bool, modelo: bool, tipo: bool}>
     */
    private function resumenLineas(): array
    {
        $resumen = [];

        foreach ($this->infoUnidades() as $i => $piezas) {
            $inventariables = collect($piezas)->filter(fn ($pieza) => $pieza['inventariable']);

            $resumen[$i] = [
                'hay' => $inventariables->isNotEmpty(),
                'marca' => $inventariables->contains(fn ($pieza) => empty($pieza['articulo']?->marca_id)),
                'modelo' => $inventariables->contains(fn ($pieza) => empty($pieza['articulo']?->modelo_id)),
                'tipo' => $inventariables->contains(fn ($pieza) => empty($pieza['articulo']?->tipo_equipo_id)),
            ];
        }

        return $resumen;
    }

    private function buscarArticulos(int $index)
    {
        $palabras = collect(preg_split('/\s+/', trim($this->articuloSearch)))
            ->map(fn ($palabra) => $this->normalizarBusqueda((string) $palabra))
            ->filter()
            ->unique()
            ->values();

        $consulta = $this->consultaArticulosPermitidos($index)
            ->with(['marca:id,nombre', 'modelo:id,nombre'])
            ->orderBy('descripcion');

        if ($palabras->isEmpty()) {
            return $consulta->limit(self::RESULTADOS_ARTICULOS + 1)->get();
        }

        return $consulta->get()
            ->map(function ($articulo) use ($palabras) {
                $marcaModelo = $this->normalizarBusqueda($articulo->marca?->nombre.' '.$articulo->modelo?->nombre);
                $todo = $this->normalizarBusqueda($articulo->codigo.' '.$articulo->descripcion).$marcaModelo;

                $articulo->coincide = $palabras->every(fn ($palabra) => str_contains($todo, $palabra));
                $articulo->puntos = $palabras->filter(fn ($palabra) => str_contains($marcaModelo, $palabra))->count();

                return $articulo;
            })
            ->filter(fn ($articulo) => $articulo->coincide)
            ->sortByDesc('puntos')
            ->take(self::RESULTADOS_ARTICULOS + 1)
            ->values();
    }

    public function render()
    {
        $busqueda = $this->search;

        $solicitudes = SolicitudProveedor::query()
            ->with('vendor')
            ->withCount('recepciones')
            ->withSum('lineas as total_solicitado', 'cantidad_solicitada')
            ->withSum('lineas as total_recibido', 'cantidad_recibida')
            ->when($busqueda !== '', function ($q) use ($busqueda) {
                $q->where(function ($q) use ($busqueda) {
                    $q->where('folio', 'like', "%{$busqueda}%")
                        ->orWhereHas('vendor', fn ($q) => $q->where('nombre_comercial', 'like', "%{$busqueda}%"))
                        ->orWhereHas('recepciones', fn ($q) => $q->where('folio_remision', 'like', "%{$busqueda}%"));
                });
            })
            ->when($this->estatusFiltro === 'pendientes', fn ($q) => $q->whereIn('estatus', [
                SolicitudProveedor::ESTATUS_SOLICITADA,
                SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA,
            ]))
            ->when($this->estatusFiltro !== '' && $this->estatusFiltro !== 'pendientes', fn ($q) => $q->where('estatus', $this->estatusFiltro))
            // Las pendientes de recibir primero, luego por fecha.
            ->orderByRaw("CASE WHEN estatus IN ('solicitada', 'parcialmente_recibida') THEN 0 ELSE 1 END")
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id')
            ->paginate(10);

        $solicitudSeleccionada = $this->selectedSolicitudId
            ? SolicitudProveedor::with([
                'vendor',
                'lineas.articulo',
                'recepciones' => fn ($q) => $q->with(['recibidoPor', 'registradoPor', 'documentoRemision'])->orderByDesc('fecha_recepcion')->orderByDesc('id'),
            ])->find($this->selectedSolicitudId)
            : null;

        return view('gestionti::livewire.compras.recepciones', [
            'solicitudes' => $solicitudes,
            'solicitudSeleccionada' => $solicitudSeleccionada,
            'admiteRecepcion' => $this->admiteRecepcion($solicitudSeleccionada),
            'validadorActual' => $this->validadorActual(),
            'esAdministrador' => $this->esAdministrador(),
            'ubicacionesDestino' => $this->ubicacionesDelLugar(),
            // Técnicos por los que un administrador puede capturar: todos los del
            // catálogo que están activos (quien recibió puede no tener usuario),
            // sin los registros de relleno como "No aplica".
            'tecnicosOptions' => $this->esAdministrador()
                ? Validador::with('lugarEntrega')->where('activo', true)
                    ->whereRaw('TRIM(nombre) <> ?', [''])
                    ->whereRaw('LOWER(TRIM(nombre)) <> ?', ['no aplica'])
                    ->orderBy('nombre')->get()
                : collect(),
            'puedeRecibir' => $this->admiteRecepcion($solicitudSeleccionada) && $this->validadorActual() !== null,
            'lugaresRecepcion' => LugarEntrega::whereIn('id', collect($this->lineas)->pluck('lugar_entrega_id')->filter()->unique())->orderBy('nombre')->get(),
            'ebsEstatusColors' => ['APPROVED' => 'emerald', 'REJECTED' => 'red', 'IN PROCESS' => 'indigo'],
            'detalleEbsRequisicion' => $this->showDetalleModal && $this->detalleEbsRequisitionId
                ? EbsRequisition::with([
                    'lines', 'notes',
                    'solicitudSicBorrador.ticket',
                    'solicitudSicBorrador.solicitudProveedorLineas.solicitud',
                    'solicitudProveedorLineas.solicitud',
                ])->find($this->detalleEbsRequisitionId)
                : null,
            'detalleSicLocal' => $this->showDetalleModal && $this->detalleSicLocalId
                ? SolicitudSicBorrador::with(['empleado', 'ticket', 'tipoEquipo', 'articulo.categoria', 'centroCosto', 'solicitudProveedorLineas.solicitud'])->find($this->detalleSicLocalId)
                : null,
            // Etiquetas de los artículos ya elegidos en las líneas (el select largo
            // se sustituyó por un buscador).
            'articulosElegidos' => ArticuloSolicitud::whereIn('id', collect($this->lineas)
                ->flatMap(fn ($linea) => array_merge([$linea['articulo_id'] ?? null], collect($linea['unidades'] ?? [])->pluck('articulo_id')->all()))
                ->filter()->unique())->get()->keyBy('id'),
            'infoUnidades' => $this->infoUnidades(),
            'resumenLineas' => $this->resumenLineas(),
            'resultadosArticulos' => $this->showArticuloModal && $this->articuloLineaIndex !== null && isset($this->lineas[$this->articuloLineaIndex])
                ? $this->buscarArticulos($this->articuloLineaIndex)
                : collect(),
            'marcaOptions' => Marca::where('activo', true)->orderBy('nombre')->get(),
            'modeloOptions' => Modelo::where('activo', true)->orderBy('nombre')->get(),
            'tipoEquipoOptions' => TipoEquipo::where('activo', true)->orderBy('nombre')->get(),
            'sharePointArchivosFiltrados' => $this->sharePointSearch !== ''
                ? array_values(array_filter(
                    $this->sharePointArchivos,
                    fn ($archivo) => str_contains(strtolower($archivo['nombre']), strtolower($this->sharePointSearch))
                ))
                : $this->sharePointArchivos,
        ]);
    }
}
