<?php

namespace Modules\GestionTI\Livewire\Inventarios;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetSicReservationLog;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\StockMovement;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;

/**
 * Stock (sección 7.11 del spec original) — ver docs/gestionti-progreso.md,
 * Fase 3 etapa 7, para el diseño completo.
 *
 * Nota de diseño sobre el filtro de "empresa": `Asset` NO tiene una columna
 * `empresa_id` propia (a diferencia de `Empleado`) — no es un dato que un
 * activo tenga hasta que se asigna a alguien. El filtro se resuelve vía
 * `whereHas('assignments', ...)` sobre la asignación activa (sin
 * `fecha_devolucion`) del empleado destinatario, así que en la práctica solo
 * tiene efecto sobre activos `asignado`. No es un bug, es el reflejo fiel del
 * esquema ya cerrado en fases anteriores.
 */
#[Layout('layouts.app')]
class Stock extends Component
{
    use WithPagination;

    public string $search = '';

    public string $ubicacionFilter = '';

    public string $tipoEquipoFilter = '';

    public string $marcaFilter = '';

    public string $empresaFilter = '';

    public string $estatusFilter = '';

    // Modal de reasignación de SIC (excepciones sobre un activo `reservado`).
    public bool $showReassignModal = false;

    public ?int $reassigningAssetId = null;

    public array $reassignForm = [];

    // Modal de traslado entre almacenes (sobre `en_stock`/`reservado`).
    public bool $showTrasladoModal = false;

    public ?int $trasladandoAssetId = null;

    public array $trasladoForm = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingUbicacionFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTipoEquipoFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMarcaFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEmpresaFilter(): void
    {
        $this->resetPage();
    }

    public function updatingEstatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Etiqueta legible para el select de SIC — mismo criterio/formato ya
     * usado por `Asignaciones::sicOptionLabel()`.
     */
    public function sicOptionLabel(SolicitudSicBorrador $sic): string
    {
        $folio = $sic->folio_sic ?: "SIC #{$sic->id}";

        return "{$folio} — {$sic->empleado?->nombre} — {$sic->tipoEquipo?->nombre}";
    }

    /**
     * SIC "autorizada pendiente de asignación" — mismo criterio exacto ya
     * usado en `Asignaciones::render()` para el select de SIC.
     */
    private function sicReservationOptions()
    {
        return SolicitudSicBorrador::where('estatus', SolicitudSicBorrador::ESTATUS_AUTORIZADA)
            ->whereDoesntHave('assetAssignments')
            ->with(['empleado', 'tipoEquipo'])
            ->get();
    }

    public function openReassign(int $assetId): void
    {
        $asset = Asset::with('estatus')->findOrFail($assetId);

        if ($asset->estatus?->codigo !== 'reservado') {
            return;
        }

        $this->reassigningAssetId = $assetId;
        $this->reassignForm = [
            'sic_nueva_id' => null,
            'motivo' => null,
        ];
        $this->resetValidation();
        $this->showReassignModal = true;
    }

    public function confirmReassign(): void
    {
        $this->validate([
            'reassignForm.sic_nueva_id' => 'required|exists:solicitudes_sic_borrador,id',
            'reassignForm.motivo' => 'required|string',
        ]);

        // Defensa contra condición de carrera: se vuelve a resolver el
        // Activo fresco desde BD y se revalida el estatus, no solo se
        // confía en que el botón estuvo oculto en la vista — mismo patrón
        // ya usado en `Asignaciones::save()`.
        $asset = Asset::with('estatus')->findOrFail($this->reassigningAssetId);

        if ($asset->estatus?->codigo !== 'reservado') {
            $this->cancelReassign();

            return;
        }

        DB::transaction(function () use ($asset) {
            AssetSicReservationLog::create([
                'asset_id' => $asset->id,
                'sic_anterior_id' => $asset->sic_reservada_id,
                'sic_nueva_id' => $this->reassignForm['sic_nueva_id'],
                'motivo' => $this->reassignForm['motivo'],
                'usuario_id' => auth()->id(),
            ]);

            $asset->update(['sic_reservada_id' => $this->reassignForm['sic_nueva_id']]);
        });

        $this->cancelReassign();
        session()->flash('status', 'SIC reasignada correctamente.');
    }

    public function cancelReassign(): void
    {
        $this->showReassignModal = false;
        $this->reassigningAssetId = null;
        $this->reassignForm = [];
        $this->resetValidation();
    }

    public function openTraslado(int $assetId): void
    {
        $asset = Asset::with('estatus')->findOrFail($assetId);

        if (! in_array($asset->estatus?->codigo, ['en_stock', 'reservado'], true)) {
            return;
        }

        $this->trasladandoAssetId = $assetId;
        $this->trasladoForm = [
            'ubicacion_destino_id' => null,
            'comentarios' => null,
        ];
        $this->resetValidation();
        $this->showTrasladoModal = true;
    }

