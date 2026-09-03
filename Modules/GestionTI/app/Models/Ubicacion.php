<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Ubicacion extends Model
{
    protected $table = 'ubicaciones';

    protected $fillable = ['nombre', 'nombre_conocido', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
