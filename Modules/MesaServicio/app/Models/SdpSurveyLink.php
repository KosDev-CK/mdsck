<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\FormBuilder\Models\TicketFormLink;

/**
 * Tabla puente (Fase 6) para la trazabilidad técnico -> ticket -> respuesta
 * de encuesta de satisfacción. Reusa Modules\FormBuilder\Models\TicketFormLink
 * tal cual existe hoy (no se toca ese módulo) — este modelo es el único lugar
 * donde MesaServicio conoce esa relación.
 */
class SdpSurveyLink extends Model
{
    protected $table = 'sdp_survey_links';

    protected $fillable = [
        'ticket_form_link_id',
        'sdp_ticket_id',
        'sdp_technician_id',
    ];

    public function ticketFormLink(): BelongsTo
    {
        return $this->belongsTo(TicketFormLink::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SdpTicket::class, 'sdp_ticket_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(SdpTechnician::class, 'sdp_technician_id');
    }
}
