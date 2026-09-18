<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo local de un registro de cualquiera de los 12 catálogos de
 * configuración de SDP (ver sdp:sync-catalogos) — `catalogo` distingue de
 * cuál se trata (ej. 'categories', 'priority_matrices', 'closure_codes').
 */
class SdpCatalogEntry extends Model
{
    protected $table = 'sdp_catalog_entries';

    /**
     * Las 12 claves de catálogo conocidas — mismo nombre de recurso de SDP
     * que de clave local (ver SdpClient::listCatalog()/SyncCatalogosCommand).
     */
    public const CATALOGOS = [
        'categories',
        'levels',
        'modes',
        'impacts',
        'urgencies',
        'priorities',
        'priority_matrices',
        'request_types',
        'task_types',
        'worklog_types',
        'closure_codes',
        'downtime_types',
    ];

    protected $fillable = [
        'catalogo',
        'sdp_id',
        'nombre',
        'descripcion',
        'color',
        'activo',
        'extra',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'extra' => 'array',
    ];

    public function scopeDelCatalogo(Builder $query, string $catalogo): Builder
    {
        return $query->where('catalogo', $catalogo);
    }
}
