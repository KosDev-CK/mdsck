<?php

namespace Modules\GestionTI\Livewire\Compras;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\RecepcionLinea;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudProveedorLinea;
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

    #[Url(as: 'search')]
    public string $search = '';

    public bool $showModal = false;

    protected function rules(): array
    {
        return [
            'selectedSolicitudId' => 'required|exists:solicitudes_proveedor,id',
            'form.folio_remision' => 'required|string|max:100',
            'form.fecha_recepcion' => 'required|date',
            'form.recibido_por_id' => 'required|exists:validadores,id',
            'form.ubicacion_id' => 'required|exists:ubicaciones,id',
            'form.observaciones' => 'nullable|string',
            'documentoRemision' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'lineas.*.cantidad_a_recibir' => 'required|integer|min:0',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedSolicitudId(): void
    {
        $this->loadLineas();
    }

    /**
     * Catch-all de Livewire para reaccionar a cambios de propiedades
     * anidadas en arreglos (`lineas.N.cantidad_a_recibir`) — los hooks
     * mágicos `updated{Propiedad}()` de Livewire solo existen para
     * propiedades públicas de primer nivel, no para índices de arreglo.
     */
    public function updated($name, $value): void
    {
        if (preg_match('/^lineas\.(\d+)\.cantidad_a_recibir$/', $name, $m)) {
            $this->clampAndResizeLinea((int) $m[1]);
        }
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

        if (! $this->lineas[$index]['es_activo_inventariable']) {
            return;
        }

        $unidades = $this->lineas[$index]['unidades'];
        $actual = count($unidades);

        if ($cantidad > $actual) {
            for ($i = $actual; $i < $cantidad; $i++) {
                $unidades[] = ['numero_serie' => '', 'service_tag' => ''];
            }
        } elseif ($cantidad < $actual) {
            $unidades = array_slice($unidades, 0, $cantidad);
        }

        $this->lineas[$index]['unidades'] = $unidades;
    }

    public function create(): void
    {
        $this->form = [
            'folio_remision' => '',
            'fecha_recepcion' => now()->format('Y-m-d'),
            'recibido_por_id' => null,
            'ubicacion_id' => null,
            'observaciones' => null,
        ];
        $this->selectedSolicitudId = null;
        $this->lineas = [];
        $this->documentoRemision = null;
        $this->documentoRemisionVinculado = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->form = [];
        $this->selectedSolicitudId = null;
        $this->lineas = [];
        $this->documentoRemision = null;
        $this->documentoRemisionVinculado = null;
        $this->resetValidation();
    }

    /**
     * Abre el modal "Buscar en SharePoint" (Fase 5, punto 5) para vincular
     * un archivo ya existente en la carpeta de "remisión de proveedor" —
     * sin subir nada. Misma idea que `Asignaciones::openSharePointBuscar()`,
     * simplificada aquí a un solo destino posible (`documentoRemision`, esta
     * pantalla no tiene un 2do punto de subida diferido).
     */
    public function openSharePointBuscar(): void
    {
        $this->sharePointSearch = '';
        $this->resetValidation('sharePointArchivos');

        try {
            $this->sharePointArchivos = app(SharePointClient::class)->listarArchivosParaTipo('remision_proveedor');
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

        $this->documentoRemisionVinculado = $archivo;
        $this->documentoRemision = null;

        $this->cancelSharePointBuscar();
    }

    public function cancelSharePointBuscar(): void
    {
        $this->showSharePointModal = false;
        $this->sharePointSearch = '';
        $this->sharePointArchivos = [];
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

        $solicitud = SolicitudProveedor::with('lineas.articulo')->find($this->selectedSolicitudId);

        if (! $solicitud) {
            return;
        }

        foreach ($solicitud->lineas as $linea) {
            $pendiente = max(0, $linea->cantidad_solicitada - $linea->cantidad_recibida);

            $lineaForm = [
                'solicitud_proveedor_linea_id' => $linea->id,
                'descripcion' => $linea->articulo?->descripcion ?? $linea->descripcion_libre,
                'cantidad_solicitada' => $linea->cantidad_solicitada,
                'cantidad_ya_recibida' => $linea->cantidad_recibida,
                'cantidad_pendiente' => $pendiente,
                'cantidad_a_recibir' => $pendiente,
                'es_activo_inventariable' => (bool) $linea->es_activo_inventariable,
                'articulo_tipo_equipo_id' => $linea->articulo?->tipo_equipo_id,
                'tipo_equipo_id' => null,
                'marca_id' => null,
                'modelo_id' => null,
                'fecha_inicio_garantia' => null,
                'fecha_fin_garantia' => null,
                'unidades' => [],
            ];

            if ($lineaForm['es_activo_inventariable']) {
                for ($i = 0; $i < $pendiente; $i++) {
                    $lineaForm['unidades'][] = ['numero_serie' => '', 'service_tag' => ''];
                }
            }

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

            if (! $linea['es_activo_inventariable']) {
                continue;
            }

            if (empty($linea['marca_id'])) {
                $this->addError("lineas.$i.marca_id", 'La marca es requerida para un activo inventariable.');
            }

            if (empty($linea['articulo_tipo_equipo_id']) && empty($linea['tipo_equipo_id'])) {
                $this->addError("lineas.$i.tipo_equipo_id", 'Selecciona el tipo de equipo — no se pudo determinar automáticamente del artículo.');
            }

            $unidades = $linea['unidades'] ?? [];

            if (count($unidades) !== $cantidad) {
                $this->addError("lineas.$i.cantidad_a_recibir", 'El número de unidades capturadas no coincide con la cantidad a recibir.');
            }

            foreach ($unidades as $u => $unidad) {
                if (trim((string) ($unidad['numero_serie'] ?? '')) === '') {
                    $this->addError("lineas.$i.unidades.$u.numero_serie", 'El número de serie es requerido.');
                }
            }
        }

        if (empty($this->lineas)) {
            $this->addError('lineas', 'Selecciona una solicitud a proveedor con líneas.');
        } elseif ($totalARecibir === 0) {
            $this->addError('lineas', 'Captura una cantidad mayor a 0 en al menos una línea.');
        }
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

        DB::transaction(function () use ($solicitud) {
            // Arranca siempre de un max(codigo) fresco contra BD — ver nota
            // en Asset::resetCodigoSequenceCache().
            Asset::resetCodigoSequenceCache();

            $recepcion = Recepcion::create([
                'solicitud_proveedor_id' => $solicitud->id,
                'folio_remision' => $this->form['folio_remision'],
                'fecha_recepcion' => $this->form['fecha_recepcion'],
                'recibido_por_id' => $this->form['recibido_por_id'],
                'ubicacion_id' => $this->form['ubicacion_id'],
                'observaciones' => $this->form['observaciones'] !== '' ? $this->form['observaciones'] : null,
            ]);

            if ($this->documentoRemision) {
                $documento = DocumentoDigitalizado::storeUploaded($this->documentoRemision, $recepcion, 'remision_proveedor', auth()->id());
                $recepcion->update(['documento_remision_id' => $documento->id]);
            } elseif ($this->documentoRemisionVinculado) {
                $documento = DocumentoDigitalizado::linkExisting($this->documentoRemisionVinculado, $recepcion, 'remision_proveedor', auth()->id());
                $recepcion->update(['documento_remision_id' => $documento->id]);
            }

            // Reservación: si la SolicitudProveedor viene de una SIC, el
            // Asset nuevo se reserva contra esa SIC (apartado, no la
            // asignación formal); si no, queda libre en_stock. Se resuelve
            // una sola vez fuera del loop de líneas/unidades.
            $estatusInventariableId = $solicitud->sic_id
                ? $this->estatusIdPorCodigo('reservado')
                : $this->estatusIdPorCodigo('en_stock');

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

                if (! $linea['es_activo_inventariable']) {
                    RecepcionLinea::create([
                        'recepcion_id' => $recepcion->id,
                        'solicitud_proveedor_linea_id' => $solicitudLinea->id,
                        'cantidad_recibida' => $cantidad,
                        'asset_id' => null,
                    ]);
                } else {
                    $tipoEquipoId = $linea['articulo_tipo_equipo_id'] ?: $linea['tipo_equipo_id'];
                    $tipoEquipo = TipoEquipo::findOrFail($tipoEquipoId);

                    foreach ($linea['unidades'] as $unidad) {
                        $asset = Asset::create([
                            'codigo' => Asset::generateCodigo($tipoEquipo),
                            'tipo_equipo_id' => $tipoEquipo->id,
                            'marca_id' => $linea['marca_id'] ?: null,
                            'modelo_id' => $linea['modelo_id'] ?: null,
                            'numero_serie' => $unidad['numero_serie'],
                            'service_tag' => $unidad['service_tag'] !== '' ? $unidad['service_tag'] : null,
                            'costo_adquisicion' => $solicitudLinea->precio_unitario_cotizado,
                            'origen_tipo' => 'compra',
                            'vendor_id' => $solicitud->vendor_id,
                            'fecha_alta_stock' => $this->form['fecha_recepcion'],
                            'fecha_inicio_garantia' => $linea['fecha_inicio_garantia'] ?: null,
                            'fecha_fin_garantia' => $linea['fecha_fin_garantia'] ?: null,
                            'ubicacion_actual_id' => $this->form['ubicacion_id'],
                            'sic_reservada_id' => $solicitud->sic_id,
                            'proyecto_presupuesto_id' => $proyectoPresupuestoId,
                            'estatus_id' => $estatusInventariableId,
                            'nota_adquisicion_original' => null,
                        ]);

                        $recepcionLinea = RecepcionLinea::create([
                            'recepcion_id' => $recepcion->id,
                            'solicitud_proveedor_linea_id' => $solicitudLinea->id,
                            'cantidad_recibida' => 1,
                            'asset_id' => $asset->id,
                        ]);

                        $asset->update(['recepcion_linea_id' => $recepcionLinea->id]);
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

    public function render()
    {
        $records = Recepcion::query()
            ->with(['solicitudProveedor.vendor', 'recibidoPor'])
            ->when($this->search !== '', function ($q) {
                $q->where('folio_remision', 'like', "%{$this->search}%")
                    ->orWhereHas('solicitudProveedor', fn ($q) => $q->where('folio', 'like', "%{$this->search}%"));
            })
            ->orderByDesc('fecha_recepcion')
            ->paginate(10);

        return view('gestionti::livewire.compras.recepciones', [
            'records' => $records,
            'solicitudOptions' => SolicitudProveedor::whereIn('estatus', [
                SolicitudProveedor::ESTATUS_SOLICITADA,
                SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA,
            ])->with('vendor')->orderByDesc('fecha_solicitud')->get(),
            'validadorOptions' => Validador::where('activo', true)->orderBy('nombre')->get(),
            'ubicacionOptions' => Ubicacion::where('activo', true)->orderBy('nombre')->get(),
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
