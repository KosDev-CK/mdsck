<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mantenimiento — Preventivo/Correctivo (sección 7.10 del spec
     * original). Ver docs/gestionti-progreso.md, Fase 3 etapa 9, para el
     * diseño completo.
     */
    public function up(): void
    {
        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete: mismo criterio ya usado en
            // `stock_movements.asset_id` — no tiene sentido conservar un
            // mantenimiento huérfano sin su activo.
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('tipo');
            // Solo aplica conceptualmente a `correctivo`, pero no se impide
            // a nivel de esquema que un preventivo tenga uno también.
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('origen_ejecucion');
            // Solo se captura cuando origen_ejecucion = externo.
            $table->foreignId('vendor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->decimal('costo', 10, 2)->nullable();
            $table->date('fecha_programada')->nullable();
            $table->date('fecha_realizada')->nullable();
            $table->string('estatus')->default('programado');
            // Solo se captura cuando origen_ejecucion = interno, al completar.
            $table->foreignId('realizado_por_id')->nullable()->constrained('validadores')->nullOnDelete();
            $table->text('diagnostico')->nullable();
            $table->foreignId('documento_id')->nullable()->constrained('documentos_digitalizados')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimientos');
    }
};
