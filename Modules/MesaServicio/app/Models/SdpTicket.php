<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SdpTicket extends Model
{
    protected $table = 'sdp_tickets';

    protected $fillable = [
        'sdp_id',
        'display_id',
        'asunto',
        'sdp_technician_id',
        'sdp_ticket_status_id',
        'estado_nombre',
        'categoria',
        'subcategoria',
        'solicitante_nombre',
        'solicitante_correo',
        'departamento',
        'sitio',
        'prioridad',
        'urgencia',
        'impacto',
        'tipo_solicitud',
        'modo',
        'grupo',
        'created_time',
        'responded_time',
        'resolved_time',
        'completed_time',
        'due_time',
        'primera_respuesta_vencida',
        'vencido',
        'resolucion',
        'raw_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'created_time' => 'datetime',
        'responded_time' => 'datetime',
        'resolved_time' => 'datetime',
        'completed_time' => 'datetime',
        'due_time' => 'datetime',
        'primera_respuesta_vencida' => 'boolean',
        'vencido' => 'boolean',
        'raw_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(SdpTechnician::class, 'sdp_technician_id');
    }

    public function ticketStatus(): BelongsTo
    {
        return $this->belongsTo(SdpTicketStatus::class, 'sdp_ticket_status_id');
    }

    /**
     * Alerta de SLA de primera respuesta (Fase 2, dashboard): creado hace
     * más de 10 minutos y todavía sin responded_time — cálculo on-demand,
     * sin tabla propia de alertas.
     */
    public function scopeSlaPrimeraRespuestaVencida(Builder $query): Builder
    {
        return $query->whereNull('responded_time')
            ->where('created_time', '<=', now()->subMinutes(10));
    }

    public function scopeDeTecnicosNivel1(Builder $query): Builder
    {
        return $query->whereHas('technician', fn (Builder $q) => $q->where('es_nivel_1', true));
    }
}
