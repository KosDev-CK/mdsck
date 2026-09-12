<?php

namespace Modules\MesaServicio\Livewire\Catalogos;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\MesaServicio\Models\SdpSlaDefinition;
use Modules\MesaServicio\Models\SdpTicket;

/**
 * Fase 7: catálogo editable de definiciones de SLA (tiempos objetivo de
 * primera respuesta/resolución por prioridad, texto libre — ver
 * docs/mesaservicio-progreso.md Fase 7 sobre por qué no es un enum) + dos
 * tablas de cumplimiento (por técnico / por categoría) calculadas al vuelo
 * sobre sdp_tickets en un rango de fechas, sin tabla propia de métricas.
 *
 * Independiente de sdp_tickets.vencido/primera_respuesta_vencida (juicio de
 * SDP con su propia configuración interna, ya mostrado tal cual en la ficha
 * de técnico) — ambos coexisten con propósitos distintos.
 */
#[Layout('layouts.app')]
class Slas extends Component
{
    // --- Alta de una definición nueva ---
    public string $newNombre = '';

    public string $newPrioridad = '';

    public ?int $newTiempoPrimeraRespuesta = null;

    public ?int $newTiempoResolucion = null;

    // --- Edición inline por fila ---
    public ?int $editingId = null;

    public string $editNombre = '';

    public string $editPrioridad = '';

    public ?int $editTiempoPrimeraRespuesta = null;

    public ?int $editTiempoResolucion = null;

    // --- Filtro de fechas para las tablas de cumplimiento ---
    public string $desde = '';

