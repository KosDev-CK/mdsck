<?php

namespace Modules\GestionTI\Livewire\PresupuestoProyectos;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\ProyectoPresupuesto;

/**
 * Presupuesto por Proyecto (secciones 5.1 y 7.5 del spec original) —
 * bandeja de encabezados + alta. Todo el flujo de trabajo (artículos,
 * captura de costos, autorización, exportar) vive en Show.php — ver
 * docs/gestionti-progreso.md para el diseño completo.
 *
 * Sin edición del encabezado desde ningún lado en esta entrega — decisión
 * deliberada (deferred explícito, documentada en docs/gestionti-progreso.md):
 * el spec no la pide y agregarla suma superficie a una etapa ya grande, igual
 * que el flujo de devolución quedó deferred en Asignación.
 *
 * Sin control de acceso por registro — el permiso de pantalla es lo único
 * que autoriza (misma decisión ya aplicada a las demás pantallas de este
 * módulo desde Fase 3 etapa 1, ver docs/gestionti-progreso.md).
 * `pm_responsable_id` es solo dato descriptivo de quién arma el proyecto, no
 * un candado de autorización.
 */
#[Layout('layouts.app')]
class Manage extends Component
{
    use WithPagination;

    public array $form = [];

    public string $search = '';

    public string $estatusFilter = '';

    public bool $showModal = false;

    protected function rules(): array
    {
        return [
            'form.nombre_proyecto' => 'required|string|max:255',
            'form.empresa_id' => 'required|exists:empresas,id',
            'form.centro_costo_id' => 'required|exists:centros_costo,id',
            'form.direccion_centro' => 'required|string|max:255',
            'form.area_operativa_solicitante_id' => 'required|exists:areas,id',
            'form.pm_responsable_id' => 'required|exists:empleados,id',
            'form.fecha_solicitud' => 'required|date',
            'form.fecha_limite_captura' => 'required|date',
            'form.factor_administrativo' => 'required|numeric|min:1',
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

    public function create(): void
    {
        $this->form = [
            'nombre_proyecto' => '',
            'empresa_id' => null,
            'centro_costo_id' => null,
            'direccion_centro' => '',
            'area_operativa_solicitante_id' => null,
            'pm_responsable_id' => null,
            'fecha_solicitud' => now()->format('Y-m-d'),
            'fecha_limite_captura' => now()->addDays(15)->format('Y-m-d'),
            'factor_administrativo' => '1.0350',
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
        $this->validate();

        $record = ProyectoPresupuesto::create(array_merge($this->form, [
            'estatus' => ProyectoPresupuesto::ESTATUS_ARMADO,
        ]));

        $this->showModal = false;

        $this->redirect(route('gestionti.presupuestos-proyecto.show', $record), navigate: false);
    }

    public function render()
    {
        $records = ProyectoPresupuesto::query()
            ->with(['empresa', 'centroCosto', 'pmResponsable'])
            ->when($this->estatusFilter !== '', fn ($q) => $q->where('estatus', $this->estatusFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('nombre_proyecto', 'like', "%{$this->search}%")
                        ->orWhereHas('pmResponsable', fn ($q2) => $q2->where('nombre', 'like', "%{$this->search}%"))
                        ->orWhereHas('centroCosto', fn ($q2) => $q2->where('nombre', 'like', "%{$this->search}%"));
                });
            })
            ->orderByDesc('fecha_solicitud')
            ->paginate(10);

        return view('gestionti::livewire.presupuesto-proyectos.manage', [
            'records' => $records,
            'empresaOptions' => Empresa::where('activo', true)->orderBy('nombre_comercial')->get(),
            'centroCostoOptions' => CentroCosto::where('activo', true)->orderBy('nombre')->get(),
            'areaOptions' => Area::where('activo', true)->orderBy('nombre')->get(),
            'empleadoOptions' => Empleado::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