    public function confirmTraslado(): void
    {
        $this->validate([
            'trasladoForm.ubicacion_destino_id' => 'required|exists:ubicaciones,id',
            'trasladoForm.comentarios' => 'nullable|string',
        ]);

        // Defensa contra condición de carrera, mismo criterio que
        // `confirmReassign()`/`Asignaciones::save()`.
        $asset = Asset::with('estatus')->findOrFail($this->trasladandoAssetId);

        if (! in_array($asset->estatus?->codigo, ['en_stock', 'reservado'], true)) {
            $this->cancelTraslado();

            return;
        }

        if ((int) $this->trasladoForm['ubicacion_destino_id'] === (int) $asset->ubicacion_actual_id) {
            $this->addError('trasladoForm.ubicacion_destino_id', 'Selecciona una ubicación distinta a la actual.');

            return;
        }

        DB::transaction(function () use ($asset) {
            StockMovement::create([
                'asset_id' => $asset->id,
                'tipo' => StockMovement::TIPO_TRASLADO,
                'fecha' => now()->format('Y-m-d'),
                'usuario_responsable_id' => auth()->id(),
                'referencia_tipo' => 'manual',
                'ubicacion_origen_id' => $asset->ubicacion_actual_id,
                'ubicacion_destino_id' => $this->trasladoForm['ubicacion_destino_id'],
                'comentarios' => ($this->trasladoForm['comentarios'] ?? '') !== ''
                    ? $this->trasladoForm['comentarios']
                    : null,
            ]);

            $asset->update(['ubicacion_actual_id' => $this->trasladoForm['ubicacion_destino_id']]);
        });

        $this->cancelTraslado();
        session()->flash('status', 'Traslado registrado correctamente.');
    }

    public function cancelTraslado(): void
    {
        $this->showTrasladoModal = false;
        $this->trasladandoAssetId = null;
        $this->trasladoForm = [];
        $this->resetValidation();
    }

    /**
     * Alertas de mínimos (spec 7.11, línea 127): stock libre = solo
     * `en_stock` — NO cuenta `reservado` ni `asignado`. El cálculo real vive
     * en `StockMinimo::enBreach()` (extraído en Fase 4 para que
     * `RevisarAvisosProgramadosCommand` lo reutilice sin duplicar la regla)
     * — aquí solo se adapta al shape que ya espera la vista de esta pantalla.
     */
    private function alertasMinimos()
    {
        return StockMinimo::enBreach()->map(fn (array $item) => [
            'tipo_equipo' => $item['minimo']->tipoEquipo?->nombre ?? 'Sin tipo',
            'ubicacion' => $item['minimo']->ubicacion?->nombre ?? 'Sin ubicación',
            'stock_actual' => $item['stock_actual'],
            'cantidad_minima' => $item['minimo']->cantidad_minima,
        ]);
    }

    public function render()
    {
        $records = Asset::query()
            ->with(['tipoEquipo', 'marca', 'modelo', 'ubicacionActual', 'estatus', 'sicReservada'])
            ->when($this->ubicacionFilter !== '', fn ($q) => $q->where('ubicacion_actual_id', $this->ubicacionFilter))
            ->when($this->tipoEquipoFilter !== '', fn ($q) => $q->where('tipo_equipo_id', $this->tipoEquipoFilter))
            ->when($this->marcaFilter !== '', fn ($q) => $q->where('marca_id', $this->marcaFilter))
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('estatus_id', $this->estatusFilter))
            ->when($this->empresaFilter !== '', function ($q) {
                $q->whereHas('assignments', function ($q2) {
                    $q2->whereNull('fecha_devolucion')
                        ->whereHas('empleado', fn ($q3) => $q3->where('empresa_id', $this->empresaFilter));
                });
            })
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('codigo', 'like', "%{$this->search}%")
                        ->orWhere('numero_serie', 'like', "%{$this->search}%")
                        ->orWhere('service_tag', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('codigo')
            ->paginate(10);

        $trasladandoAsset = $this->trasladandoAssetId
            ? Asset::find($this->trasladandoAssetId)
            : null;

        return view('gestionti::livewire.inventarios.stock', [
            'records' => $records,
            'alertasMinimos' => $this->alertasMinimos(),
            'ubicacionOptions' => Ubicacion::where('activo', true)->orderBy('nombre')->get(),
            'tipoEquipoOptions' => TipoEquipo::where('activo', true)->orderBy('nombre')->get(),
            'marcaOptions' => Marca::where('activo', true)->orderBy('nombre')->get(),
            'empresaOptions' => Empresa::where('activo', true)->orderBy('nombre_comercial')->get(),
            'estatusOptions' => EstatusActivo::where('activo', true)->orderBy('nombre')->get(),
            'sicReservationOptions' => $this->sicReservationOptions(),
            'ubicacionDestinoOptions' => Ubicacion::where('activo', true)
                ->when($trasladandoAsset, fn ($q) => $q->where('id', '!=', $trasladandoAsset->ubicacion_actual_id))
                ->orderBy('nombre')
                ->get(),
        ]);
    }
}
