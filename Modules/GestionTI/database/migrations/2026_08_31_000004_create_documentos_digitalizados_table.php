<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_digitalizados', function (Blueprint $table) {
            $table->id();
            // Entidad dueña del documento, por nombre de clase base (ej.
            // 'SolicitudSicBorrador') + su id — llave genérica reutilizable
            // por varias pantallas futuras de Fase 3 (Recepción, Asignación,
            // Mantenimiento, Invoice), no una relación morph real de
            // Eloquent porque no hay necesidad de eager-loading polimórfico
            // todavía, solo de guardar/consultar por entidad+id.
            $table->string('entidad_relacionada');
            $table->unsignedBigInteger('entidad_id');
            // Valor usado hoy: 'sic'. Otros valores futuros (sin migración
            // nueva porque es texto libre): 'responsiva', 'remision_proveedor',
            // 'factura', 'orden_servicio'.
            $table->string('tipo_documento');
            // Hoy siempre 'local' (disco `public`). Fase 5 lo cambiará a
            // 'sharepoint' cuando exista esa integración real.
            $table->string('proveedor_almacenamiento')->default('local');
            $table->string('referencia');
            $table->string('nombre_archivo');
            $table->timestamp('fecha_subida');
            $table->foreignId('subido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entidad_relacionada', 'entidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_digitalizados');
    }
};
