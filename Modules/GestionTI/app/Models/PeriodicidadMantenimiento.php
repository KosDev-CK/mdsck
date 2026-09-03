<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodicidadMantenimiento extends Model
{
    protected $table = 'periodicidades_mantenimiento';

    protected $fillable = ['tipo_equipo_id', 'meses_sugeridos', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class);
    }
}
