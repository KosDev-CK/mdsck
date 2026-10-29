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
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\SistemaOperativo;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Validador;
use Modules\GestionTI\Support\Sic\ResolutorSic;
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

    /** Solo asignaciones hechas antes de que su SIC estuviera autorizada y que aún no se cubren. */
    public bool $soloPendientes = false;

    public bool $showModal = false;

    // Modal "Ligar SIC": formaliza una asignación hecha como excepción cuando
    // su SIC ya existe y está autorizada.
    public bool $showLigarModal = false;

    public ?int $ligandoId = null;

    public string $ligarFolio = '';

    public ?int $ligarLineaId = null;

    public bool $ligarConfirmaEmpleado = false;

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
            // El folio de la SIC es siempre obligatorio: se elige una SIC local
            // autorizada, o se captura el folio (EBS o local, aunque aún no esté
            // autorizada — entonces es una excepción con motivo).
            'form.sic_id' => $this->origenSic() === 'local' ? 'required|exists:solicitudes_sic_borrador,id' : 'nullable',
            'form.sic_folio' => $this->origenSic() === 'ebs' ? 'required|string|max:100' : 'nullable',
            'form.ebs_linea_id' => 'nullable|integer',
            'form.empleado_id' => 'nullable|exists:empleados,id',
            'form.motivo_excepcion' => 'nullable|string|max:2000',
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
        foreach (['sistema_operativo_id', 'libra_cloud', 'oracle_ebs', 'empleado_id', 'ebs_linea_id'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }
    }

    /**
     * Acceso directo desde "SIC en EBS" (`?folio=...`): abre el formulario con el
     * folio de esa SIC ya capturado.
     */
    public function mount(): void
    {
        $folio = trim((string) request()->query('folio', ''));

        if ($folio !== '') {
            $this->create();
            $this->form['sic_folio'] = $folio;
            $this->updated('form.sic_folio', $folio);
        }
    }

    protected function messages(): array
    {
        return [
            'form.sic_id.required' => 'Elige la SIC local autorizada.',
            'form.sic_folio.required' => 'Captura el folio de la SIC (obligatorio aunque aún no esté autorizada).',
            'form.asset_id.required' => 'Elige el activo a entregar.',
            'form.fecha_asignacion.required' => 'Captura la fecha de asignación.',
            'form.estado_equipo_entrega.required' => 'Elige el estado del equipo entregado.',
            'form.responsable_entrega_id.required' => 'Elige al responsable de la entrega.',
        ];
    }

    /**
     * De dónde sale la SIC: `ebs` (por folio — requisición de EBS, flujo
     * principal, o cualquier folio) o `local` (SIC capturada en el sistema,
     * respaldo cuando EBS no está disponible). Si ya hay una SIC local elegida
     * se trata como local aunque el interruptor no se haya movido.
     */
    private function origenSic(): string
    {
        if (filled($this->form['sic_id'] ?? null)) {
            return 'local';
        }

        return ($this->form['origen_sic'] ?? 'ebs') === 'local' ? 'local' : 'ebs';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSoloPendientes(): void
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

        // Cambiar de origen o de folio invalida lo elegido a partir de la SIC.
        if ($name === 'form.origen_sic') {
            $this->form['sic_id'] = null;
            $this->form['sic_folio'] = null;
            $this->form['ebs_linea_id'] = null;
            $this->form['asset_id'] = null;
        }

        if ($name === 'form.ebs_linea_id') {
            $this->form['asset_id'] = null;
        }

        if ($name === 'form.sic_folio') {
            $this->form['ebs_linea_id'] = null;
            $this->form['asset_id'] = null;
            $res = ResolutorSic::porFolio((string) $value);

            // Una requisición con una sola línea con piezas libres la elige sola.
            if ($res['tipo'] === 'ebs' && ! $res['local']) {
                $libres = ResolutorSic::lineasEbs($res['ebs'])->where('libres', '>', 0);

                if ($libres->count() === 1) {
                    $this->form['ebs_linea_id'] = $libres->first()['id'];
                }
            }
        }
    }

    public function create(): void
    {
        $this->form = [
            'origen_sic' => 'ebs',
            'sic_id' => null,
            'sic_folio' => null,
            'ebs_linea_id' => null,
            'empleado_id' => null,
            'motivo_excepcion' => null,
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

        $asset = Asset::with('estatus')->findOrFail($this->form['asset_id']);
        $origen = $this->origenSic();
        $claveSic = $origen === 'local' ? 'form.sic_id' : 'form.sic_folio';

        $sic = null;
        $requisicion = null;
        $lineaEbsId = null;
        $autorizada = true;

        if ($origen === 'local') {
            $sic = SolicitudSicBorrador::findOrFail($this->form['sic_id']);
            $folio = $sic->folio_sic ?: "SIC-LOCAL-{$sic->id}";

            if ($sic->estatus !== SolicitudSicBorrador::ESTATUS_AUTORIZADA) {
                $this->addError($claveSic, 'Esta SIC local aún no está autorizada: captura su folio en "SIC de EBS (por folio)" para asignar como excepción.');

                return;
            }
        } else {
            $folio = trim((string) $this->form['sic_folio']);
            $res = ResolutorSic::porFolio($folio);

            if ($res['bloqueo']) {
                $this->addError($claveSic, $res['bloqueo']);

                return;
            }

            $sic = $res['local'];
            $requisicion = $sic ? null : $res['ebs'];
            $autorizada = $res['autorizada'];
        }

        // Asignar antes de que la SIC esté autorizada es una excepción: pide motivo.
        if (! $autorizada && trim((string) ($this->form['motivo_excepcion'] ?? '')) === '') {
            $this->addError('form.motivo_excepcion', 'La SIC '.$folio.' aún no está autorizada (o no existe): captura el motivo de la excepción.');

            return;
        }

        // Una SIC local ya asignada, o con un equipo reservado distinto al elegido,
        // no puede volver a asignarse aquí: se rechaza antes de tocar nada.
        if ($sic) {
            if ($sic->assetAssignments()->exists()) {
                $this->addError($claveSic, 'Esta SIC ya tiene una asignación: una SIC local cubre un solo equipo.');

                return;
            }

            $reservado = Asset::where('sic_reservada_id', $sic->id)
                ->whereHas('estatus', fn ($q) => $q->where('codigo', 'reservado'))
                ->first();

            if ($reservado && $reservado->id !== $asset->id) {
                $this->addError($claveSic, "Esta SIC ya tiene reservado el activo {$reservado->codigo}: asígnalo a ella (o libera la reserva desde Stock) en vez de usar otro equipo.");

                return;
            }
        }

        // Requisición de EBS: cada artículo es una línea con su identificador único y
        // puede cubrir tantas asignaciones como piezas trae.
        if ($requisicion) {
            $lineas = ResolutorSic::lineasEbs($requisicion);

            if ($lineas->count() > 1 && empty($this->form['ebs_linea_id'])) {
                $this->addError('form.ebs_linea_id', 'Esta SIC trae varios artículos: elige la línea que se está entregando.');

                return;
            }

            $lineaEbsId = $lineas->count() === 1 ? $lineas->first()['id'] : ($this->form['ebs_linea_id'] ?? null);
            $linea = $lineaEbsId ? $lineas->firstWhere('id', (int) $lineaEbsId) : null;

            if ($lineaEbsId && ! $linea) {
                $this->addError('form.ebs_linea_id', 'La línea elegida no pertenece a esa SIC.');

                return;
            }

            if ($linea && $linea['libres'] <= 0) {
                $this->addError('form.ebs_linea_id', "La línea {$linea['numero']} de la SIC {$folio} ya tiene cubiertas sus {$linea['cantidad']} pieza(s).");

                return;
            }

            // Lo recibido de esa línea queda reservado para ella: si hay equipos
            // reservados sin asignar, se entregan esos en vez de uno cualquiera de stock.
            $reservadosDeLaLinea = $this->reservadosDeEbs($requisicion->id, $lineaEbsId);

            if ($reservadosDeLaLinea->isNotEmpty() && ! $reservadosDeLaLinea->contains('id', $asset->id)) {
                $this->addError('form.asset_id', 'Esta SIC ya tiene equipos reservados que llegaron en su recepción ('.$reservadosDeLaLinea->pluck('codigo')->join(', ').'): asigna uno de ellos antes de usar otro de stock.');

                return;
            }
        }

        $empleadoId = $sic?->empleado_id ?? ($this->form['empleado_id'] ?? null);

        if (! $empleadoId) {
            $this->addError('form.empleado_id', 'Elige el empleado que recibe el equipo (esta SIC no lo trae).');

            return;
        }

        // Defensa contra condición de carrera: la lista de opciones del
        // último render ya filtró a `en_stock` o `reservado`-para-esta-SIC,
        // pero pudo cambiar entre esa carga y este submit (otro proceso
        // pudo asignar el mismo Activo mientras tanto) — se vuelve a
        // verificar aquí antes de crear nada.
        $calificaEnStock = $asset->estatus?->codigo === 'en_stock';
        $calificaReservadoParaEstaSic = $asset->estatus?->codigo === 'reservado' && (
            ($sic && $asset->sic_reservada_id === $sic->id)
            || ($requisicion && $this->reservadosDeEbs($requisicion->id, $lineaEbsId)->contains('id', $asset->id))
        );

        if (! $calificaEnStock && ! $calificaReservadoParaEstaSic) {
            $this->addError('form.asset_id', 'Este activo ya no está disponible para asignarse — selecciona otro.');

            return;
        }

        DB::transaction(function () use ($sic, $asset, $requisicion, $lineaEbsId, $folio, $autorizada, $empleadoId) {
            $assignment = AssetAssignment::create([
                'asset_id' => $asset->id,
                'empleado_id' => $empleadoId,
                'ticket_id' => $sic?->ticket_id,
                'sic_id' => $sic?->id,
                'sic_folio' => $folio,
                'ebs_requisition_id' => $requisicion?->id,
                'ebs_requisition_line_id' => $lineaEbsId,
                'excepcion_sin_sic' => ! $autorizada,
                'motivo_excepcion' => $autorizada ? null : trim((string) $this->form['motivo_excepcion']),
                'registrado_por_user_id' => auth()->id(),
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

    /**
     * Quita la responsiva vinculada a una asignación (el técnico subió/eligió
     * el archivo equivocado) — borra el `DocumentoDigitalizado` (nunca el
     * archivo real en SharePoint/disco, ver `DocumentoDigitalizado::quitar()`)
     * y limpia la FK, dejando la asignación en el mismo estado que antes de
     * adjuntar nada: "Adjuntar responsiva firmada" vuelve a aparecer para
     * corregirlo, y el archivo original queda libre de nuevo en "Buscar en
     * SharePoint" (`DocumentoDigitalizado::driveItemIdsVinculados()` ya no lo
     * cuenta como vinculado).
     */
    public function quitarResponsiva(int $id): void
    {
        $assignment = AssetAssignment::findOrFail($id);

        if ($assignment->documento_responsiva_id === null) {
            return;
        }

        DocumentoDigitalizado::quitar($assignment->documento_responsiva_id);
        $assignment->update(['documento_responsiva_id' => null]);

        session()->flash('status', 'Responsiva desvinculada — puedes adjuntar la correcta desde el listado.');
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

    /**
     * "Ligar SIC": formaliza una asignación hecha como excepción (antes de que su
     * SIC estuviera autorizada) cuando esa SIC ya existe y está autorizada.
     * Valida lo mismo que al asignar: SIC no asignada, sin otro equipo reservado,
     * línea de EBS con piezas libres y, si el empleado de la SIC es otro, pide
     * confirmación.
     */
    public function openLigar(int $id): void
    {
        $assignment = AssetAssignment::findOrFail($id);

        if ($assignment->sic_id || $assignment->ebs_requisition_id) {
            return;
        }

        $this->ligandoId = $id;
        $this->ligarFolio = (string) ($assignment->sic_folio ?? '');
        $this->ligarLineaId = null;
        $this->ligarConfirmaEmpleado = false;
        $this->resetValidation();
        $this->showLigarModal = true;
        $this->autoElegirLineaLigar();
    }

    public function closeLigar(): void
    {
        $this->showLigarModal = false;
        $this->ligandoId = null;
        $this->ligarFolio = '';
        $this->ligarLineaId = null;
        $this->ligarConfirmaEmpleado = false;
        $this->resetValidation();
    }

    public function updatedLigarFolio(): void
    {
        $this->ligarLineaId = null;
        $this->ligarConfirmaEmpleado = false;
        $this->autoElegirLineaLigar();
    }

    private function autoElegirLineaLigar(): void
    {
        $res = ResolutorSic::porFolio($this->ligarFolio);

        if ($res['tipo'] === 'ebs' && ! $res['local']) {
            $libres = ResolutorSic::lineasEbs($res['ebs'], $this->ligandoId)->where('libres', '>', 0);

            if ($libres->count() === 1) {
                $this->ligarLineaId = $libres->first()['id'];
            }
        }
    }

    public function confirmLigar(): void
    {
        $assignment = $this->ligandoId ? AssetAssignment::with('asset')->find($this->ligandoId) : null;

        if (! $assignment || $assignment->sic_id || $assignment->ebs_requisition_id) {
            $this->closeLigar();

            return;
        }

        $folio = trim($this->ligarFolio);
        $res = ResolutorSic::porFolio($folio);

        if ($folio === '' || $res['tipo'] === null) {
            $this->addError('ligarFolio', 'No existe ninguna SIC con ese folio, ni local ni de EBS.');

            return;
        }

        if ($res['bloqueo']) {
            $this->addError('ligarFolio', $res['bloqueo']);

            return;
        }

        if (! $res['autorizada']) {
            $this->addError('ligarFolio', "La SIC {$folio} está en estatus {$res['estatus']}: todavía no puede cubrir la asignación.");

            return;
        }

        $sic = $res['local'];
        $requisicion = $sic ? null : $res['ebs'];
        $lineaId = null;
        $datos = ['sic_folio' => $folio];

        if ($sic) {
            if ($sic->assetAssignments()->where('id', '!=', $assignment->id)->exists()) {
                $this->addError('ligarFolio', 'Esta SIC ya tiene otra asignación: una SIC local cubre un solo equipo.');

                return;
            }

            $reservado = Asset::where('sic_reservada_id', $sic->id)
                ->whereHas('estatus', fn ($q) => $q->where('codigo', 'reservado'))
                ->first();

            if ($reservado && $reservado->id !== $assignment->asset_id) {
                $this->addError('ligarFolio', "Esta SIC ya tiene reservado el activo {$reservado->codigo}: libera esa reserva desde Stock antes de ligarla a otro equipo.");

                return;
            }

            if ($sic->empleado_id && $sic->empleado_id !== $assignment->empleado_id && ! $this->ligarConfirmaEmpleado) {
                $this->addError('ligarFolio', "La SIC es de {$sic->empleado?->nombre} y la asignación es de {$assignment->empleado?->nombre}: confirma que corresponde para ligarla.");

                return;
            }

            $datos['sic_id'] = $sic->id;
            $datos['ticket_id'] = $assignment->ticket_id ?? $sic->ticket_id;
        } else {
            $lineas = ResolutorSic::lineasEbs($requisicion, $assignment->id);

            if ($lineas->count() > 1 && ! $this->ligarLineaId) {
                $this->addError('ligarLineaId', 'Esta SIC trae varios artículos: elige la línea que se entregó.');

                return;
            }

            $lineaId = $lineas->count() === 1 ? $lineas->first()['id'] : $this->ligarLineaId;
            $linea = $lineaId ? $lineas->firstWhere('id', (int) $lineaId) : null;

            if ($lineaId && ! $linea) {
                $this->addError('ligarLineaId', 'La línea elegida no pertenece a esa SIC.');

                return;
            }

            if ($linea && $linea['libres'] <= 0) {
                $this->addError('ligarLineaId', "La línea {$linea['numero']} ya tiene cubiertas sus {$linea['cantidad']} pieza(s).");

                return;
            }

            $datos['ebs_requisition_id'] = $requisicion->id;
            $datos['ebs_requisition_line_id'] = $lineaId;
        }

        $assignment->update($datos + [
            'sic_ligada_at' => now(),
            'sic_ligada_por_user_id' => auth()->id(),
        ]);

        $this->closeLigar();
        session()->flash('status', "SIC {$folio} ligada a la asignación del activo {$assignment->asset?->codigo}.");
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

    /**
     * Equipos reservados (aún sin asignar) para una requisición de EBS: los de la
     * línea concreta si ya se eligió, o los de cualquier línea de la requisición.
     */
    private function reservadosDeEbs(int $requisicionId, $lineaId = null)
    {
        return Asset::query()
            ->whereHas('estatus', fn ($q) => $q->where('codigo', 'reservado'))
            ->when($lineaId, fn ($q) => $q->where('ebs_requisition_line_id', $lineaId), fn ($q) => $q->where('ebs_requisition_id', $requisicionId))
            ->orderBy('codigo')
            ->get();
    }

    private function assetOptionsFor(?int $sicId, ?int $ebsRequisicionId = null, $ebsLineaId = null)
    {
        return Asset::query()
            ->with(['tipoEquipo', 'marca', 'modelo'])
            ->where(function ($q) use ($sicId, $ebsRequisicionId, $ebsLineaId) {
                $q->whereHas('estatus', fn ($q2) => $q2->where('codigo', 'en_stock'));

                if ($sicId) {
                    $q->orWhere(function ($q2) use ($sicId) {
                        $q2->whereHas('estatus', fn ($q3) => $q3->where('codigo', 'reservado'))
                            ->where('sic_reservada_id', $sicId);
                    });
                }

                if ($ebsRequisicionId) {
                    $q->orWhere(function ($q2) use ($ebsRequisicionId, $ebsLineaId) {
                        $q2->whereHas('estatus', fn ($q3) => $q3->where('codigo', 'reservado'))
                            ->when($ebsLineaId, fn ($q3) => $q3->where('ebs_requisition_line_id', $ebsLineaId), fn ($q3) => $q3->where('ebs_requisition_id', $ebsRequisicionId));
                    });
                }
            })
            ->orderBy('codigo')
            ->get();
    }

    public function render()
    {
        $records = AssetAssignment::query()
            ->with(['asset', 'empleado', 'sic', 'ebsRequisition', 'responsableEntrega', 'documentoResponsiva'])
            ->when($this->soloPendientes, function ($q) {
                // Excepciones (asignadas antes de la autorización) que todavía no se cubren.
                $q->where('excepcion_sin_sic', true)->where(function ($q) {
                    $q->where(fn ($q) => $q->whereNull('sic_id')->whereNull('ebs_requisition_id'))
                        ->orWhereHas('sic', fn ($q) => $q->where('estatus', '!=', SolicitudSicBorrador::ESTATUS_AUTORIZADA))
                        ->orWhereHas('ebsRequisition', fn ($q) => $q->where('status', '!=', 'APPROVED'));
                });
            })
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->whereHas('asset', fn ($q2) => $q2->where('codigo', 'like', "%{$this->search}%"))
                        ->orWhereHas('empleado', fn ($q2) => $q2->where('nombre', 'like', "%{$this->search}%"))
                        ->orWhereHas('sic', fn ($q2) => $q2->where('folio_sic', 'like', "%{$this->search}%"))
                        ->orWhere('sic_folio', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('fecha_asignacion')
            ->paginate(10);

        $selectedSicId = $this->form['sic_id'] ?? null;

        // Origen "por folio": se resuelve la SIC (local o requisición de EBS) para
        // mostrar su estatus, sus líneas y filtrar los activos que se pueden usar.
        $resolucion = null;
        $lineasEbs = collect();

        if ($this->showModal && $this->origenSic() === 'ebs' && filled($this->form['sic_folio'] ?? null)) {
            $resolucion = ResolutorSic::porFolio((string) $this->form['sic_folio']);
            $selectedSicId = $resolucion['local']?->id;

            if ($resolucion['tipo'] === 'ebs' && ! $resolucion['local']) {
                $lineasEbs = ResolutorSic::lineasEbs($resolucion['ebs']);
            }
        }

        $resolucionLigar = null;
        $lineasLigar = collect();

        if ($this->showLigarModal && trim($this->ligarFolio) !== '') {
            $resolucionLigar = ResolutorSic::porFolio($this->ligarFolio);

            if ($resolucionLigar['tipo'] === 'ebs' && ! $resolucionLigar['local']) {
                $lineasLigar = ResolutorSic::lineasEbs($resolucionLigar['ebs'], $this->ligandoId);
            }
        }

        return view('gestionti::livewire.inventarios.asignaciones', [
            'resolucion' => $resolucion,
            'lineasEbs' => $lineasEbs,
            'resolucionLigar' => $resolucionLigar,
            'lineasLigar' => $lineasLigar,
            'ligandoAsignacion' => $this->ligandoId ? AssetAssignment::with('empleado', 'asset')->find($this->ligandoId) : null,
            'empleadoOptions' => $this->showModal ? Empleado::where('activo', true)->orderBy('nombre')->get() : collect(),
            'records' => $records,
            'sicOptions' => SolicitudSicBorrador::where('estatus', SolicitudSicBorrador::ESTATUS_AUTORIZADA)
                ->whereDoesntHave('assetAssignments')
                ->with(['empleado', 'tipoEquipo', 'ticket'])
                ->get(),
            'assetOptions' => $this->assetOptionsFor(
                $selectedSicId,
                $lineasEbs->isNotEmpty() ? $resolucion['ebs']->id : null,
                $lineasEbs->isNotEmpty() ? ($this->form['ebs_linea_id'] ?? null) : null,
            ),
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
