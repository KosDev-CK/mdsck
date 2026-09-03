<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `categoria` es texto libre validado en la capa de aplicación contra una
     * lista fija de 11 valores (`ProyectoPresupuestoArticulo::CATEGORIAS`) —
     * mismo patrón que `tipo_solicitud`/`urgencia` en otras pantallas de este
     * módulo, no es un catálogo con tabla propia.
     */
    public function up(): void
    {
        Schema::create('proyecto_presupuesto_articulos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyecto_presupuestos')->cascadeOnDelete();
            $table->string('categoria');
            $table->string('descripcion');
            $table->integer('cantidad');
            $table->foreignId('responsable_costo_id')->constrained('empleados')->restrictOnDelete();
            $table->decimal('costo_unitario', 10, 2)->nullable();
            // pendiente -> capturado
            $table->string('estatus_captura')->default('pendiente');
            $table->date('fecha_captura')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_presupuesto_articulos');
    }
};
