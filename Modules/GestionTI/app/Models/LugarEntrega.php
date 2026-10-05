<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class LugarEntrega extends Model
{
    protected $table = 'lugares_entrega';

    protected $fillable = ['nombre', 'ubicacion_id', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** Ubicación física (inventario) a la que llegan los activos recibidos en este lugar. */
    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function lineas()
    {
        return $this->hasMany(SolicitudProveedorLinea::class, 'lugar_entrega_id');
    }
}
