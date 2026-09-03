<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas';

    protected $fillable = ['nombre', 'nombre_conocido', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
