<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mantenimiento — Preventivo/Correctivo (sección 7.10 del spec original).
 * Ver docs/gestionti-progreso.md, Fase 3 etapa 9, para el diseño completo.
 */
class Mantenimiento extends Model
{
    public const TIPO_PREVENTIVO = 'preventivo';

    public const TIPO_CORRECTIVO = 'correctivo';

    public const ORIGEN_INTERNO = 'interno';

    public const ORIGEN_EXTERNO = 'externo';

    public const ESTATUS_PROGRAMADO = 'programado';

    public const ESTATUS_EN_PROCESO = 'en_proceso';

    public const ESTATUS_REALIZADO = 'realizado';

    public const ESTATUS_CANCELADO = 'cancelado';

    public const ESTATUS_REPROGRAMADO = 'reprogramado';

    protected $fillable = [
        'asset_id', 'tipo', 'ticket_id', 'origen_ejecucion', 'vendor_id',
        'costo', 'fecha_programada', 'fecha_realizada', 'estatus',
        'realizado_por_id', 'diagnostico', 'documento_id',
    ];

    protected $casts = [
        'fecha_programada' => 'date',
        'fecha_realizada' => 'date',
        'costo' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Proveedor::class, 'vendor_id');
    }

    public function realizadoPor()
    {
        return $this->belongsTo(Validador::class, 'realizado_por_id');
    }

    public function documento()
    {
        return $this->belongsTo(DocumentoDigitalizado::class, 'documento_id');
    }
}
