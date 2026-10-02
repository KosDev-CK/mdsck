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

    public function lineas()
    {
        return $this->hasMany(SolicitudProveedorLinea::class, 'lugar_entrega_id');
    }
}
