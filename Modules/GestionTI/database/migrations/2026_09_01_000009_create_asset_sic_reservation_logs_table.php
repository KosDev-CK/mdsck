<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora dedicada del cambio manual de SIC reservada sobre un activo
     * `reservado` (sección 7.11 del spec original — "excepciones"). NO es el
     * `AuditLog` transversal del spec, esa pieza es una fase futura separada
     * y no se construye aquí. Ver docs/gestionti-progreso.md, Fase 3 etapa 7.
     */
    public function up(): void
    {
        Schema::create('asset_sic_reservation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            // Sin FK real hacia `solicitudes_sic_borrador` — apuntan ahí solo
            // por convención, es una bitácora histórica que no necesita
            // "cerrarse" como sí se hizo con `assets.sic_reservada_id` en
            // Fase 3 etapa 3 (ese campo sí necesitaba integridad referencial
            // real porque conduce lógica de negocio en vivo).
            $table->unsignedBigInteger('sic_anterior_id')->nullable();
            $table->unsignedBigInteger('sic_nueva_id')->nullable();
            $table->text('motivo');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_sic_reservation_logs');
    }
};