    public string $hasta = '';

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();
    }

    /**
     * Reglas compartidas entre alta y edición — $prefix distingue el set de
     * propiedades ("new"/"edit"), $ignoreId excluye el propio registro de la
     * validación de unicidad al editar.
     */
    private function reglas(string $prefix, ?int $ignoreId): array
    {
        return [
            "{$prefix}Nombre" => ['required', 'string', 'max:255', Rule::unique('sdp_sla_definitions', 'nombre')->ignore($ignoreId)],
            "{$prefix}Prioridad" => ['nullable', 'string', 'max:255'],
            "{$prefix}TiempoPrimeraRespuesta" => ['nullable', 'integer', 'min:1', "required_without:{$prefix}TiempoResolucion"],
            "{$prefix}TiempoResolucion" => ['nullable', 'integer', 'min:1', "required_without:{$prefix}TiempoPrimeraRespuesta"],
        ];
    }

    public function addDefinicion(): void
    {
        $this->validate($this->reglas('new', null));

        SdpSlaDefinition::create([
            'nombre' => $this->newNombre,
            'prioridad' => $this->newPrioridad !== '' ? $this->newPrioridad : null,
            'tiempo_primera_respuesta_minutos' => $this->newTiempoPrimeraRespuesta,
            'tiempo_resolucion_minutos' => $this->newTiempoResolucion,
        ]);

        $this->reset(['newNombre', 'newPrioridad', 'newTiempoPrimeraRespuesta', 'newTiempoResolucion']);
        session()->flash('status', 'Definición de SLA agregada.');
    }

    public function edit(int $id): void
    {
        $definicion = SdpSlaDefinition::findOrFail($id);

        $this->editingId = $id;
        $this->editNombre = $definicion->nombre;
        $this->editPrioridad = (string) ($definicion->prioridad ?? '');
        $this->editTiempoPrimeraRespuesta = $definicion->tiempo_primera_respuesta_minutos;
        $this->editTiempoResolucion = $definicion->tiempo_resolucion_minutos;
        $this->resetValidation();
    }

    public function update(): void
    {
        $this->validate($this->reglas('edit', $this->editingId));

        SdpSlaDefinition::findOrFail($this->editingId)->update([
            'nombre' => $this->editNombre,
            'prioridad' => $this->editPrioridad !== '' ? $this->editPrioridad : null,
            'tiempo_primera_respuesta_minutos' => $this->editTiempoPrimeraRespuesta,
            'tiempo_resolucion_minutos' => $this->editTiempoResolucion,
        ]);

        $this->cancelEdit();
        session()->flash('status', 'Definición de SLA actualizada.');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset(['editNombre', 'editPrioridad', 'editTiempoPrimeraRespuesta', 'editTiempoResolucion']);
        $this->resetValidation();
    }

    public function toggleActivo(int $id): void
    {
        $definicion = SdpSlaDefinition::findOrFail($id);
        $definicion->update(['activo' => ! $definicion->activo]);
    }

    /**
     * Consulta base de tickets creados dentro del rango desde/hasta
     * (inclusive, día completo en ambos extremos) — reusada por ambas
     * tablas de cumplimiento.
     */
    private function ticketsEnRango(): Builder
    {
        return SdpTicket::query()->whereBetween('created_time', [
            Carbon::parse($this->desde)->startOfDay(),
            Carbon::parse($this->hasta)->endOfDay(),
        ]);
    }

    /**
     * @return Collection<int, array{etiqueta: string, primera_respuesta: array, resolucion: array}>
     */
    private function cumplimientoPorTecnico(): Collection
    {
        $tickets = $this->ticketsEnRango()
            ->whereNotNull('sdp_technician_id')
            ->with('technician')
            ->get();

        return $this->calcularCumplimiento(
            $tickets,
            fn (SdpTicket $ticket) => $ticket->sdp_technician_id,
            fn (SdpTicket $ticket) => $ticket->technician?->nombre ?? 'Sin asignar',
        );
    }

    /**
     * @return Collection<int, array{etiqueta: string, primera_respuesta: array, resolucion: array}>
     */
    private function cumplimientoPorCategoria(): Collection
    {
        $tickets = $this->ticketsEnRango()->get();

        return $this->calcularCumplimiento(
            $tickets,
            fn (SdpTicket $ticket) => $ticket->categoria ?: '(sin categoría)',
            fn (SdpTicket $ticket) => $ticket->categoria ?: 'Sin categoría',
        );
    }

    /**
     * Cálculo de cumplimiento agrupado: por cada ticket se resuelve su
     * SdpSlaDefinition aplicable (SdpSlaDefinition::paraPrioridad). Un
     * ticket sin definición aplicable, o que todavía no tiene el timestamp
     * necesario (responded_time para primera respuesta, resolved_time o
     * completed_time para resolución), se EXCLUYE de ese numerador/
     * denominador — no cuenta ni a favor ni en contra.
     */
    private function calcularCumplimiento(Collection $tickets, \Closure $keyResolver, \Closure $labelResolver): Collection
    {
        return $tickets->groupBy($keyResolver)
            ->map(function (Collection $grupo) use ($labelResolver) {
                $evaluablesRespuesta = 0;
                $cumplidasRespuesta = 0;
                $evaluablesResolucion = 0;
                $cumplidasResolucion = 0;

                /** @var SdpTicket $ticket */
                foreach ($grupo as $ticket) {
                    $definicion = SdpSlaDefinition::paraPrioridad($ticket->prioridad);

                    if (! $definicion) {
                        continue;
                    }

                    if ($definicion->tiempo_primera_respuesta_minutos !== null && $ticket->responded_time !== null) {
                        $evaluablesRespuesta++;
                        $minutos = abs($ticket->created_time->diffInMinutes($ticket->responded_time));

                        if ($minutos <= $definicion->tiempo_primera_respuesta_minutos) {
                            $cumplidasRespuesta++;
                        }
                    }

                    $tiempoResolucion = $ticket->resolved_time ?? $ticket->completed_time;

                    if ($definicion->tiempo_resolucion_minutos !== null && $tiempoResolucion !== null) {
                        $evaluablesResolucion++;
                        $minutos = abs($ticket->created_time->diffInMinutes($tiempoResolucion));

                        if ($minutos <= $definicion->tiempo_resolucion_minutos) {
                            $cumplidasResolucion++;
                        }
                    }
                }

                return [
                    'etiqueta' => $labelResolver($grupo->first()),
                    'primera_respuesta' => [
                        'evaluables' => $evaluablesRespuesta,
                        'cumplidas' => $cumplidasRespuesta,
                        'pct' => $evaluablesRespuesta > 0 ? round($cumplidasRespuesta / $evaluablesRespuesta * 100, 1) : null,
                    ],
                    'resolucion' => [
                        'evaluables' => $evaluablesResolucion,
                        'cumplidas' => $cumplidasResolucion,
                        'pct' => $evaluablesResolucion > 0 ? round($cumplidasResolucion / $evaluablesResolucion * 100, 1) : null,
                    ],
                ];
            })
            ->sortBy('etiqueta')
            ->values();
    }

    public function render()
    {
        return view('mesaservicio::livewire.catalogos.slas', [
            'definiciones' => SdpSlaDefinition::orderBy('nombre')->get(),
            'porTecnico' => $this->cumplimientoPorTecnico(),
            'porCategoria' => $this->cumplimientoPorCategoria(),
        ]);
    }
}
