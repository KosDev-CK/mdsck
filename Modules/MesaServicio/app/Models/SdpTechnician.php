<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SdpTechnician extends Model
{
    protected $table = 'sdp_technicians';

    protected $fillable = [
        'sdp_id',
        'nombre',
        'correo',
        'puesto',
        'activo',
        'es_nivel_1',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_nivel_1' => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeNivel1(Builder $query): Builder
    {
        return $query->where('es_nivel_1', true);
    }
}
