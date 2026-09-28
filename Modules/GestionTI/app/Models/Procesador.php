<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Procesador extends Model
{
    // Eloquent pluraliza "Procesador" en inglés ("procesadors"), no en
    // español — se declara explícito, mismo patrón que `Validador`.
    protected $table = 'procesadores';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
