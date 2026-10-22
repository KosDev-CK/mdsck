<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encargados de cada lugar de entrega: reciben copia de la Solicitud a
 * Proveedores con lo que deben recibir en su sitio. `encargados_avisados_at`
 * guarda cuándo fue el último aviso a los encargados de una solicitud.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lugar_entrega_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lugar_entrega_id')->constrained('lugares_entrega')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lugar_entrega_id', 'user_id']);
        });

        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->timestamp('encargados_avisados_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->dropColumn('encargados_avisados_at');
        });

        Schema::dropIfExists('lugar_entrega_usuario');
    }
};
