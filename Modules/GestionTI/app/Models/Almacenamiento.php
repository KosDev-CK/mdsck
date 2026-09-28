<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Almacenamiento extends Model
{
    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
