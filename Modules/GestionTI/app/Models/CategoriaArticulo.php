<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo real de categorías de Artículo (reemplaza la lista fija en código
 * `Modules\GestionTI\Support\Catalogos\CategoriaArticulo::OPTIONS`/`LABELS`,
 * retirada) — mantenimiento CRUD desde la pestaña "Categoría" de "Catálogos
 * de Compras" (`Modules\GestionTI\Livewire\Catalogos\Compras`).
 *
 * `slug` es la clave interna estable que usa el código para matchear lógica
 * de negocio real (ej. `'laptops_desktops'` en
 * `Compras\SolicitudesProveedor::sicPickerOptions()`/`render()`) — se genera
 * UNA SOLA VEZ al crear el registro (`Str::slug($nombre, '_')`, ver
 * `Catalogos\Compras::save()`) y NUNCA se vuelve a tocar ni se expone como
 * campo editable: el usuario puede renombrar `nombre` (la etiqueta visible)
 * libremente sin romper nada, porque el código nunca matchea contra
 * `nombre`. Doble protección de la migración que la creó: FK
 * `restrictOnDelete()` (no `nullOnDelete`) desde `articulos_solicitud`/
 * `proyecto_presupuesto_articulos` — una categoría en uso no se puede borrar
 * ni siquiera saltándose la UI.
 */
class CategoriaArticulo extends Model
{
    protected $table = 'categorias_articulo';

    protected $fillable = [
        'nombre', 'slug', 'es_compra', 'activo',
    ];

    protected $casts = [
        'es_compra' => 'boolean',
        'activo' => 'boolean',
    ];
}
