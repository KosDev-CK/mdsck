<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton (`id` siempre 1, mismo patrón que `ConfiguracionDocumentos`/
 * `App\Models\SiteSetting::current()`) que decide, de las 11 categorías de
 * `Modules\GestionTI\Support\Catalogos\CategoriaArticulo::OPTIONS`, cuáles
 * representan una compra real de equipo físico que debe pasar por Compras
 * (laptops, PCs, impresoras...) — el resto (telefonía fija/celular,
 * licencias, correo 365, etc.) se reporta a otra área sin pasar por
 * Compras. Pantalla asociada: "Categorías que van a Compra"
 * (`Modules\GestionTI\Livewire\Configuracion\CategoriasCompra`). Consumido
 * por el picker de SICs disponibles de `Compras\SolicitudesProveedor`.
 */
class ConfiguracionCategorias extends Model
{
    protected $table = 'configuracion_categorias';

    protected $fillable = [
        'categorias_compra',
    ];

    protected $casts = [
        'categorias_compra' => 'array',
    ];

    /**
     * Default al sembrarse por primera vez — deliberadamente vacío, mismo
     * criterio que `ConfiguracionDocumentos::DEFAULTS`: no se asume ninguna
     * división de antemano, el usuario decide con el checkbox desde la
     * pantalla. Mientras esté vacío, ninguna SIC aparece en el picker de
     * "Solicitud a Proveedores" — sigue disponible solo por la vía de
     * captura manual (folio de texto).
     */
    public const DEFAULTS = [
        'categorias_compra' => [],
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], self::DEFAULTS);
    }

    public function vaACompra(string $categoria): bool
    {
        return in_array($categoria, $this->categorias_compra ?? [], true);
    }
}
