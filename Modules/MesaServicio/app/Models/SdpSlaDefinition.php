<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo propio y editable de acuerdos de nivel de servicio (SLA) — Fase 7.
 * Independiente de sdp_tickets.vencido/primera_respuesta_vencida (juicio de
 * SDP sobre su propia configuración interna, ya sincronizado desde la Fase 2)
 * — este catálogo mide cumplimiento contra tiempos objetivo definidos por el
 * equipo, no reemplaza esos dos campos. Ver docs/mesaservicio-progreso.md,
 * Fase 7, "Ajustes de criterio".
 */
class SdpSlaDefinition extends Model
{
    protected $table = 'sdp_sla_definitions';

    protected $fillable = [
        'nombre',
        'prioridad',
        'tiempo_primera_respuesta_minutos',
        'tiempo_resolucion_minutos',
        'activo',
    ];

    protected $casts = [
        'tiempo_primera_respuesta_minutos' => 'integer',
        'tiempo_resolucion_minutos' => 'integer',
        'activo' => 'boolean',
    ];

    /**
     * Resuelve la definición activa aplicable a una prioridad de ticket
     * dada: busca primero una definición activa cuya `prioridad` calce
     * exacto, y si no hay match cae a la definición activa "por defecto"
     * (`prioridad` null, catch-all). Si tampoco hay catch-all activo,
     * regresa null — el ticket queda excluido del cálculo de cumplimiento
     * (no cuenta como incumplido).
     */
    public static function paraPrioridad(?string $prioridad): ?self
    {
        if ($prioridad !== null && $prioridad !== '') {
            $especifica = static::query()
                ->where('activo', true)
                ->where('prioridad', $prioridad)
                ->first();

            if ($especifica) {
                return $especifica;
            }
        }

        return static::query()
            ->where('activo', true)
            ->whereNull('prioridad')
            ->first();
    }

    public static function paraTicket(SdpTicket $ticket): ?self
    {
        return static::paraPrioridad($ticket->prioridad);
    }
}
