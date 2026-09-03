<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Autorización multi-nivel de un `ProyectoPresupuesto`. El aprobador es
     * un `Empleado` (no un `Validador`) — a diferencia de los campos "quien
     * ejecutó la acción internamente" ya vistos en Recepción/Asignación, aquí
     * el aprobador es alguien de la línea de mando organizacional (ej. un
     * Director), que ya vive como `Empleado` con sus relaciones
     * `director()`/`directorEjecutivo()` — ver docs/gestionti-progreso.md.
     *
     * Nombre de tabla explícito requerido en el modelo: la pluralización
     * automática de Eloquent para la clase `ProyectoPresupuestoAutorizacion`
     * daría `proyecto_presupuesto_autorizacions`, no `..._autorizaciones` —
     * mismo riesgo ya documentado repetidamente en este módulo.
     */
    public function up(): void
    {
        Schema::create('proyecto_presupuesto_autorizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyecto_presupuestos')->cascadeOnDelete();
            $table->integer('nivel');
            $table->foreignId('aprobador_id')->constrained('empleados')->restrictOnDelete();
            // pendiente -> aprobado|rechazado
            $table->string('estatus')->default('pendiente');
            $table->date('fecha_resolucion')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->unique(['proyecto_id', 'nivel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_presupuesto_autorizaciones');
    }
};
