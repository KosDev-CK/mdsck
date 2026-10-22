<?php

namespace Modules\GestionTI\Livewire\Compras;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Notifications\EntregaInternaConfirmadaNotification;

/**
 * "Entregas internas": las solicitudes enviadas a un proveedor INTERNO (un
 * área de la empresa — telefonía, infraestructura...) que sus encargados
 * confirman cuando entregan lo solicitado. Cada encargado ve solo las de las
 * áreas a las que pertenece (`proveedor_usuario`); un Administrador ve todas.
 * Confirmar NO hace la Recepción (eso sigue siendo de TI, con serie y artículo
 * real): solo deja constancia y avisa a quien creó la solicitud.
 */
#[Layout('layouts.app')]
class EntregasInternas extends Component
{
    use WithPagination;

    /** 'pendientes' | 'confirmadas' */
    public string $vista = 'pendientes';

    public string $search = '';

    public bool $showModal = false;

    public ?int $detalleId = null;

    public string $notas = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setVista(string $vista): void
    {
        $this->vista = in_array($vista, ['pendientes', 'confirmadas'], true) ? $vista : 'pendientes';
        $this->resetPage();
    }

    /**
     * Solicitudes que ESTE usuario puede ver/confirmar: de proveedores
     * internos, ya enviadas, no canceladas, y — salvo Administrador — de las
     * áreas a las que pertenece. Se usa tanto para listar como para
     * autorizar la confirmación (nunca se confía en el id que mande el cliente).
     */
    private function alcance(): Builder
    {
        $usuario = auth()->user();

        return SolicitudProveedor::query()
            ->whereNotNull('enviada_at')
            ->where('estatus', '!=', SolicitudProveedor::ESTATUS_CANCELADA)
            ->whereHas('vendor', function ($q) use ($usuario) {
                $q->where('es_interno', true);

                if (! $usuario->hasRole('Administrador')) {
                    $q->whereHas('usuarios', fn ($q2) => $q2->where('users.id', $usuario->id));
                }
            });
    }

    public function openDetalle(int $id): void
    {
        if (! $this->alcance()->whereKey($id)->exists()) {
            return;
        }

        $this->detalleId = $id;
        $this->notas = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeDetalle(): void
    {
        $this->showModal = false;
        $this->detalleId = null;
        $this->notas = '';
    }

    public function confirmarEntrega(): void
    {
        $this->validate(['notas' => 'nullable|string|max:1000']);

        $solicitud = $this->detalleId
            ? $this->alcance()->with(['vendor', 'creadoPor'])->find($this->detalleId)
            : null;

        if (! $solicitud || $solicitud->entrega_confirmada_at !== null) {
            return;
        }

        $solicitud->update([
            'entrega_confirmada_at' => now(),
            'entrega_confirmada_por_user_id' => auth()->id(),
            'entrega_confirmada_notas' => trim($this->notas) !== '' ? trim($this->notas) : null,
        ]);

        $solicitud->creadoPor?->notify(new EntregaInternaConfirmadaNotification($solicitud, auth()->user()->name));

        $this->closeDetalle();
        session()->flash('status', 'Entrega confirmada. Se avisó a quien creó la solicitud.');
    }

    public function render()
    {
        $registros = $this->alcance()
            ->with(['vendor', 'creadoPor', 'entregaConfirmadaPor'])
            ->withCount('lineas')
            ->when($this->vista === 'pendientes', fn ($q) => $q->whereNull('entrega_confirmada_at'))
            ->when($this->vista === 'confirmadas', fn ($q) => $q->whereNotNull('entrega_confirmada_at'))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('folio', 'like', "%{$this->search}%")
                        ->orWhereHas('vendor', fn ($q3) => $q3->where('nombre_comercial', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('enviada_at')
            ->paginate(15);

        $detalle = $this->showModal && $this->detalleId
            ? $this->alcance()->with([
                'vendor', 'creadoPor', 'entregaConfirmadaPor',
                'lineas.articulo', 'lineas.sic', 'lineas.ebsRequisition.lines', 'lineas.lugarEntrega',
            ])->find($this->detalleId)
            : null;

        return view('gestionti::livewire.compras.entregas-internas', [
            'registros' => $registros,
            'detalle' => $detalle,
        ]);
    }
}
