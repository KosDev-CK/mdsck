<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $fillable = ['razon_social', 'nombre_comercial', 'rfc', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
