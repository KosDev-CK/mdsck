<?php

namespace Modules\GestionTI\Livewire\Inventarios;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\SistemaOperativo;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Validador;
use Modules\GestionTI\Support\SharePoint\SharePointClient;
use Modules\GestionTI\Support\SharePoint\SharePointException;

/**
 * Asignación de Activo (sección 7.8 del spec original) — ver
 * docs/gestionti-progreso.md, Fase 3 etapa 4, para el diseño completo.
 *
 * Sin `edit()`, sin "cancelar" — mismo criterio ya usado en `Recepciones.php`:
 * una asignación guardada ya cambió el estatus de un Asset real a `asignado`;
 * "editarla" implicaría deshacer una alta de ciclo de vida, algo que el spec
 * original no describe.
 *
 * Sin flujo de devolución en esta etapa — `fecha_devolucion` existe en el
 * esquema desde Fase 2 pero esta entrega no construye ninguna acción para
 * llenarlo (fuera del alcance textual de 7.8, que solo habla de crear la
 * asignación + PDF + documento firmado). Queda deferred para una futura
 * iteración de esta misma pantalla.
 */
#[Layout('layouts.app')]
class Asignaciones extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $form = [];

    /** Responsiva firmada — opcional al crear, ver nota de flujo físico abajo. */
    public $documentoResponsiva;

    public string $search = '';

    public bool $showModal = false;

    // Modal secundario para adjuntar la responsiva firmada después de creada
    // la asignación (el spec describe un flujo físico: PDF en blanco -> se
    // imprime -> se firma en papel -> solo después existe un escaneo que
    // subir, no encaja con forzar la subida en el mismo formulario de alta).
    public bool $showAttachModal = false;

    public ?int $attachingId = null;

    public $attachDocumento;

    /**
     * Archivo ya existente en SharePoint elegido vía el modal "Buscar en
     * SharePoint" — alternativa a subir un archivo nuevo con
     * `$documentoResponsiva`/`$attachDocumento` (Fase 5, punto 5). Ambas
     * propiedades de "vinculado" son mutuamente excluyentes con su
     * contraparte de subida: elegir un archivo aquí limpia la propiedad de
     * subida correspondiente, y viceversa (ver `elegirArchivoSharePoint()`).
     *
     * @var array{driveItemId: string, nombre: string, webUrl: string}|null
     */
    public ?array $documentoResponsivaVinculado = null;

    /** @var array{driveItemId: string, nombre: string, webUrl: string}|null */
    public ?array $attachDocumentoVinculado = null;

    public bool $showSharePointModal = false;

    public string $sharePointSearch = '';

    /** @var array<int, array{driveItemId: string, nombre: string, webUrl: string}> */
    public array $sharePointArchivos = [];

    /** 'documentoResponsiva' (modal de crear) | 'attachDocumento' (modal de adjuntar después). */
    public ?string $sharePointTarget = null;

    protected function rules(): array
    {
        // sic_id/asset_id/responsable_entrega_id son requeridos; el resto de
        // los campos originales son texto libre opcional, no selects de FK.
        // `sistema_operativo_id` sí es un select de FK opcional (único de la
        // sección "Configuración técnica") — manda '' como "Sin asignar",
        // normalizado a null por `nullifyEmptyForeignKeys()` antes de
        // validar, mismo patrón ya documentado en otras pantallas del
        // módulo.
        return [
            'form.sic_id' => 'required|exists:solicitudes_sic_borrador,id',
            'form.asset_id' => 'required|exists:assets,id',
            'form.fecha_asignacion' => 'required|date',
            'form.estado_equipo_entrega' => ['required', Rule::in(AssetAssignment::ESTADOS_EQUIPO_ENTREGA)],
            'form.accesorios_entregados' => 'nullable|string',
            'form.responsable_entrega_id' => 'required|exists:validadores,id',
            'form.observaciones' => 'nullable|string',
            'documentoResponsiva' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',

            // Configuración técnica (opcional) — sección 4 del documento real
            // de responsiva (ver docs/gestionti-progreso.md, Fase 4 etapa 2).
            'form.ip' => 'nullable|string|max:255',
            'form.mac_wifi' => 'nullable|string|max:255',
            'form.mac_ethernet' => 'nullable|string|max:255',
            'form.sistema_operativo_id' => 'nullable|exists:sistemas_operativos,id',
            'form.version_office' => 'nullable|string|max:255',
            'form.antivirus' => 'nullable|string|max:255',
            'form.dominio' => 'nullable|string|max:255',
            'form.usuario_dominio' => 'nullable|string|max:255',
            'form.id_producto_so' => 'nullable|string|max:255',
            'form.libra_cloud' => 'nullable|boolean',
            'form.oracle_ebs' => 'nullable|boolean',
        ];
    }

    /**
     * Normaliza a `null` los selects opcionales de la sección "Configuración
     * técnica" que mandan '' como "Sin asignar"/"Sin capturar": el único
     * select de FK de este formulario (`sistema_operativo_id` — mismo fix ya
     * documentado en `Empleados.php`/`Compras.php`/`Inventario.php`, sin
     * esto la FK nullable revienta al recibir cadena vacía) y los 2 selects
     * "Sí/No/Sin capturar" (`libra_cloud`/`oracle_ebs` — la regla `boolean`
     * no acepta '' como valor válido, a diferencia de `null`, que sí pasa
     * gracias a `nullable`).
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach (['sistema_operativo_id', 'libra_cloud', 'oracle_ebs'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Catch-all de Livewire para reaccionar al cambio de SIC seleccionada
     * (`form.sic_id`, propiedad anidada — no existe un hook mágico
     * `updatedFormSicId()` para propiedades dentro de un arreglo, mismo
     * motivo ya documentado en `Recepciones::updated()`). Las opciones de
     * Activo dependen de la SIC elegida (`render()` las recalcula en cada
     * request), así que aquí solo se limpia `asset_id` si ya no aplica a la
     * nueva SIC — evita dejar seleccionado (en el estado, no visualmente) un
     * Activo reservado para otra SIC distinta.
     */
    public function updated($name, $value): void
    {
        if ($name === 'form.sic_id') {
            $this->form['asset_id'] = null;
        }
    }

    public function create(): void
    {
        $this->form = [
            'sic_id' => null,
            'asset_id' => null,
            'fecha_asignacion' => now()->format('Y-m-d'),
            'estado_equipo_entrega' => null,
            'accesorios_entregados' => null,
            'responsable_entrega_id' => null,
            'observaciones' => null,
            // Configuración técnica (opcional).
            'ip' => null,
            'mac_wifi' => null,
            'mac_ethernet' => null,
            'sistema_operativo_id' => null,
            'version_office' => null,
            'antivirus' => null,
            'dominio' => null,
            'usuario_dominio' => null,
            'id_producto_so' => null,
            'libra_cloud' => null,
            'oracle_ebs' => null,
        ];
        $this->documentoResponsiva = null;
        $this->documentoResponsivaVinculado = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->form = [];
        $this->documentoResponsiva = null;
        $this->documentoResponsivaVinculado = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->nullifyEmptyForeignKeys();
        $this->validate();

        $sic = SolicitudSicBorrador::findOrFail($this->form['sic_id']);
        $asset = Asset::with('estatus')->findOrFail($this->form['asset_id']);

        // Defensa contra condición de carrera: la lista de opciones del
        // último render ya filtró a `en_stock` o `reservado`-para-esta-SIC,
        // pero pudo cambiar entre esa carga y este submit (otro proceso
        // pudo asignar el mismo Activo mientras tanto) — se vuelve a
        // verificar aquí antes de crear nada.
        $calificaEnStock = $asset->estatus?->codigo === 'en_stock';
        $calificaReservadoParaEstaSic = $asset->estatus?->codigo === 'reservado'
            && $asset->sic_reservada_id === $sic->id;

        if (! $calificaEnStock && ! $calificaReservadoParaEstaSic) {
            $this->addError('form.asset_id', 'Este activo ya no está disponible para asignarse — selecciona otro.');

            return;
        }

        DB::transaction(function () use ($sic, $asset) {
            $assignment = AssetAssignment::create([
                'asset_id' => $asset->id,
                'empleado_id' => $sic->empleado_id,
                'ticket_id' => $sic->ticket_id,
                'sic_id' => $sic->id,
                'fecha_asignacion' => $this->form['fecha_asignacion'],
                'estado_equipo_entrega' => $this->form['estado_equipo_entrega'],
                'accesorios_entregados' => ($this->form['accesorios_entregados'] ?? '') !== ''
                    ? $this->form['accesorios_entregados']
                    : null,
                'responsable_entrega_id' => $this->form['responsable_entrega_id'],
                'observaciones' => ($this->form['observaciones'] ?? '') !== ''
                    ? $this->form['observaciones']
                    : null,
                // Configuración técnica (opcional) — Fase 4 etapa 2.
                'ip' => ($this->form['ip'] ?? '') !== '' ? $this->form['ip'] : null,
                'mac_wifi' => ($this->form['mac_wifi'] ?? '') !== '' ? $this->form['mac_wifi'] : null,
                'mac_ethernet' => ($this->form['mac_ethernet'] ?? '') !== '' ? $this->form['mac_ethernet'] : null,
                'sistema_operativo_id' => $this->form['sistema_operativo_id'] ?? null,
                'version_office' => ($this->form['version_office'] ?? '') !== '' ? $this->form['version_office'] : null,
                'antivirus' => ($this->form['antivirus'] ?? '') !== '' ? $this->form['antivirus'] : null,
                'dominio' => ($this->form['dominio'] ?? '') !== '' ? $this->form['dominio'] : null,
                'usuario_dominio' => ($this->form['usuario_dominio'] ?? '') !== '' ? $this->form['usuario_dominio'] : null,
                'id_producto_so' => ($this->form['id_producto_so'] ?? '') !== '' ? $this->form['id_producto_so'] : null,
                'libra_cloud' => $this->form['libra_cloud'] ?? null,
                'oracle_ebs' => $this->form['oracle_ebs'] ?? null,
            ]);

            $asset->update(['estatus_id' => $this->estatusIdPorCodigo('asignado')]);

            if ($this->documentoResponsiva) {
                $documento = DocumentoDigitalizado::storeUploaded(
                    $this->documentoResponsiva,
                    $assignment,
                    'responsiva',
                    auth()->id()
                );
                $assignment->update(['documento_responsiva_id' => $documento->id]);
            } elseif ($this->documentoResponsivaVinculado) {
                $documento = DocumentoDigitalizado::linkExisting(
                    $this->documentoResponsivaVinculado,
                    $assignment,
                    'responsiva',
                    auth()->id()
                );
                $assignment->update(['documento_responsiva_id' => $documento->id]);
            }
        });

        $this->showModal = false;
        $this->documentoResponsiva = null;
        $this->documentoResponsivaVinculado = null;
        session()->flash('status', 'Asignación registrada correctamente.');
    }

    public function openAttach(int $id): void
    {
        $assignment = AssetAssignment::findOrFail($id);

        if ($assignment->documento_responsiva_id !== null) {
            return;
        }

        $this->attachingId = $id;
        $this->attachDocumento = null;
        $this->attachDocumentoVinculado = null;
        $this->resetValidation();
        $this->showAttachModal = true;
    }

    public function confirmAttach(): void
    {
        // Se requiere uno de los 2 caminos (subir un archivo nuevo o haber
        // vinculado uno existente vía el modal "Buscar en SharePoint") —
        // mutuamente excluyentes, nunca los 2 a la vez (elegir uno limpia el
        // otro, ver `elegirArchivoSharePoint()`).
        if (! $this->attachDocumento && ! $this->attachDocumentoVinculado) {
            $this->addError('attachDocumento', 'Sube un archivo o vincula uno existente de SharePoint.');

            return;
        }

        if ($this->attachDocumento) {
            $this->validate(['attachDocumento' => 'file|mimes:pdf,jpg,jpeg,png|max:5120']);
        }

        $assignment = AssetAssignment::findOrFail($this->attachingId);

        // No se debe poder subir un 2do archivo si ya existe uno — validado
        // aquí también por si acaso, no solo ocultando la acción en la vista.
        if ($assignment->documento_responsiva_id !== null) {
            $this->cancelAttach();

            return;
        }

        $documento = $this->attachDocumento
            ? DocumentoDigitalizado::storeUploaded($this->attachDocumento, $assignment, 'responsiva', auth()->id())
            : DocumentoDigitalizado::linkExisting($this->attachDocumentoVinculado, $assignment, 'responsiva', auth()->id());

        $assignment->update(['documento_responsiva_id' => $documento->id]);

        $this->cancelAttach();
        session()->flash('status', 'Responsiva firmada adjuntada correctamente.');
    }

    public function cancelAttach(): void
    {
        $this->showAttachModal = false;
        $this->attachingId = null;
        $this->attachDocumento = null;
        $this->attachDocumentoVinculado = null;
        $this->resetValidation();
    }

    /**
     * Abre el modal "Buscar en SharePoint" (Fase 5, punto 5) para vincular
     * un archivo ya existente en la carpeta de "responsiva" — sin subir
     * nada. `$target` indica a qué propiedad de "vinculado" va el archivo
     * elegido: 'documentoResponsiva' desde el modal de crear, o
     * 'attachDocumento' desde el modal de adjuntar después. La lista
     * completa de la carpeta se trae una sola vez de Graph; el filtro por
     * nombre (`sharePointSearch`) se aplica en memoria en `render()`, sin
     * volver a pegarle a Graph por cada tecla. Excluye los archivos que ya
     * quedaron vinculados a otro registro (`DocumentoDigitalizado::driveItemIdsVinculados()`)
     * para no ofrecer dos veces el mismo archivo.
     */
    public function openSharePointBuscar(string $target): void
    {
        $this->sharePointTarget = $target;
        $this->sharePointSearch = '';
        $this->resetValidation('sharePointArchivos');

        try {
            $vinculados = DocumentoDigitalizado::driveItemIdsVinculados('responsiva');
            $this->sharePointArchivos = collect(app(SharePointClient::class)->listarArchivosParaTipo('responsiva'))
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

        if ($this->sharePointTarget === 'documentoResponsiva') {
            $this->documentoResponsivaVinculado = $archivo;
            $this->documentoResponsiva = null;
        } elseif ($this->sharePointTarget === 'attachDocumento') {
            $this->attachDocumentoVinculado = $archivo;
            $this->attachDocumento = null;
        }

        $this->cancelSharePointBuscar();
    }

    public function cancelSharePointBuscar(): void
    {
        $this->showSharePointModal = false;
        $this->sharePointSearch = '';
        $this->sharePointArchivos = [];
        $this->sharePointTarget = null;
    }

    /**
     * Genera el PDF de responsiva en blanco — no depende de que ya exista el
     * documento firmado, es justamente lo que hay que imprimir para firmar.
     * Mismo patrón que `Modules\FormBuilder\Livewire\Links\Show::exportPdf()`.
     */
    public function exportResponsivaPdf(int $id)
    {
        $assignment = AssetAssignment::with([
            'asset.tipoEquipo', 'asset.marca', 'asset.modelo',
            'empleado.puesto', 'empleado.area', 'empleado.ubicacion',
            'empleado.jefeInmediato', 'empleado.director', 'empleado.directorEjecutivo',
            'responsableEntrega', 'sistemaOperativo', 'ticket',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('gestionti::pdf.responsiva', ['assignment' => $assignment]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'responsiva-'.$assignment->asset->codigo.'.pdf'
        );
    }

    private function estatusIdPorCodigo(string $codigo): int
    {
        $id = EstatusActivo::where('codigo', $codigo)->value('id');

        if ($id === null) {
            throw new \RuntimeException("Falta el estatus base '{$codigo}' en estatus_activo — corre primero `php artisan module:seed GestionTI`.");
        }

        return $id;
    }

    /**
     * Etiqueta legible para el select de Activo: código + tipo de equipo +
     * marca/modelo + número de serie. Pública porque se invoca desde la
     * vista Blade.
     */
    public function assetOptionLabel(Asset $asset): string
    {
        $tipo = $asset->tipoEquipo?->nombre ?? 'Sin tipo';
        $marcaModelo = trim(($asset->marca?->nombre ?? '').' '.($asset->modelo?->nombre ?? ''));
        $serie = $asset->numero_serie ? "S/N {$asset->numero_serie}" : 'Sin número de serie';

        $label = "{$asset->codigo} — {$tipo}";

        if ($marcaModelo !== '') {
            $label .= " — {$marcaModelo}";
        }

        return "{$label} — {$serie}";
    }

    /**
     * Etiqueta legible para el select de SIC. Pública porque se invoca desde
     * la vista Blade.
     */
    public function sicOptionLabel(SolicitudSicBorrador $sic): string
    {
        $folio = $sic->folio_sic ?: "SIC #{$sic->id}";

        return "{$folio} — {$sic->empleado?->nombre} — {$sic->tipoEquipo?->nombre}";
    }

    private function assetOptionsFor(?int $sicId)
    {
        return Asset::query()
            ->with(['tipoEquipo', 'marca', 'modelo'])
            ->where(function ($q) use ($sicId) {
                $q->whereHas('estatus', fn ($q2) => $q2->where('codigo', 'en_stock'));

                if ($sicId) {
                    $q->orWhere(function ($q2) use ($sicId) {
                        $q2->whereHas('estatus', fn ($q3) => $q3->where('codigo', 'reservado'))
                            ->where('sic_reservada_id', $sicId);
                    });
                }
            })
            ->orderBy('codigo')
            ->get();
    }

    public function render()
    {
        $records = AssetAssignment::query()
            ->with(['asset', 'empleado', 'sic', 'responsableEntrega'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->whereHas('asset', fn ($q2) => $q2->where('codigo', 'like', "%{$this->search}%"))
                        ->orWhereHas('empleado', fn ($q2) => $q2->where('nombre', 'like', "%{$this->search}%"))
                        ->orWhereHas('sic', fn ($q2) => $q2->where('folio_sic', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('fecha_asignacion')
            ->paginate(10);

        $selectedSicId = $this->form['sic_id'] ?? null;

        return view('gestionti::livewire.inventarios.asignaciones', [
            'records' => $records,
            'sicOptions' => SolicitudSicBorrador::where('estatus', SolicitudSicBorrador::ESTATUS_AUTORIZADA)
                ->whereDoesntHave('assetAssignments')
                ->with(['empleado', 'tipoEquipo', 'ticket'])
                ->get(),
            'assetOptions' => $this->assetOptionsFor($selectedSicId),
            'validadorOptions' => Validador::where('activo', true)->orderBy('nombre')->get(),
            'sistemaOperativoOptions' => SistemaOperativo::where('activo', true)->orderBy('nombre')->get(),
            'selectedSic' => $selectedSicId
                ? SolicitudSicBorrador::with('empleado')->find($selectedSicId)
                : null,
            'sharePointArchivosFiltrados' => $this->sharePointSearch !== ''
                ? array_values(array_filter(
                    $this->sharePointArchivos,
                    fn ($archivo) => str_contains(strtolower($archivo['nombre']), strtolower($this->sharePointSearch))
                ))
                : $this->sharePointArchivos,
        ]);
    }
}
