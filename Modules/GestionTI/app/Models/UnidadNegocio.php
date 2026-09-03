<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadNegocio extends Model
{
    protected $table = 'unidades_negocio';

    protected $fillable = ['nombre', 'nombre_conocido', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
