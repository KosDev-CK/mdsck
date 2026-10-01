<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class ArticuloSolicitud extends Model
{
    protected $table = 'articulos_solicitud';

    protected $fillable = [
        'codigo', 'descripcion', 'unidad_medida', 'categoria_id', 'tipo_equipo_id', 'activo',
        'marca_id', 'modelo_id', 'procesador_id', 'ram_id', 'almacenamiento_id', 'es_inventariable',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_inventariable' => 'boolean',
    ];

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaArticulo::class, 'categoria_id');
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function modelo()
    {
        return $this->belongsTo(Modelo::class);
    }

    public function procesador()
    {
        return $this->belongsTo(Procesador::class);
    }

    public function ram()
    {
        return $this->belongsTo(Ram::class);
    }

    public function almacenamiento()
    {
        return $this->belongsTo(Almacenamiento::class);
    }
}
