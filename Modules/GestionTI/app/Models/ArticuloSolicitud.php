<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class ArticuloSolicitud extends Model
{
    protected $table = 'articulos_solicitud';

    protected $fillable = [
        'codigo', 'descripcion', 'unidad_medida', 'categoria', 'tipo_equipo_id', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class);
    }
}
