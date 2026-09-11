<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SdpReport extends Model
{
    public const TIPO_DIARIO = 'diario';

    public const TIPO_MENSUAL = 'mensual';

    protected $table = 'sdp_reports';

    protected $fillable = [
        'tipo',
        'periodo',
        'ruta_archivo',
        'resumen_metricas',
        'generado_en',
    ];

    protected $casts = [
        'periodo' => 'date',
        'resumen_metricas' => 'array',
        'generado_en' => 'datetime',
    ];

    public function scopeDiarios(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_DIARIO);
    }

    public function scopeMensuales(Builder $query): Builder
    {
        return $query->where('tipo', self::TIPO_MENSUAL);
    }

    /**
     * Nombre de archivo sugerido para la descarga (no necesariamente el mismo
     * que el basename de `ruta_archivo`, aunque hoy coincide).
     */
    public function downloadFilename(): string
    {
        return "cierre-{$this->tipo}-{$this->periodo->format('Y-m-d')}.xlsx";
    }
}
