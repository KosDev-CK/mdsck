<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoPresupuestoAutorizacion extends Model
{
    // Nombre de tabla explícito — la pluralización automática de Eloquent
    // para "ProyectoPresupuestoAutorizacion" daría "proyecto_presupuesto_autorizacions",
    // no "proyecto_presupuesto_autorizaciones" (mismo riesgo ya documentado
    // repetidamente en este módulo).
    protected $table = 'proyecto_presupuesto_autorizaciones';

    public const ESTATUS_PENDIENTE = 'pendiente';

    public const ESTATUS_APROBADO = 'aprobado';

    public const ESTATUS_RECHAZADO = 'rechazado';

    protected $fillable = [
        'proyecto_id',
        'nivel',
        'aprobador_id',
        'estatus',
        'fecha_resolucion',
        'comentario',
    ];

    protected $casts = [
        'fecha_resolucion' => 'date',
    ];

    public function proyecto()
    {
        return $this->belongsTo(ProyectoPresupuesto::class, 'proyecto_id');
    }

    /**
     * El aprobador es un `Empleado` (no un `Validador`) — decisión de diseño
     * documentada en la migración: aquí el aprobador es alguien de la línea
     * de mando organizacional (ej. un Director), no "quien ejecutó la acción
     * internamente".
     */
    public function aprobador()
    {
        return $this->belongsTo(Empleado::class, 'aprobador_id');
    }
}
