<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->restrictOnDelete();

            // FK cerrada en 2026_08_31_000013_add_fks_to_asset_assignments_table
            // (Fase 3, etapa 4 — Asignación de Activo).
            $table->unsignedBigInteger('ticket_id')->nullable();

            // FK cerrada en 2026_08_31_000013_add_fks_to_asset_assignments_table
            // hacia `solicitudes_sic_borrador` (la tabla real, no
            // `solicitud_sic_borradors` como decía este comentario original).
            $table->unsignedBigInteger('sic_id')->nullable();

            $table->date('fecha_asignacion')->nullable();
            $table->date('fecha_devolucion')->nullable();
            $table->string('estado_equipo_entrega')->nullable();
            $table->text('accesorios_entregados')->nullable();
            $table->foreignId('responsable_entrega_id')->nullable()->constrained('validadores')->nullOnDelete();
            $table->text('observaciones')->nullable();

            // FK cerrada en 2026_08_31_000013_add_fks_to_asset_assignments_table
            // hacia `documentos_digitalizados` (la tabla real, no
            // `documento_digitalizados` como decía este comentario original).
            $table->unsignedBigInteger('documento_responsiva_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
