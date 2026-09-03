<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $fillable = [
        'numero_empleado', 'nombre', 'correo', 'rfc', 'puesto_id', 'area_id',
        'ubicacion_id', 'unidad_negocio_id', 'empresa_id', 'jefe_inmediato_id',
        'director_id', 'director_ejecutivo_id', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function puesto()
    {
        return $this->belongsTo(Puesto::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function unidadNegocio()
    {
        return $this->belongsTo(UnidadNegocio::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function jefeInmediato()
    {
        return $this->belongsTo(Empleado::class, 'jefe_inmediato_id');
    }

    public function director()
    {
        return $this->belongsTo(Empleado::class, 'director_id');
    }

    public function directorEjecutivo()
    {
        return $this->belongsTo(Empleado::class, 'director_ejecutivo_id');
    }

    public function assetAssignments()
    {
        return $this->hasMany(AssetAssignment::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
