<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SdpTicketStatus extends Model
{
    public const TIPO_EN_CURSO = 'en_curso';

    public const TIPO_COMPLETADO = 'completado';

    protected $table = 'sdp_ticket_statuses';

    protected $fillable = [
        'sdp_id',
        'nombre',
        'internal_name',
        'tipo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeEnCurso(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_EN_CURSO);
    }

    public function scopeCompletados(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_COMPLETADO);
    }
}
