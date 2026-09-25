<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registra el Artículo REALMENTE recibido (editable en Recepción,
     * precargado del `articulo_id` de la `SolicitudProveedorLinea`
     * correspondiente pero puede diferir — sustitución del proveedor, etc.)
     * — es este valor, no el de la solicitud, el que se hereda hacia el
     * `Asset` creado. Ver docs/gestionti-progreso.md, entrada "Catálogo
     * unificado de Artículos".
     */
    public function up(): void
    {
        Schema::table('recepcion_lineas', function (Blueprint $table) {
            $table->foreignId('articulo_id')->nullable()->after('solicitud_proveedor_linea_id')->constrained('articulos_solicitud')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_lineas', function (Blueprint $table) {
            $table->dropForeign(['articulo_id']);
            $table->dropColumn('articulo_id');
        });
    }
};
