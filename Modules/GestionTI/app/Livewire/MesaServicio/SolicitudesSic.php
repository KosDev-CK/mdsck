<?php

namespace Modules\GestionTI\Livewire\MesaServicio;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\UnidadNegocio;
use Modules\GestionTI\Support\Avisos\AvisoDispatcher;

#[Layout('layouts.app')]
class SolicitudesSic extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [];

    /**
     * Adjunto de la SIC (`tipo_documento = 'sic'` en `DocumentoDigitalizado`)
     * — propiedad de nivel superior, no dentro de `$form`, mismo convenio de
     * `App\Livewire\Branding\Manage` para uploads de Livewire.
     */
    public $adjunto;

    public ?DocumentoDigitalizado $currentAdjunto = null;

    #[Url(as: 'search')]
    public string $search = '';

    public string $estatusFilter = '';

    public bool $showModal = false;

    // Modal secundario para la transición capturado -> sic_creada, que
    // necesita capturar el folio de EBS antes de avanzar.
    public bool $showAdvanceModal = false;

    public ?int $advancingId = null;

    public string $advanceFolioSic = '';

    /**
     * Fase 5, punto 1 (EBS) — "buscar y elegir" una `EbsRequisition` ya
     * importada en vez de escribir el folio a mano. Completamente opcional:
     * si se deja vacío, el camino de texto libre de `advanceFolioSic` sigue
     * funcionando exactamente igual que antes (respaldo manual, nunca se
     * pierde). Al elegir una, `updatedAdvanceEbsRequisitionId()` autocompleta
     * `advanceFolioSic` con su código.
     */
    public ?int $advanceEbsRequisitionId = null;

    public string $ebsRequisicionSearch = '';

    protected function rules(): array
    {
        return [
            'form.ticket_id' => 'required|exists:tickets,id',
            'form.empleado_id' => 'required|exists:empleados,id',
            'form.tipo_equipo_id' => 'required|exists:tipos_equipo,id',
            'form.motivo' => 'required|string',
            'form.especificaciones_requeridas' => 'nullable|string',
            'form.centro_costo_id' => 'required|exists:centros_costo,id',
            'form.unidad_negocio_id' => 'nullable|exists:unidades_negocio,id',
            'form.urgencia' => ['required', Rule::in(SolicitudSicBorrador::URGENCIAS)],
            'form.fecha_solicitud' => 'required|date',
            'adjunto' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
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
     * El select opcional de Unidad de Negocio manda '' para "Sin asignar" —
     * normalizar a null antes de validar/guardar (mismo bug/fix ya
     * documentado en Empleados.php/Compras.php de Fase 1).
     */
    private function nullifyEmptyForeignKeys(): void
    {
        if (($this->form['unidad_negocio_id'] ?? null) === '') {
            $this->form['unidad_negocio_id'] = null;
        }
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [
            'ticket_id' => null,
            'empleado_id' => null,
            'tipo_equipo_id' => null,
            'motivo' => null,
            'especificaciones_requeridas' => null,
            'centro_costo_id' => null,
            'unidad_negocio_id' => null,
            'urgencia' => null,
            'fecha_solicitud' => now()->format('Y-m-d'),
        ];
        $this->adjunto = null;
        $this->currentAdjunto = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = SolicitudSicBorrador::findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'ticket_id' => $record->ticket_id,
            'empleado_id' => $record->empleado_id,
            'tipo_equipo_id' => $record->tipo_equipo_id,
            'motivo' => $record->motivo,
            'especificaciones_requeridas' => $record->especificaciones_requeridas,
            'centro_costo_id' => $record->centro_costo_id,
            'unidad_negocio_id' => $record->unidad_negocio_id,
            'urgencia' => $record->urgencia,
            'fecha_solicitud' => optional($record->fecha_solicitud)->format('Y-m-d'),
        ];
        $this->adjunto = null;
        $this->currentAdjunto = $record->documentoAdjunto();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->nullifyEmptyForeignKeys();
        $this->validate();

        if ($this->editingId) {
            $record = SolicitudSicBorrador::findOrFail($this->editingId);
            $record->update($this->form);
        } else {
            $record = SolicitudSicBorrador::create(array_merge($this->form, [
                'estatus' => SolicitudSicBorrador::ESTATUS_CAPTURADO,
            ]));
        }

        if ($this->adjunto) {
            DocumentoDigitalizado::storeUploaded($this->adjunto, $record, 'sic', auth()->id());
        }

        $this->showModal = false;
        $this->adjunto = null;
        session()->flash('status', 'Guardado correctamente.');
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->form = [];
        $this->adjunto = null;
        $this->currentAdjunto = null;
        $this->resetValidation();
    }

    public function openAdvance(int $id): void
    {
        $record = SolicitudSicBorrador::findOrFail($id);

        if ($record->estatus !== SolicitudSicBorrador::ESTATUS_CAPTURADO) {
            return;
        }

        $this->advancingId = $id;
        $this->advanceFolioSic = $record->folio_sic ?? '';
        $this->advanceEbsRequisitionId = $record->ebs_requisition_id;
        $this->ebsRequisicionSearch = '';
        $this->resetValidation();
        $this->showAdvanceModal = true;
    }

    /**
     * Al elegir una `EbsRequisition` de la lista, autocompleta el folio con
     * su código — el usuario puede seguir editándolo a mano después si
     * quiere, la vinculación en `confirmAdvanceToSicCreada()` no depende de
     * que el texto siga coincidiendo.
     */
    public function updatedAdvanceEbsRequisitionId($value): void
    {
        if ($value === '' || $value === null) {
            return;
        }

        $ebsRequisicion = EbsRequisition::find($value);

        if ($ebsRequisicion) {
            $this->advanceFolioSic = (string) $ebsRequisicion->code;
        }
    }

    public function confirmAdvanceToSicCreada(): void
    {
        $this->validate([
            'advanceFolioSic' => 'required|string|max:255',
            'advanceEbsRequisitionId' => 'nullable|integer|exists:ebs_requisitions,id',
        ]);

        $record = SolicitudSicBorrador::findOrFail($this->advancingId);

        if ($record->estatus !== SolicitudSicBorrador::ESTATUS_CAPTURADO) {
            $this->showAdvanceModal = false;

            return;
        }

        // Si se eligió una requisición de EBS ya vinculada a OTRA SIC, no se
        // reasigna — la unique() de la columna lo rechazaría con un
        // QueryException feo si se dejara pasar.
        if ($this->advanceEbsRequisitionId) {
            $yaVinculada = SolicitudSicBorrador::where('ebs_requisition_id', $this->advanceEbsRequisitionId)
                ->where('id', '!=', $record->id)
                ->exists();

            if ($yaVinculada) {
                $this->addError('advanceEbsRequisitionId', 'Esa requisición de EBS ya está vinculada a otra Solicitud de SIC.');

                return;
            }
        }

        $update = [
            'folio_sic' => $this->advanceFolioSic,
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
        ];

        // Camino de texto libre (sin seleccionar ninguna EbsRequisition):
        // se comporta exactamente igual que antes, sin tocar
        // ebs_requisition_id.
        if ($this->advanceEbsRequisitionId) {
            $update['ebs_requisition_id'] = $this->advanceEbsRequisitionId;
        }

        $record->update($update);

        $this->cancelAdvance();
        session()->flash('status', 'SIC creada registrada correctamente.');
    }

    public function cancelAdvance(): void
    {
        $this->showAdvanceModal = false;
        $this->advancingId = null;
        $this->advanceFolioSic = '';
        $this->advanceEbsRequisitionId = null;
        $this->ebsRequisicionSearch = '';
        $this->resetValidation();
    }

    public function marcarAutorizada(int $id): void
    {
        $record = SolicitudSicBorrador::with('empleado')->findOrFail($id);

        if ($record->estatus !== SolicitudSicBorrador::ESTATUS_SIC_CREADA) {
            return;
        }

        $record->update(['estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA]);

        app(AvisoDispatcher::class)->disparar(
            TipoAviso::EVENTO_SIC_AUTORIZADA,
            $record,
            solicitante: $record->empleado,
            variables: [
                'folio' => $record->folio_sic ?? "SIC #{$record->id}",
                'empleado' => $record->empleado?->nombre,
            ]
        );

        session()->flash('status', 'Solicitud marcada como autorizada.');
    }

    public function marcarRechazada(int $id): void
    {
        $record = SolicitudSicBorrador::with('empleado')->findOrFail($id);

        if ($record->estatus !== SolicitudSicBorrador::ESTATUS_SIC_CREADA) {
            return;
        }

        $record->update(['estatus' => SolicitudSicBorrador::ESTATUS_RECHAZADA]);

        app(AvisoDispatcher::class)->disparar(
            TipoAviso::EVENTO_SIC_RECHAZADA,
            $record,
            solicitante: $record->empleado,
            variables: [
                'folio' => $record->folio_sic ?? "SIC #{$record->id}",
                'empleado' => $record->empleado?->nombre,
            ]
        );

        session()->flash('status', 'Solicitud marcada como rechazada.');
    }

    /**
     * PDF de respaldo/archivo de la solicitud tal como fue capturada — sin
     * cláusula legal ni firmas (a diferencia de la responsiva de
     * `Asignaciones::exportResponsivaPdf()`), disponible en cualquier
     * estatus. Mismo patrón: `Pdf::loadView(...)` + `streamDownload(...)`.
     */
    public function exportSicPdf(int $id)
    {
        $solicitud = SolicitudSicBorrador::with([
            'ticket', 'empleado.puesto', 'empleado.area', 'empleado.ubicacion',
            'empleado.empresa', 'empleado.jefeInmediato', 'tipoEquipo',
            'centroCosto', 'unidadNegocio',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('gestionti::pdf.formato-sic', ['solicitud' => $solicitud]);

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'formato-sic-'.($solicitud->folio_sic ?: $solicitud->id).'.pdf'
        );
    }

    public function render()
    {
        $records = SolicitudSicBorrador::query()
            ->with(['ticket', 'empleado', 'tipoEquipo', 'centroCosto'])
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('estatus', $this->estatusFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('folio_sic', 'like', "%{$this->search}%")
                        ->orWhereHas('empleado', fn ($q) => $q->where('nombre', 'like', "%{$this->search}%"))
                        ->orWhereHas('ticket', fn ($q) => $q->where('sdp_display_id', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('fecha_solicitud')
            ->paginate(10);

        // Opciones de EbsRequisition para el "buscar y elegir" del modal de
        // avance — excluye las ya vinculadas a OTRA SIC, pero incluye la que
        // ya está vinculada a ESTA (si `openAdvance()` la precargó).
        $ebsRequisicionOptions = EbsRequisition::query()
            ->when($this->ebsRequisicionSearch !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('code', 'like', "%{$this->ebsRequisicionSearch}%")
                        ->orWhere('description', 'like', "%{$this->ebsRequisicionSearch}%");
                });
            })
            ->where(function ($q) {
                $q->whereDoesntHave('solicitudSicBorrador')
                    ->orWhere('id', $this->advanceEbsRequisitionId);
            })
            ->orderByDesc('fecha_creacion')
            ->limit(20)
            ->get();

        return view('gestionti::livewire.mesa-servicio.solicitudes-sic', [
            'records' => $records,
            'ticketOptions' => Ticket::orderByDesc('fecha')->get(),
            'empleadoOptions' => Empleado::where('activo', true)->orderBy('nombre')->get(),
            'tipoEquipoOptions' => TipoEquipo::where('activo', true)->orderBy('nombre')->get(),
            'centroCostoOptions' => CentroCosto::where('activo', true)->orderBy('nombre')->get(),
            'unidadNegocioOptions' => UnidadNegocio::where('activo', true)->orderBy('nombre')->get(),
            'ebsRequisicionOptions' => $ebsRequisicionOptions,
        ]);
    }
}
