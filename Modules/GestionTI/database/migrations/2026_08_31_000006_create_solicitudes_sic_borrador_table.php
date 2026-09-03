<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nombre de tabla explícito porque la pluralización automática de
        // Eloquent para "SolicitudSicBorrador" daría "solicitud_sic_borradors"
        // (mismo riesgo ya documentado para Proveedor/Validador/etc. en Fase 1)
        // — aquí se fuerza a "solicitudes_sic_borrador" en la migración y en
        // el modelo (`protected $table`) para que coincidan.
        Schema::create('solicitudes_sic_borrador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados')->restrictOnDelete();
            $table->foreignId('tipo_equipo_id')->constrained('tipos_equipo')->restrictOnDelete();
            $table->text('motivo');
            $table->text('especificaciones_requeridas')->nullable();
            $table->foreignId('centro_costo_id')->constrained('centros_costo')->restrictOnDelete();
            $table->foreignId('unidad_negocio_id')->nullable()->constrained('unidades_negocio')->nullOnDelete();
            // baja/media/alta — campo de 3 valores fijos, validado en la capa
            // de aplicación (no requiere un catálogo propio).
            $table->string('urgencia');
            $table->date('fecha_solicitud');
            // capturado -> sic_creada -> autorizada|rechazada. El modo manual
            // arranca directo en 'capturado' (sin 'enviado'/'recibido' — esos
            // dependen del round-trip real de Formularios, Fase 5).
            $table->string('estatus')->default('capturado');
            // Folio de autorización en EBS — manual/texto libre por ahora,
            // sin lookup real (Fase 5).
            $table->string('folio_sic')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_sic_borrador');
    }
};
