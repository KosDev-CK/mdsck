<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoPresupuesto extends Model
{
    public const ESTATUS_ARMADO = 'armado';

    public const ESTATUS_EN_CAPTURA_COSTOS = 'en_captura_costos';

    public const ESTATUS_COMPLETO = 'completo';

    public const ESTATUS_EN_AUTORIZACION = 'en_autorizacion';

    public const ESTATUS_AUTORIZADO = 'autorizado';

    public const ESTATUS_RECHAZADO = 'rechazado';

    protected $fillable = [
        'nombre_proyecto',
        'empresa_id',
        'centro_costo_id',
        'direccion_centro',
        'area_operativa_solicitante_id',
        'pm_responsable_id',
        'fecha_solicitud',
        'fecha_limite_captura',
        'factor_administrativo',
        'estatus',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_limite_captura' => 'date',
        'factor_administrativo' => 'decimal:4',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class);
    }

    /**
     * Reutiliza el catálogo núcleo `Area` ya existente — el spec dice "área
     * operativa solicitante", mismo concepto de área que ya usa `Empleado`,
     * no es un catálogo nuevo.
     */
    public function areaOperativa()
    {
        return $this->belongsTo(Area::class, 'area_operativa_solicitante_id');
    }

    public function pmResponsable()
    {
        return $this->belongsTo(Empleado::class, 'pm_responsable_id');
    }

    public function articulos()
    {
        return $this->hasMany(ProyectoPresupuestoArticulo::class, 'proyecto_id');
    }

    public function autorizaciones()
    {
        return $this->hasMany(ProyectoPresupuestoAutorizacion::class, 'proyecto_id');
    }
}
