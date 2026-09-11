<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Marca de agua de sincronización, clave/valor (ver migración de
 * sdp_sync_states para el porqué de este diseño en vez de una tabla fija de
 * una sola fila). Por ahora solo existe la clave "tickets".
 */
class SdpSyncState extends Model
{
    public const KEY_TICKETS = 'tickets';

    protected $table = 'sdp_sync_states';

    protected $fillable = [
        'key',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public static function get(string $key): ?Carbon
    {
        // ->value(...) es Query Builder puro y NO aplica el cast a
        // datetime de Eloquent (devolvería un string) — se usa first() para
        // que sí pase por el modelo y su cast.
        return self::where('key', $key)->first()?->last_synced_at;
    }

    public static function set(string $key, Carbon $time): void
    {
        self::updateOrCreate(['key' => $key], ['last_synced_at' => $time]);
    }
}
