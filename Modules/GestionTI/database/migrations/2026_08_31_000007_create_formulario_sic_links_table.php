<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Capa de datos únicamente — sin pantalla, sin lógica de generación de
     * link real ni envío de correo (eso depende del módulo de Formularios
     * separado, Fase 5). El flujo manual de esta etapa de Fase 3 crea el
     * borrador de SIC directamente sin pasar por aquí; esta tabla queda lista
     * para cuando la integración real exista.
     */
    public function up(): void
    {
        Schema::create('formulario_sic_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
            // Token/id del link externo generado por el módulo de
            // Formularios — null hasta que esa integración exista.
            $table->string('token_o_referencia_externa')->nullable();
            $table->string('estatus')->default('pendiente'); // pendiente/respondido/expirado
            $table->timestamp('fecha_generacion')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->foreignId('solicitud_sic_borrador_id')->nullable()->constrained('solicitudes_sic_borrador')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formulario_sic_links');
    }
};
