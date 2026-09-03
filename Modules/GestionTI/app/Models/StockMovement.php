<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Movimiento de stock (sección 7.11 del spec original). Ver
 * docs/gestionti-progreso.md, Fase 3 etapa 7 — soporta los 5 tipos del
 * spec, pero por ahora solo la pantalla de Stock inserta filas
 * `TIPO_TRASLADO`; el resto quedan reservados para una futura
 * retro-instrumentación de Recepciones/Asignaciones.
 */
class StockMovement extends Model
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    public const TIPO_ASIGNACION = 'asignacion';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_TRASLADO = 'traslado';

    protected $fillable = [
        'asset_id', 'tipo', 'fecha', 'usuario_responsable_id',
        'referencia_tipo', 'referencia_id',
        'ubicacion_origen_id', 'ubicacion_destino_id', 'comentarios',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function usuarioResponsable()
    {
        return $this->belongsTo(User::class, 'usuario_responsable_id');
    }

    public function ubicacionOrigen()
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_origen_id');
    }

    public function ubicacionDestino()
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_destino_id');
    }
}
