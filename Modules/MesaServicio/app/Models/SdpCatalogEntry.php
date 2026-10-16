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
        'slas',
    ];

    /**
     * Catálogos jerárquicos: SDP no los expone como recurso de nivel
     * superior (404 "Invalid URL" en /subcategories e /items), solo anidados
     * bajo su padre — categories/{id}/subcategories y
     * categories/{id}/subcategories/{id}/items. Se guardan en la misma tabla;
     * el padre queda en `extra` (category_id/category_nombre y, para items,
     * subcategory_id/subcategory_nombre). Ver SyncCatalogosCommand.
     */
    public const CATALOGOS_JERARQUICOS = [
        'subcategories',
        'items',
    ];

    /**
     * Los 12 planos más los 2 jerárquicos, en el orden en que deben
     * sincronizarse (cada jerárquico depende del anterior ya local).
     *
     * @return list<string>
     */
    public static function todos(): array
    {
        return [...self::CATALOGOS, ...self::CATALOGOS_JERARQUICOS];
    }

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
