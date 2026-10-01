<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira `configuracion_categorias` (singleton `categorias_compra` JSON) —
 * su función la cubre ahora `categorias_articulo.es_compra` (ver migración
 * `2026_09_30_000001_convert_categoria_articulo_to_real_catalog.php`). La
 * pantalla standalone que la consumía ("Categorías que van a Compra",
 * `Modules\GestionTI\Livewire\Configuracion\CategoriasCompra`) nunca se
 * llegó a sembrar en producción — se retira sin migración de continuidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('configuracion_categorias');
    }

    public function down(): void
    {
        Schema::create('configuracion_categorias', function (Blueprint $table) {
            $table->id();
            $table->json('categorias_compra');
            $table->timestamps();
        });
    }
};
