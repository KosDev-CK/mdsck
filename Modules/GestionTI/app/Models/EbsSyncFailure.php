<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Registro persistente de un día+método que falló al sincronizar contra
 * Oracle EBS — ver la migración `create_ebs_sync_failures_table` y
 * docs/gestionti-progreso.md. Alimentado por `EbsBackfillCommand`,
 * `EbsSincronizarCreadasCommand`/`EbsSincronizarAprobadasCommand` (cuando
 * fallan) y consumido/resuelto por `gestionti:ebs-reintentar-fallidos`.
 */
class EbsSyncFailure extends Model
{
    public const RESUELTO_VIA_REINTENTO = 'reintento_ok';

    public const RESUELTO_VIA_CONSECUTIVO = 'consecutivo_confirmado';

    protected $fillable = [
        'fecha',
        'metodo',
        'error_code',
        'error_msg',
        'intentos',
        'resuelto_at',
        'resuelto_via',
    ];

    protected $casts = [
        'fecha' => 'date',
        'error_code' => 'integer',
        'intentos' => 'integer',
        'resuelto_at' => 'datetime',
    ];

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereNull('resuelto_at');
    }

    /**
     * Registra (o actualiza) un fallo real para esta fecha+método:
     * incrementa `intentos`, refresca `error_code`/`error_msg`, y —
     * importante — si el registro ya estaba `resuelto_at` (una resolución
     * previa, real o por consecutivo), lo REABRE: un fallo real siempre
     * gana sobre una resolución previa.
     *
     * Busca por `whereDate(...)`, no por una igualdad `where('fecha', ...)`
     * directa — mismo gotcha ya documentado en este módulo
     * (docs/gestionti-progreso.md, nota de "Mantenimientos próximos" en el
     * Dashboard): el cast `date` de Eloquent, al ESCRIBIR, persiste la hora
     * incluida (`"...00:00:00"`), así que una igualdad de string contra
     * solo la fecha (`Y-m-d`) nunca haría match contra un registro ya
     * guardado — `whereDate(...)` sí compara solo la parte de fecha en
     * ambos motores (SQLite/MySQL).
     */
    public static function registrar(Carbon $fecha, string $metodo, ?int $errorCode, ?string $errorMsg): void
    {
        $registro = static::whereDate('fecha', $fecha->toDateString())
            ->where('metodo', $metodo)
            ->first();

        if (! $registro) {
            $registro = new static([
                'fecha' => $fecha->toDateString(),
                'metodo' => $metodo,
            ]);
            $registro->intentos = 0;
        }

        $registro->intentos = ((int) $registro->intentos) + 1;
        $registro->error_code = $errorCode;
        $registro->error_msg = $errorMsg;
        $registro->resuelto_at = null;
        $registro->resuelto_via = null;
        $registro->save();
    }

    /**
     * Marca como resuelto (vía reintento real) el registro pendiente de
     * esta fecha+método, si existe — no-op si no hay ninguno. Se usa cuando
     * una sincronización normal (fuera del comando de reintento dedicado)
     * simplemente funciona para un día que antes había fallado.
     *
     * Mismo criterio de `whereDate(...)` que `registrar()`, ver arriba.
     */
    public static function resolverSiPendiente(Carbon $fecha, string $metodo): void
    {
        static::pendientes()
            ->whereDate('fecha', $fecha->toDateString())
            ->where('metodo', $metodo)
            ->update([
                'resuelto_at' => now(),
                'resuelto_via' => self::RESUELTO_VIA_REINTENTO,
            ]);
    }
}
