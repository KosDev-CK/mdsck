<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora dedicada del cambio manual de SIC reservada sobre un activo
 * `reservado` (sección 7.11 del spec original — "excepciones"). NO es el
 * `AuditLog` transversal del spec (fase futura separada, no construida
 * aquí). `sic_anterior_id`/`sic_nueva_id` son ids libres sin FK real (ver
 * migración) — solo `asset` es una relación Eloquent real.
 */
class AssetSicReservationLog extends Model
{
    protected $table = 'asset_sic_reservation_logs';

    protected $fillable = [
        'asset_id', 'sic_anterior_id', 'sic_nueva_id', 'motivo', 'usuario_id',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
