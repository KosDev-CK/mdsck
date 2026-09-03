<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Puesto extends Model
{
    protected $fillable = ['nombre', 'nombre_conocido', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
