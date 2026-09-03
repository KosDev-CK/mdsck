<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Modelo extends Model
{
    protected $fillable = ['nombre', 'marca_id', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }
}
