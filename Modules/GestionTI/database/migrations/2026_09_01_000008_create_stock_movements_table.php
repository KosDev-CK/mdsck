<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock (sección 7.11 del spec original). Ver
     * docs/gestionti-progreso.md, Fase 3 etapa 7, para el diseño completo.
     *
     * Alcance explícito de esta etapa: esta migración soporta los 5 tipos
     * de movimiento del spec (`entrada`/`salida`/`asignacion`/`devolucion`/
     * `traslado`), pero la pantalla de Stock solo inserta filas
     * `tipo = 'traslado'` — no se retro-instrumentan Recepciones/
     * Asignaciones para generar `entrada`/`asignacion`/`devolucion`, eso
     * queda pendiente de una futura etapa.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete: mismo criterio ya usado en
            // `asset_compliances.asset_id` — no tiene sentido conservar el
            // registro de movimiento huérfano sin su activo.
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('tipo');
            $table->date('fecha');
            $table->foreignId('usuario_responsable_id')->nullable()->constrained('users')->nullOnDelete();
            // Referencia libre, sin FK real — mismo espíritu que
            // `entidad_relacionada`/`entidad_id` de `DocumentoDigitalizado`
            // pero sin duplicar ese patrón exacto (aquí no hay adjunto).
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->foreignId('ubicacion_origen_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->foreignId('ubicacion_destino_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->text('comentarios')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
