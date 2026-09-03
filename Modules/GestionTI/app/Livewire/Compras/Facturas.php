<?php

namespace Modules\GestionTI\Livewire\Compras;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\RecepcionLinea;
use Modules\GestionTI\Models\SolicitudProveedor;

/**
 * Facturación (sección 7.9 del spec original) — SIN Orden de Compra. Ver
 * docs/gestionti-progreso.md, Fase 3 etapa 6, para el recorte de alcance
 * explícito (decisión del usuario, no un gap): esta pantalla solo registra
 * manualmente las facturas que el proveedor entrega junto con la
 * mercancía/remisión — nada de integración real con el ERP externo
 * todavía; `PurchaseOrder` no se construye en este módulo.
 *
 * Patrón lista+modal (como SolicitudesProveedor/Recepciones), no
 * lista+detalle — esta pantalla no tiene un flujo colaborativo de varios
 * días como Presupuesto por Proyecto.
 */
#[Layout('layouts.app')]
class Facturas extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $editingId = null;

    public array $form = [];

    /**
     * Recepciones (remisiones) actualmente marcadas con checkbox — array
     * plano de ids, atado vía `wire:model` a los checkboxes del mismo
     * nombre (soporte nativo de Livewire para checkboxes múltiples sobre un
     * mismo arreglo).
     *
     * @var array<int, int>
     */
    public array $recepcionIds = [];

    /** Adjunto (factura digitalizada) — propiedad de nivel superior, mismo convenio de WithFileUploads ya usado en el resto del módulo. */
    public $adjunto;

    public ?DocumentoDigitalizado $currentAdjunto = null;

    #[Url(as: 'search')]
    public string $search = '';

    public string $estatusFilter = '';

    public bool $soloDiferencia = false;

    public bool $showModal = false;

    protected function rules(): array
    {
        return [
            'form.folio_factura' => [
                'required',
                'string',
                'max:100',
                // Único POR PROVEEDOR, no globalmente — 2 proveedores
                // distintos pueden compartir numeración de folio (mismo
                // patrón que Rule::unique('stocks_minimos', ...)->where(...)
                // de Fase 1).
                Rule::unique('invoices', 'folio_factura')
                    ->where('vendor_id', $this->form['vendor_id'] ?? null)
                    ->ignore($this->editingId),
            ],
            'form.vendor_id' => 'required|exists:proveedores,id',
            'form.fecha_recepcion' => 'required|date',
            'form.monto_total' => 'required|numeric|min:0',
            'form.moneda' => ['required', Rule::in(Invoice::MONEDAS)],
            'form.partida_presupuestal' => 'nullable|string|max:255',
            'form.ejercicio_fiscal' => 'nullable|string|max:50',
            'recepcionIds' => 'nullable|array',
            'recepcionIds.*' => 'exists:recepciones,id',
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

    public function updatingSoloDiferencia(): void
    {
        $this->resetPage();
    }

    /**
     * Catch-all de Livewire para reaccionar a cambios de propiedades
     * anidadas (`form.vendor_id`) — mismo patrón ya documentado en
     * Recepciones::updated()/Asignaciones::updated(). Al cambiar de
     * proveedor se descartan de la selección las remisiones que ya no
     * pertenecen a él (la lista de opciones se recalcula sola en cada
     * render() vía recepcionesDisponibles()).
     */
    public function updated($name): void
    {
        if ($name === 'form.vendor_id') {
            $idsValidos = $this->recepcionesDisponibles()->pluck('id')->all();
            $this->recepcionIds = array_values(array_intersect($this->recepcionIds, $idsValidos));
        }
    }

    private function recepcionesDisponibles()
    {
        $vendorId = $this->form['vendor_id'] ?? null;

        if (! $vendorId) {
            return collect();
        }

        return Recepcion::whereHas('solicitudProveedor', fn ($q) => $q->where('vendor_id', $vendorId))
            ->with('solicitudProveedor')
            ->orderByDesc('fecha_recepcion')
            ->get();
    }

    /**
     * Suma, sobre TODAS las RecepcionLinea de las Recepcion dadas,
     * `cantidad_recibida * (solicitudProveedorLinea.precio_unitario_cotizado ?? 0)`
     * — misma fórmula usada tanto para la vista previa en vivo del
     * formulario como para persistir `diferencia_a_revisar` en `save()`.
     */
    private function totalCotizadoDe(array $recepcionIds): float
    {
        if (empty($recepcionIds)) {
            return 0.0;
        }

        return (float) RecepcionLinea::whereIn('recepcion_id', $recepcionIds)
            ->with('solicitudProveedorLinea')
            ->get()
            ->sum(fn ($linea) => $linea->cantidad_recibida * (float) ($linea->solicitudProveedorLinea?->precio_unitario_cotizado ?? 0));
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = [
            'folio_factura' => '',
            'vendor_id' => null,
            'fecha_recepcion' => now()->format('Y-m-d'),
            'monto_total' => null,
            'moneda' => 'MXN',
            'partida_presupuestal' => null,
            'ejercicio_fiscal' => null,
        ];
        $this->recepcionIds = [];
        $this->adjunto = null;
        $this->currentAdjunto = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $record = Invoice::with('recepciones')->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'folio_factura' => $record->folio_factura,
            'vendor_id' => $record->vendor_id,
            'fecha_recepcion' => optional($record->fecha_recepcion)->format('Y-m-d'),
            'monto_total' => $record->monto_total,
            'moneda' => $record->moneda,
            'partida_presupuestal' => $record->partida_presupuestal,
            'ejercicio_fiscal' => $record->ejercicio_fiscal,
        ];
        $this->recepcionIds = $record->recepciones->pluck('id')->all();
        $this->adjunto = null;
        $this->currentAdjunto = $record->documentoAdjunto();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate($this->rules());

        DB::transaction(function () {
            if ($this->editingId) {
                $record = Invoice::findOrFail($this->editingId);
                $record->update($this->form);
            } else {
                $record = Invoice::create(array_merge($this->form, [
                    'estatus' => Invoice::ESTATUS_RECIBIDA,
                ]));
            }

            // 1. Sincroniza la relación M:N.
            $record->recepciones()->sync($this->recepcionIds);

            // 2. Calcula y persiste diferencia_a_revisar — comparación
            // exacta a 2 decimales, sin margen de tolerancia.
            $totalCotizado = $this->totalCotizadoDe($this->recepcionIds);
            $record->update([
                'diferencia_a_revisar' => round((float) $record->monto_total, 2) !== round($totalCotizado, 2),
            ]);

            // 3. Marca Asset.invoice_id para cada línea inventariable de
            // las remisiones ahora vinculadas.
            $assetIds = RecepcionLinea::whereIn('recepcion_id', $this->recepcionIds)
                ->whereNotNull('asset_id')
                ->pluck('asset_id');
            Asset::whereIn('id', $assetIds)->update(['invoice_id' => $record->id]);

            // 4. Marca SolicitudProveedor::estatus = 'facturada' cuando
            // TODAS sus Recepcion (no solo las de esta factura) ya tienen
            // al menos una factura vinculada. Limitación aceptada: sin
            // reversión si luego se desvincula una remisión.
            $solicitudIds = Recepcion::whereIn('id', $this->recepcionIds)->pluck('solicitud_proveedor_id')->unique();

            foreach ($solicitudIds as $solicitudId) {
                $solicitud = SolicitudProveedor::find($solicitudId);

                if ($solicitud && $solicitud->recepciones()->whereDoesntHave('invoices')->doesntExist()) {
                    $solicitud->update(['estatus' => SolicitudProveedor::ESTATUS_FACTURADA]);
                }
            }

            if ($this->adjunto) {
                DocumentoDigitalizado::storeUploaded($this->adjunto, $record, 'factura', auth()->id());
            }
        });

        $this->showModal = false;
        $this->adjunto = null;
        session()->flash('status', 'Guardado correctamente.');
    }

    public function cancel(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->form = [];
        $this->recepcionIds = [];
        $this->adjunto = null;
        $this->currentAdjunto = null;
        $this->resetValidation();
    }

    /**
     * 3 transiciones explícitas secuenciales, sin rama de rechazo — mismo
     * patrón de doble defensa (oculto en la vista + validado en el método)
     * ya usado en SolicitudesSic/SolicitudesProveedor.
     */
    public function marcarRegistrada(int $id): void
    {
        $record = Invoice::findOrFail($id);

        if ($record->estatus !== Invoice::ESTATUS_RECIBIDA) {
            return;
        }

        $record->update(['estatus' => Invoice::ESTATUS_REGISTRADA]);
        session()->flash('status', 'Factura marcada como registrada.');
    }

    public function marcarAutorizada(int $id): void
    {
        $record = Invoice::findOrFail($id);

        if ($record->estatus !== Invoice::ESTATUS_REGISTRADA) {
            return;
        }

        $record->update([
            'estatus' => Invoice::ESTATUS_AUTORIZADA,
            'fecha_autorizacion' => now()->format('Y-m-d'),
        ]);
        session()->flash('status', 'Factura marcada como autorizada.');
    }

    public function marcarPagada(int $id): void
    {
        $record = Invoice::findOrFail($id);

        if ($record->estatus !== Invoice::ESTATUS_AUTORIZADA) {
            return;
        }

        $record->update([
            'estatus' => Invoice::ESTATUS_PAGADA,
            'fecha_pago' => now()->format('Y-m-d'),
        ]);
        session()->flash('status', 'Factura marcada como pagada.');
    }

    public function render()
    {
        $records = Invoice::query()
            ->with('vendor')
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('estatus', $this->estatusFilter))
            ->when($this->soloDiferencia, fn ($q) => $q->where('diferencia_a_revisar', true))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('folio_factura', 'like', "%{$this->search}%")
                        ->orWhereHas('vendor', fn ($q) => $q->where('nombre_comercial', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('fecha_recepcion')
            ->paginate(10);

        return view('gestionti::livewire.compras.facturas', [
            'records' => $records,
            'vendorOptions' => Proveedor::where('activo', true)->orderBy('nombre_comercial')->get(),
            'recepcionOptions' => $this->recepcionesDisponibles(),
            'totalCotizadoSeleccion' => $this->totalCotizadoDe($this->recepcionIds),
        ]);
    }
}
