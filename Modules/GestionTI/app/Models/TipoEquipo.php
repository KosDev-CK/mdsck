<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEquipo extends Model
{
    protected $table = 'tipos_equipo';

    protected $fillable = ['nombre', 'nombre_conocido', 'en_alcance', 'activo'];

    protected $casts = [
        'en_alcance' => 'boolean',
        'activo' => 'boolean',
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}
