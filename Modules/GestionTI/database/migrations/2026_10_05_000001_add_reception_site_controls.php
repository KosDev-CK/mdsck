<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de recepción por sitio de entrega:
 *
 * - `lugares_entrega.ubicacion_id`: a qué Ubicación física (inventario) llegan
 *   los activos recibidos en ese lugar — antes se capturaba "Ubicación destino"
 *   a mano en cada recepción; ahora sale del lugar de entrega de la línea.
 * - `validadores.lugar_entrega_id`: sitio que atiende el técnico receptor. Con
 *   valor, solo puede recibir las líneas de ese lugar; vacío = cualquier sitio.
 * - `recepciones.lugar_entrega_id` / `registrado_por_user_id`: sitio recibido
 *   (una recepción = un lugar de entrega) y usuario que la registró, para el
 *   reporte de entregas / evaluación del proveedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lugares_entrega', function (Blueprint $table) {
            $table->foreignId('ubicacion_id')->nullable()->after('nombre')->constrained('ubicaciones')->nullOnDelete();
        });

        Schema::table('validadores', function (Blueprint $table) {
            $table->foreignId('lugar_entrega_id')->nullable()->constrained('lugares_entrega')->nullOnDelete();
        });

        Schema::table('recepciones', function (Blueprint $table) {
            $table->foreignId('lugar_entrega_id')->nullable()->after('ubicacion_id')->constrained('lugares_entrega')->nullOnDelete();
            $table->foreignId('registrado_por_user_id')->nullable()->after('lugar_entrega_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrado_por_user_id');
            $table->dropConstrainedForeignId('lugar_entrega_id');
        });

        Schema::table('validadores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lugar_entrega_id');
        });

        Schema::table('lugares_entrega', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ubicacion_id');
        });
    }
};
