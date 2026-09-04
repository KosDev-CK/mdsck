<?php

namespace Modules\GestionTI\Support\Ayuda;

use InvalidArgumentException;

/**
 * Contenido de ayuda de cada pantalla — un archivo PHP por pantalla en
 * `resources/ayuda/data/{slug}.php`, cada uno devolviendo un array
 * `['titulo', 'concepto', 'resuelve', 'proceso' => [...], 'campos' => [['nombre', 'explicacion'], ...]]`.
 * Consumido tanto por el modal de ayuda (Tailwind, en pantalla, ver
 * `resources/views/components/ui/help-modal.blade.php`) como por el PDF
 * descargable (`Modules\GestionTI\Http\Controllers\Ayuda\AyudaPdfController`,
 * CSS plano para Dompdf) — para que el texto nunca se desincronice entre
 * ambas versiones. Un archivo por pantalla (no un solo `match()` gigante)
 * a propósito: permite agregar/editar el contenido de una pantalla sin
 * tocar el de las demás.
 */
class AyudaCatalog
{
    public static function existe(string $slug): bool
    {
        // $slug viene de la URL (ver routes/web.php) y se usa para construir
        // una ruta de archivo que después se `require`ea — sin esta
        // validación, algo como "../../../../algo" sería una vía de path
        // traversal / inclusión de archivos arbitrarios. Solo minúsculas,
        // dígitos y guion, igual que cualquier slug real del catálogo.
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return false;
        }

        return is_file(self::rutaArchivo($slug));
    }

    public static function contenido(string $slug): array
    {
        if (! self::existe($slug)) {
            throw new InvalidArgumentException("No hay contenido de ayuda para \"{$slug}\".");
        }

        return require self::rutaArchivo($slug);
    }

    private static function rutaArchivo(string $slug): string
    {
        return module_path('GestionTI', "resources/ayuda/data/{$slug}.php");
    }
}
