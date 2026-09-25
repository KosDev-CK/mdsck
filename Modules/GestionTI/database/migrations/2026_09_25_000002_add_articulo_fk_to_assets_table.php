<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liga el Activo físico al Artículo del catálogo del que salió (compra o
     * alta manual) — mismo patrón que las 3 migraciones previas que solo
     * agregan una FK nullable a esta tabla (`recepcion_linea_id`/
     * `sic_reservada_id`/`invoice_id`). Ver docs/gestionti-progreso.md,
     * entrada "Catálogo unificado de Artículos".
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('articulo_id')->nullable()->after('modelo_id')->constrained('articulos_solicitud')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['articulo_id']);
            $table->dropColumn('articulo_id');
        });
    }
};
