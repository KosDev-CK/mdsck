<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte "Categoría" de una lista fija en código
 * (`Modules\GestionTI\Support\Catalogos\CategoriaArticulo::OPTIONS`/
 * `LABELS`, retirada) a un catálogo real (`categorias_articulo`) editable
 * desde la pestaña "Categoría" de "Catálogos de Compras" — a petición
 * explícita del usuario tras ver en producción la pantalla standalone
 * "Categorías que van a Compra" (`configuracion_categorias`, retirada por
 * la migración siguiente).
 *
 * Riesgo conocido y protegido: el valor `'laptops_desktops'` dispara lógica
 * de negocio real (`Compras\SolicitudesProveedor`). Protección de 2 capas:
 * 1) `slug` se genera una sola vez al crear y nunca se vuelve a exponer como
 *    campo editable — el código matchea contra `slug`, nunca contra
 *    `nombre` (la etiqueta visible, sí editable libremente).
 * 2) `categoria_id` es FK `restrictOnDelete()` (no `nullOnDelete`) — una
 *    categoría en uso no se puede borrar ni saltándose la UI.
 *
 * Sigue el mismo patrón de estilo que
 * `2026_09_25_000009_convert_ficha_tecnica_specs_to_catalogs_in_articulos_solicitud_table.php`.
 */
return new class extends Migration
{
    /**
     * Las 11 categorías originales de `CategoriaArticulo::OPTIONS`/`LABELS`
     * — slug y label literales, `es_compra = false` para las 11 (nunca se
     * llegó a configurar nada real en `configuracion_categorias` en
     * producción, confirmado con el usuario).
     */
    private const CATEGORIAS_ORIGINALES = [
        'celulares' => 'Celulares',
        'telefonia_fija' => 'Telefonía fija',
        'laptops_desktops' => 'Laptops/Desktops',
        'multifuncionales' => 'Multifuncionales',
        'redes' => 'Redes',
        'comunicacion' => 'Comunicación',
        'internet' => 'Internet',
        'infraestructura' => 'Infraestructura',
        'vpn' => 'VPN',
        'ciberseguridad' => 'Ciberseguridad',
        'antivirus' => 'Antivirus',
    ];

    public function up(): void
    {
        Schema::create('categorias_articulo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->boolean('es_compra')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        foreach (self::CATEGORIAS_ORIGINALES as $slug => $nombre) {
            DB::table('categorias_articulo')->insert([
                'nombre' => $nombre,
                'slug' => $slug,
                'es_compra' => false,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Defensivo: si producción ya tiene, en `articulos_solicitud` o
        // `proyecto_presupuesto_articulos`, algún valor de `categoria` que
        // haya divergido de las 11 originales, se crea también como
        // categoría real (nombre = slug crudo, ya viene en formato slug).
        $this->crearCategoriasFaltantes('articulos_solicitud');
        $this->crearCategoriasFaltantes('proyecto_presupuesto_articulos');

        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->after('categoria')->constrained('categorias_articulo')->restrictOnDelete();
        });

        Schema::table('proyecto_presupuesto_articulos', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->after('categoria')->constrained('categorias_articulo')->restrictOnDelete();
        });

        $this->backfillCategoriaId('articulos_solicitud');
        $this->backfillCategoriaId('proyecto_presupuesto_articulos');

        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });

        Schema::table('proyecto_presupuesto_articulos', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }

    private function crearCategoriasFaltantes(string $table): void
    {
        $valores = DB::table($table)
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->distinct()
            ->pluck('categoria');

        foreach ($valores as $valor) {
            $existe = DB::table('categorias_articulo')->where('slug', $valor)->exists();

            if (! $existe) {
                DB::table('categorias_articulo')->insert([
                    'nombre' => $valor,
                    'slug' => $valor,
                    'es_compra' => false,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function backfillCategoriaId(string $table): void
    {
        $valores = DB::table($table)
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->distinct()
            ->pluck('categoria');

        foreach ($valores as $valor) {
            $categoriaId = DB::table('categorias_articulo')->where('slug', $valor)->value('id');

            if ($categoriaId !== null) {
                DB::table($table)->where('categoria', $valor)->update(['categoria_id' => $categoriaId]);
            }
        }
    }

    /**
     * Nota: no reconstruye el texto original en `categoria` (solo la deja
     * nula) — mismo criterio ya aceptado en la migración de referencia, el
     * `down()` no necesita reconstruir los datos de texto perfectamente.
     */
    public function down(): void
    {
        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->string('categoria')->nullable()->after('descripcion');
        });

        Schema::table('proyecto_presupuesto_articulos', function (Blueprint $table) {
            $table->string('categoria')->nullable()->after('proyecto_id');
        });

        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::table('proyecto_presupuesto_articulos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::dropIfExists('categorias_articulo');
    }
};
