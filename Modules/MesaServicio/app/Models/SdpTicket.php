<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        // Fase 8 — folios combinados (ver SyncTicketsCommand::detectMergesFromHistory()).
        'combinado_con_display_id',
        'combinado_detectado_en',
        'categoria',
        'subcategoria',
        // Fase 8 — campos confirmados nuevos.
        'articulo',
        'categoria_servicio',
        'solicitante_nombre',
        'solicitante_correo',
        'departamento',
        'sitio',
        'sdp_site_id',
        'prioridad',
        'urgencia',
        'impacto',
        'tipo_solicitud',
        'modo',
        'grupo',
        'nivel',
        // Fase 8 (Parte 3) — campos personalizados (UDF) confirmados por el
        // administrador real de la instancia SDP.
        'area_operativa',
        'grupo_resolutor',
        'super_categoria',
        'n3_area_escalamiento',
        'n3_fecha_escalamiento',
        'n3_fecha_solucion',
        'n3_no_seguimiento_proveedor',
        'n3_recurso_escalamiento',
        'n4_area_escalamiento',
        'n4_fecha_escalamiento',
        'n4_fecha_solucion',
        'n4_no_seguimiento_proveedor',
        'n4_recurso_escalamiento',
        'created_time',
        'responded_time',
        'resolved_time',
        'completed_time',
        'due_time',
        'assigned_time',
        'tiempo_transcurrido_segundos',
        'primera_respuesta_vencida',
        'vencido',
        'resolucion',
        'resuelto_por',
        'raw_payload',
        'last_synced_at',
    ];

    protected $casts = [
        'created_time' => 'datetime',
        'responded_time' => 'datetime',
        'resolved_time' => 'datetime',
        'completed_time' => 'datetime',
        'due_time' => 'datetime',
        'assigned_time' => 'datetime',
        'n3_fecha_escalamiento' => 'datetime',
        'n3_fecha_solucion' => 'datetime',
        'n4_fecha_escalamiento' => 'datetime',
        'n4_fecha_solucion' => 'datetime',
        'tiempo_transcurrido_segundos' => 'integer',
        'primera_respuesta_vencida' => 'boolean',
        'vencido' => 'boolean',
        'combinado_detectado_en' => 'datetime',
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(SdpSite::class, 'sdp_site_id');
    }

    /**
     * Encuesta de satisfacción (Fase 6) generada para este ticket, si alguna
     * vez pasó a estado "completado" con el formulario de encuesta
     * configurado — ver SyncTicketsCommand::dispatchSurveyIfNewlyCompleted().
     */
    public function surveyLink(): HasOne
    {
        return $this->hasOne(SdpSurveyLink::class, 'sdp_ticket_id');
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
