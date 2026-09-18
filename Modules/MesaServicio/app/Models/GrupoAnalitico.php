<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo local, 100% manual, de clasificación de técnicos con fines de
 * reporte/analítica — independiente de los grupos de ServiceDesk Plus (que
 * son muchos-a-muchos por técnico, sin un grupo "canónico" utilizable). Se
 * mantiene deliberadamente genérico (no específico de técnico) porque el
 * mismo catálogo probablemente se reutilice después para otras entidades
 * (empresas, geografía, etc.) — esa reutilización es trabajo futuro, hoy
 * solo lo referencia SdpTechnician::grupoAnalitico().
 */
class GrupoAnalitico extends Model
{
    protected $table = 'grupos_analiticos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function tecnicos(): HasMany
    {
        return $this->hasMany(SdpTechnician::class, 'grupo_analitico_id');
    }
}
