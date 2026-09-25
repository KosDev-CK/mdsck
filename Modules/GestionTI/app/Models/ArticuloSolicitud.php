<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\GestionTI\Support\Catalogos\CategoriaArticulo;

class ArticuloSolicitud extends Model
{
    protected $table = 'articulos_solicitud';

    /**
     * Alias de `CategoriaArticulo::OPTIONS`/`LABELS` — mismas 11 categorías
     * que `ProyectoPresupuestoArticulo`, ver docs/gestionti-progreso.md,
     * entrada "Catálogo unificado de Artículos".
     */
    public const CATEGORIAS = CategoriaArticulo::OPTIONS;

    public const CATEGORIA_LABELS = CategoriaArticulo::LABELS;

    protected $fillable = [
        'codigo', 'descripcion', 'unidad_medida', 'categoria', 'tipo_equipo_id', 'activo',
        'marca_id', 'modelo_id', 'procesador', 'ram', 'almacenamiento', 'es_inventariable',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_inventariable' => 'boolean',
    ];

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function modelo()
    {
        return $this->belongsTo(Modelo::class);
    }
}
