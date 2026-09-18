<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SdpTechnician extends Model
{
    protected $table = 'sdp_technicians';

    protected $fillable = [
        'sdp_id',
        'nombre',
        'correo',
        'puesto',
        'zuid',
        'tiene_acceso_sdp',
        'activo',
        'es_nivel_1',
        'grupo_analitico_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_nivel_1' => 'boolean',
        'tiene_acceso_sdp' => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeNivel1(Builder $query): Builder
    {
        return $query->where('es_nivel_1', true);
    }

    /**
     * grupo_analitico_id, igual que es_nivel_1, es un campo editado a mano
     * desde la pantalla "Técnicos" — sdp:sync-technicians nunca lo toca.
     */
    public function grupoAnalitico(): BelongsTo
    {
        return $this->belongsTo(GrupoAnalitico::class, 'grupo_analitico_id');
    }
}
