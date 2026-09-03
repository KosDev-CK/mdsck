<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encabezado de "Presupuesto por Proyecto" (secciones 5.1/7.5 del spec
     * original) — ver docs/gestionti-progreso.md, Fase 3, para el diseño
     * completo. `area_operativa_solicitante_id` reutiliza el catálogo núcleo
     * `areas` ya existente (mismo concepto de área que ya usa `Empleado`,
     * no es un catálogo nuevo).
     */
    public function up(): void
    {
        Schema::create('proyecto_presupuestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_proyecto');
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('centro_costo_id')->constrained('centros_costo')->restrictOnDelete();
            $table->string('direccion_centro');
            $table->foreignId('area_operativa_solicitante_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('pm_responsable_id')->constrained('empleados')->restrictOnDelete();
            $table->date('fecha_solicitud');
            $table->date('fecha_limite_captura');
            // armado -> en_captura_costos -> completo -> en_autorizacion -> autorizado|rechazado
            $table->string('estatus')->default('armado');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_presupuestos');
    }
};
