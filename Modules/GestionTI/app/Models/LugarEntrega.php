<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class LugarEntrega extends Model
{
    protected $table = 'lugares_entrega';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** Ubicaciones de inventario que este lugar de entrega agrupa (se asignan desde Catálogos Núcleo → Ubicaciones). */
    public function ubicaciones()
    {
        return $this->hasMany(Ubicacion::class, 'lugar_entrega_id');
    }

    public function lineas()
    {
        return $this->hasMany(SolicitudProveedorLinea::class, 'lugar_entrega_id');
    }
}
