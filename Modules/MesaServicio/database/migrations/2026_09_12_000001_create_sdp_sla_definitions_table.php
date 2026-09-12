<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_sla_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            // Debe calzar EXACTO con sdp_tickets.prioridad (texto libre, no
            // enum) — no tenemos confirmados los nombres reales de prioridad
            // de esta instancia SDP todavía (ver docs/mesaservicio-progreso.md,
            // Fase 7). null = definición "por defecto" (catch-all), aplica a
            // cualquier ticket cuya prioridad no tenga una definición
            // específica activa.
            $table->string('prioridad')->nullable();
            $table->unsignedInteger('tiempo_primera_respuesta_minutos')->nullable();
            $table->unsignedInteger('tiempo_resolucion_minutos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_sla_definitions');
    }
};
