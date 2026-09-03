<?php

namespace Modules\GestionTI\Livewire\Inventarios;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\PeriodicidadMantenimiento;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\Validador;

/**
 * Mantenimiento — Preventivo/Correctivo (sección 7.10 del spec original).
 * Ver docs/gestionti-progreso.md, Fase 3 etapa 9, para el diseño completo.
 *
 * Máquina de estados con doble defensa (oculta en la vista + revalidada en
 * cada método), mismo criterio ya usado en `Facturas`/`Asignaciones`/
 * `PresupuestoProyectos\Show::esNivelAccionable()` — cada método
 * re-consulta el `Mantenimiento` fresco desde BD y revalida su `estatus`
 * antes de actuar, no confía solo en que el botón estuvo oculto en la vista.
 *
 * Sin `edit()` — la única forma de tocar un registro ya guardado es a
 * través de las transiciones explícitas (reprogramar/iniciar/completar/
 * cancelar), mismo espíritu que `Recepciones`/`Asignaciones`.
 */
#[Layout('layouts.app')]
class Mantenimientos extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $form = [];

    public bool $showModal = false;

    public string $search = '';

    public string $tipoFilter = '';

    public string $origenFilter = '';

    public string $estatusFilter = '';

    // Modal de reprogramación (programado/reprogramado -> reprogramado).
    public bool $showReprogramarModal = false;

    public ?int $reprogramandoId = null;

    public array $reprogramarForm = [];

    // Modal de completar (en_proceso -> realizado).
    public bool $showCompletarModal = false;

    public ?int $completandoId = null;

    public ?Mantenimiento $completandoRecord = null;

    public array $completarForm = [];

    public $completarAdjunto;

    protected function rules(): array
    {
        return [
            'form.asset_id' => 'required|exists:assets,id',
            'form.tipo' => ['required', Rule::in([Mantenimiento::TIPO_PREVENTIVO, Mantenimiento::TIPO_CORRECTIVO])],
            'form.ticket_id' => 'nullable|exists:tickets,id',
            'form.origen_ejecucion' => ['required', Rule::in([Mantenimiento::ORIGEN_INTERNO, Mantenimiento::ORIGEN_EXTERNO])],
            'form.vendor_id' => 'nullable|exists:proveedores,id',
            'form.fecha_programada' => 'nullable|date',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTipoFilter(): void
    {
        $this->resetPage();
    }

    public function updatingOrigenFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEstatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Catch-all de Livewire para reaccionar a cambios de propiedades
     * anidadas (`form.asset_id`/`form.tipo`) — mismo patrón ya documentado
     * en `Recepciones::updated()`/`Asignaciones::updated()`. Recalcula la
     * fecha sugerida cuando cualquiera de las 2 condiciones que la disparan
     * cambia.
     */
    public function updated($name): void
    {
        if (in_array($name, ['form.asset_id', 'form.tipo'], true)) {
            $this->suggestFechaProgramada();
        }
    }

    /**
     * Sugiere `fecha_programada` = hoy + `PeriodicidadMantenimiento.meses_sugeridos`
     * (buscada por `tipo_equipo_id` del Asset elegido) cuando `tipo =
     * preventivo` y hay un Asset seleccionado — editable, no forzada (el
     * usuario puede sobrescribirla libremente después). Si no hay una
     * periodicidad activa para ese tipo de equipo, deja la fecha vacía sin
     * error.
     */
    private function suggestFechaProgramada(): void
    {
        if (($this->form['tipo'] ?? null) !== Mantenimiento::TIPO_PREVENTIVO) {
            return;
        }

        $assetId = $this->form['asset_id'] ?? null;

        if (! $assetId) {
            return;
        }

        $asset = Asset::find($assetId);

        if (! $asset || ! $asset->tipo_equipo_id) {
            return;
        }

        $periodicidad = PeriodicidadMantenimiento::where('tipo_equipo_id', $asset->tipo_equipo_id)
            ->where('activo', true)
            ->first();

        if (! $periodicidad) {
            return;
        }

        $this->form['fecha_programada'] = now()->addMonths($periodicidad->meses_sugeridos)->format('Y-m-d');
    }

    /**
     * Ningún select opcional de este formulario manda '' como "Sin
     * asignar" salvo estos 2 (FKs verdaderamente opcionales) — normalizados
     * aquí antes de validar/guardar, mismo `nullifyEmptyForeignKeys()` ya
     * documentado repetidamente en este módulo. `vendor_id` también se
     * ignora explícitamente en `save()` cuando `origen_ejecucion = interno`,
     * aunque se haya mandado algo.
     */
    private function nullifyEmptyForeignKeys(): void
    {
        foreach (['ticket_id', 'vendor_id'] as $field) {
            if (($this->form[$field] ?? null) === '') {
                $this->form[$field] = null;
            }
        }
    }

    /**
     * Validación cruzada dependiente de `origen_ejecucion` — no expresable
     * con reglas planas de `rules()`, mismo patrón `validateDestino()`/
     * `validateLineas()` ya usado en `RegistroManual`/`Recepciones`/
     * `SolicitudesProveedor`.
     */
    private function validateOrigenEjecucion(): void
    {
        if (($this->form['origen_ejecucion'] ?? null) === Mantenimiento::ORIGEN_EXTERNO
            && empty($this->form['vendor_id'])) {
            $this->addError('form.vendor_id', 'Selecciona el proveedor que realizará el mantenimiento.');
        }
    }

    public function create(): void
    {
        $this->form = [
            'asset_id' => null,
            'tipo' => null,
            'ticket_id' => null,
            'origen_ejecucion' => null,
            'vendor_id' => null,
            'fecha_programada' => null,
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->form = [];
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->nullifyEmptyForeignKeys();
        $this->validate();
        $this->validateOrigenEjecucion();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        Mantenimiento::create([
            'asset_id' => $this->form['asset_id'],
            'tipo' => $this->form['tipo'],
            'ticket_id' => $this->form['ticket_id'],
            'origen_ejecucion' => $this->form['origen_ejecucion'],
            'vendor_id' => $this->form['origen_ejecucion'] === Mantenimiento::ORIGEN_EXTERNO
                ? $this->form['vendor_id']
                : null,
            'fecha_programada' => ($this->form['fecha_programada'] ?? '') !== ''
                ? $this->form['fecha_programada']
                : null,
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        $this->cancel();
        session()->flash('status', 'Mantenimiento programado correctamente.');
    }

    public function openReprogramar(int $id): void
    {
        $record = Mantenimiento::findOrFail($id);

        if (! in_array($record->estatus, [Mantenimiento::ESTATUS_PROGRAMADO, Mantenimiento::ESTATUS_REPROGRAMADO], true)) {
            return;
        }

        $this->reprogramandoId = $id;
        $this->reprogramarForm = [
            'fecha_programada' => optional($record->fecha_programada)->format('Y-m-d'),
            'motivo' => null,
        ];
        $this->resetValidation();
        $this->showReprogramarModal = true;
    }

    public function confirmReprogramar(): void
    {
        // Sin `reprogramandoId` no hay nada que reprogramar — mismo guard
        // defensivo que `confirmCompletar()`.
        if ($this->reprogramandoId === null) {
            return;
        }

        $this->validate([
            'reprogramarForm.fecha_programada' => 'required|date',
            'reprogramarForm.motivo' => 'nullable|string',
        ]);

        // Defensa contra condición de carrera: se vuelve a resolver el
        // registro fresco desde BD y se revalida el estatus, no solo se
        // confía en que el botón estuvo oculto en la vista — mismo patrón
        // ya usado en `Asignaciones::save()`/`Stock::confirmReassign()`.
        $record = Mantenimiento::findOrFail($this->reprogramandoId);

        if (! in_array($record->estatus, [Mantenimiento::ESTATUS_PROGRAMADO, Mantenimiento::ESTATUS_REPROGRAMADO], true)) {
            $this->cancelReprogramar();

            return;
        }

        $nuevaFecha = $this->reprogramarForm['fecha_programada'];

        if (optional($record->fecha_programada)->format('Y-m-d') === $nuevaFecha) {
            $this->addError('reprogramarForm.fecha_programada', 'La nueva fecha debe ser distinta a la actual.');

            return;
        }

        $record->update([
            'fecha_programada' => $nuevaFecha,
            'estatus' => Mantenimiento::ESTATUS_REPROGRAMADO,
        ]);

        $this->cancelReprogramar();
        session()->flash('status', 'Mantenimiento reprogramado correctamente.');
    }

    public function cancelReprogramar(): void
    {
        $this->showReprogramarModal = false;
        $this->reprogramandoId = null;
        $this->reprogramarForm = [];
        $this->resetValidation();
    }

    public function iniciar(int $id): void
    {
        $record = Mantenimiento::findOrFail($id);

        if (! in_array($record->estatus, [Mantenimiento::ESTATUS_PROGRAMADO, Mantenimiento::ESTATUS_REPROGRAMADO], true)) {
            return;
        }

        $record->update(['estatus' => Mantenimiento::ESTATUS_EN_PROCESO]);
        session()->flash('status', 'Mantenimiento iniciado.');
    }

    public function openCompletar(int $id): void
    {
        $record = Mantenimiento::findOrFail($id);

        if ($record->estatus !== Mantenimiento::ESTATUS_EN_PROCESO) {
            return;
        }

        $this->completandoId = $id;
        $this->completandoRecord = $record;
        $this->completarForm = [
            'fecha_realizada' => now()->format('Y-m-d'),
            'diagnostico' => null,
            'costo' => null,
            'realizado_por_id' => null,
        ];
        $this->completarAdjunto = null;
        $this->resetValidation();
        $this->showCompletarModal = true;
    }

    /**
     * Reglas condicionadas por `origen_ejecucion` del registro (no del
     * formulario de completar, que no lo vuelve a preguntar) — `costo`
     * requerido solo si `externo`, `realizado_por_id` requerido solo si
     * `interno`.
     */
    private function rulesCompletar(Mantenimiento $record): array
    {
        $rules = [
            'completarForm.fecha_realizada' => 'required|date',
            'completarForm.diagnostico' => 'required|string',
            'completarAdjunto' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];

        if ($record->origen_ejecucion === Mantenimiento::ORIGEN_EXTERNO) {
            $rules['completarForm.costo'] = 'required|numeric|min:0';
        } else {
            $rules['completarForm.realizado_por_id'] = 'required|exists:validadores,id';
        }

        return $rules;
    }

    public function confirmCompletar(): void
    {
        // Sin `completandoId` no hay nada que completar — solo puede pasar
        // si este método se invoca directamente sin haber pasado antes por
        // `openCompletar()` (que es lo único que lo asigna). Guard
        // defensivo antes de la defensa real contra condición de carrera
        // (mismo criterio que `confirmReprogramar()`), que sí necesita un
        // id para revalidar el estatus.
        if ($this->completandoId === null) {
            return;
        }

        $record = Mantenimiento::findOrFail($this->completandoId);

        if ($record->estatus !== Mantenimiento::ESTATUS_EN_PROCESO) {
            $this->cancelCompletar();

            return;
        }

        $this->validate($this->rulesCompletar($record));

        DB::transaction(function () use ($record) {
            $documentoId = $record->documento_id;

            if ($this->completarAdjunto) {
                $documento = DocumentoDigitalizado::storeUploaded(
                    $this->completarAdjunto,
                    $record,
                    'orden_servicio',
                    auth()->id()
                );
                $documentoId = $documento->id;
            }

            $record->update([
                'fecha_realizada' => $this->completarForm['fecha_realizada'],
                'diagnostico' => $this->completarForm['diagnostico'],
                'costo' => $record->origen_ejecucion === Mantenimiento::ORIGEN_EXTERNO
                    ? $this->completarForm['costo']
                    : null,
                'realizado_por_id' => $record->origen_ejecucion === Mantenimiento::ORIGEN_INTERNO
                    ? $this->completarForm['realizado_por_id']
                    : null,
                'documento_id' => $documentoId,
                'estatus' => Mantenimiento::ESTATUS_REALIZADO,
            ]);
        });

        $this->cancelCompletar();
        session()->flash('status', 'Mantenimiento completado correctamente.');
    }

    public function cancelCompletar(): void
    {
        $this->showCompletarModal = false;
        $this->completandoId = null;
        $this->completandoRecord = null;
        $this->completarForm = [];
        $this->completarAdjunto = null;
        $this->resetValidation();
    }

    public function cancelar(int $id): void
    {
        $record = Mantenimiento::findOrFail($id);

        if (! in_array($record->estatus, [
            Mantenimiento::ESTATUS_PROGRAMADO,
            Mantenimiento::ESTATUS_REPROGRAMADO,
            Mantenimiento::ESTATUS_EN_PROCESO,
        ], true)) {
            return;
        }

        $record->update(['estatus' => Mantenimiento::ESTATUS_CANCELADO]);
        session()->flash('status', 'Mantenimiento cancelado.');
    }

    /**
     * Etiqueta legible para el select de Activo. Pública porque se invoca
     * desde la vista Blade, mismo criterio que `Asignaciones::assetOptionLabel()`.
     */
    public function assetOptionLabel(Asset $asset): string
    {
        $tipo = $asset->tipoEquipo?->nombre ?? 'Sin tipo';

        return "{$asset->codigo} — {$tipo}";
    }

    public function render()
    {
        $records = Mantenimiento::query()
            ->with(['asset.tipoEquipo', 'vendor', 'realizadoPor'])
            ->when($this->tipoFilter !== '', fn ($q) => $q->where('tipo', $this->tipoFilter))
            ->when($this->origenFilter !== '', fn ($q) => $q->where('origen_ejecucion', $this->origenFilter))
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('estatus', $this->estatusFilter))
            ->when($this->search !== '', function ($q) {
                $q->whereHas('asset', fn ($q2) => $q2->where('codigo', 'like', "%{$this->search}%"));
            })
            ->orderByDesc('fecha_programada')
            ->paginate(10);

        return view('gestionti::livewire.inventarios.mantenimientos', [
            'records' => $records,
            'assetOptions' => Asset::with('tipoEquipo')->orderBy('codigo')->get(),
            'ticketOptions' => Ticket::orderByDesc('fecha')->get(),
            'vendorOptions' => Proveedor::where('activo', true)->orderBy('nombre_comercial')->get(),
            'validadorOptions' => Validador::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
