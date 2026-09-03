<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Capa de datos únicamente — sin pantalla ni CRUD todavía. El flujo manual de
 * esta etapa de Fase 3 crea el `SolicitudSicBorrador` directamente sin pasar
 * por aquí; esta tabla queda lista para cuando la integración real con el
 * módulo de Formularios exista (Fase 5, pausada).
 */
class FormularioSicLink extends Model
{
    protected $table = 'formulario_sic_links';

    protected $fillable = [
        'ticket_id',
        'token_o_referencia_externa',
        'estatus',
        'fecha_generacion',
        'fecha_respuesta',
        'solicitud_sic_borrador_id',
    ];

    protected $casts = [
        'fecha_generacion' => 'datetime',
        'fecha_respuesta' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function solicitudSic()
    {
        return $this->belongsTo(SolicitudSicBorrador::class, 'solicitud_sic_borrador_id');
    }
}
