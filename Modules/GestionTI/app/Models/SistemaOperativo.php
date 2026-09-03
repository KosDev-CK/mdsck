<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class SistemaOperativo extends Model
{
    protected $table = 'sistemas_operativos';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
