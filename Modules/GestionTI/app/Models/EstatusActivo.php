<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class EstatusActivo extends Model
{
    protected $table = 'estatus_activo';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
