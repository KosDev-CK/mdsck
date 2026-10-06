<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Ubicacion extends Model
{
    protected $table = 'ubicaciones';

    protected $fillable = ['nombre', 'nombre_conocido', 'lugar_entrega_id', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /** Lugar de entrega de Compras (Zurich, CEDA, Sotelo...) al que pertenece esta ubicación. */
    public function lugarEntrega()
    {
        return $this->belongsTo(LugarEntrega::class, 'lugar_entrega_id');
    }
}
