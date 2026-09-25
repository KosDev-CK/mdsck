<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite elegir el Artículo inventariable desde la captura local de SIC
     * — opcional a propósito: las SIC que llegan sincronizadas de Oracle EBS
     * no traen esta clasificación, así que se queda vacío hasta que alguien
     * lo complete a mano. Ver docs/gestionti-progreso.md, entrada "Catálogo
     * unificado de Artículos".
     */
    public function up(): void
    {
        Schema::table('solicitudes_sic_borrador', function (Blueprint $table) {
            $table->foreignId('articulo_id')->nullable()->after('tipo_equipo_id')->constrained('articulos_solicitud')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_sic_borrador', function (Blueprint $table) {
            $table->dropForeign(['articulo_id']);
            $table->dropColumn('articulo_id');
        });
    }
};
