<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton (siempre `id = 1`, mismo patrón que `configuracion_documentos`)
 * que decide, de las 11 categorías de `CategoriaArticulo::OPTIONS`, cuáles
 * representan una compra real de equipo físico que debe pasar por Compras
 * (laptops, PCs, impresoras...) — el resto (telefonía fija/celular,
 * licencias, correo 365, etc.) se reporta a otra área sin generar una
 * Solicitud a Proveedores. Ver `Modules\GestionTI\Models\ConfiguracionCategorias`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_categorias', function (Blueprint $table) {
            $table->id();
            // Subconjunto de las 11 categorías de CategoriaArticulo::OPTIONS
            // — texto libre en JSON, no tabla normalizada aparte, mismo
            // criterio que `configuracion_documentos.tipos_sharepoint`
            // (volumen máximo de 11 elementos, siempre se lee/escribe
            // completo, nunca se consulta por elemento individual).
            $table->json('categorias_compra');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_categorias');
    }
};
