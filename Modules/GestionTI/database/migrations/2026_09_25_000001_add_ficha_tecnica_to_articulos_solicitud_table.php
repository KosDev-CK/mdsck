<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expande `ArticuloSolicitud` de un catálogo pobre (código/descripción
     * libre/categoría libre/tipo de equipo opcional) a un catálogo con ficha
     * técnica real, para que Compras e Inventario compartan un solo
     * "Artículo" definible una vez y reutilizable en ambos lugares — ver
     * docs/gestionti-progreso.md, entrada "Catálogo unificado de Artículos".
     */
    public function up(): void
    {
        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->foreignId('marca_id')->nullable()->after('categoria')->constrained('marcas')->nullOnDelete();
            $table->foreignId('modelo_id')->nullable()->after('marca_id')->constrained('modelos')->nullOnDelete();
            $table->string('procesador')->nullable()->after('modelo_id');
            $table->string('ram')->nullable()->after('procesador');
            $table->string('almacenamiento')->nullable()->after('ram');
            $table->boolean('es_inventariable')->default(false)->after('almacenamiento');
        });
    }

    public function down(): void
    {
        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->dropForeign(['marca_id']);
            $table->dropForeign(['modelo_id']);
            $table->dropColumn(['marca_id', 'modelo_id', 'procesador', 'ram', 'almacenamiento', 'es_inventariable']);
        });
    }
};
