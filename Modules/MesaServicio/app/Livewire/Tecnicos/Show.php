<?php

namespace Modules\MesaServicio\Livewire\Tecnicos;

use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\FormBuilder\Models\FormAnswer;
use Modules\FormBuilder\Models\FormField;
use Modules\FormBuilder\Models\FormSubmission;
use Modules\MesaServicio\Models\SdpSurveyLink;
use Modules\MesaServicio\Models\SdpSurveySetting;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;

/**
 * Ficha de técnico (Fase 2): tickets pendientes y atendidos de un técnico
 * específico. Ruta con route-model binding sobre SdpTechnician, mismo
 * patrón que Modules\GestionTI\Livewire\Inventarios\FichaActivo\Show sobre
 * Asset. Comparte permiso (screens.mesaservicio-dashboard.manage) con el
 * Dashboard en vez de tener un permiso propio — ver "Ajustes de criterio"
 * en docs/mesaservicio-progreso.md para el porqué.
 *
 * Sin componente de paginación reusable todavía en el repo (ver "Pendiente"
 * en CLAUDE.md) — se acota a los 100 más recientes de cada lista en vez de
 * paginar, mismo criterio de tope defensivo que Dashboard::slaVencidosDetalle().
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public SdpTechnician $tecnico;

    public function mount(SdpTechnician $tecnico): void
    {
        $this->tecnico = $tecnico;
    }

    /**
     * @return Collection<int, SdpTicket>
     */
    private function pendientes(): Collection
    {
        return SdpTicket::where('sdp_technician_id', $this->tecnico->id)
            ->whereHas('ticketStatus', fn ($q) => $q->where('tipo', SdpTicketStatus::TIPO_EN_CURSO))
            ->orderByDesc('created_time')
            ->limit(100)
            ->get();
    }

    /**
     * @return Collection<int, SdpTicket>
     */
    private function atendidos(): Collection
    {
        return SdpTicket::where('sdp_technician_id', $this->tecnico->id)
            ->whereHas('ticketStatus', fn ($q) => $q->where('tipo', SdpTicketStatus::TIPO_COMPLETADO))
            ->orderByDesc('completed_time')
            ->limit(100)
            ->get();
    }

    /**
     * Métricas de satisfacción (Fase 6) del técnico: promedio de la
     * calificación 1-5 (primera pregunta de la encuesta configurada) y
     * conteo de encuestas respondidas, sobre los SdpSurveyLink de este
     * técnico. Localiza la pregunta de calificación por su field_key estable
     * (SdpSurveySetting::CALIFICACION_FIELD_KEY), no por posición ordinal ni
     * por el texto del label — así el cálculo no se rompe si el formulario
     * configurado cambia de contenido. Sin respuestas todavía (o sin
     * encuesta configurada, o formulario configurado sin esa pregunta),
     * regresa promedio null en vez de dividir por cero.
     *
     * @return array{promedio: ?float, total: int}
     */
    private function satisfaccion(): array
    {
        $vacio = ['promedio' => null, 'total' => 0];

        $formId = SdpSurveySetting::current()->form_id;

        if (! $formId) {
            return $vacio;
        }

        $ratingField = FormField::where('form_id', $formId)
            ->where('field_key', SdpSurveySetting::CALIFICACION_FIELD_KEY)
            ->first();

        if (! $ratingField) {
            return $vacio;
        }

        $linkIds = SdpSurveyLink::where('sdp_technician_id', $this->tecnico->id)
            ->pluck('ticket_form_link_id');

        if ($linkIds->isEmpty()) {
            return $vacio;
        }

        $submissionIds = FormSubmission::whereIn('ticket_form_link_id', $linkIds)->pluck('id');

        if ($submissionIds->isEmpty()) {
            return $vacio;
        }

        // FormAnswer::value viene casteado 'array', pero para un campo
        // single_choice en la práctica guarda un escalar (el 'value' de la
        // opción elegida, ej. "3"), no un arreglo — el cast 'array' de
        // Eloquent solo hace json_decode(..., true) de lo que se guardó, y
        // json_decode('"3"', true) regresa el string "3", no un arreglo.
        // Se maneja ambas formas (escalar o arreglo con un elemento) para no
        // asumir de más sobre el shape exacto.
        $calificaciones = FormAnswer::where('form_field_id', $ratingField->id)
            ->whereIn('submission_id', $submissionIds)
            ->get()
            ->map(function (FormAnswer $answer) {
                $raw = $answer->value;
                $raw = is_array($raw) ? ($raw[0] ?? null) : $raw;

                return is_numeric($raw) ? (int) $raw : null;
            })
            ->filter(fn (?int $valor) => $valor !== null);

        if ($calificaciones->isEmpty()) {
            return $vacio;
        }

        return [
            'promedio' => round($calificaciones->avg(), 2),
            'total' => $calificaciones->count(),
        ];
    }

    public function render()
    {
        return view('mesaservicio::livewire.tecnicos.show', [
            'pendientes' => $this->pendientes(),
            'atendidos' => $this->atendidos(),
            'satisfaccion' => $this->satisfaccion(),
        ]);
    }
}
