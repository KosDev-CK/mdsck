<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_proveedor', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('vendor_id')->constrained('proveedores')->restrictOnDelete();
            $table->date('fecha_solicitud');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('sic_id')->nullable()->constrained('solicitudes_sic_borrador')->nullOnDelete();
            // La FK real (`nullOnDelete` hacia `proyecto_presupuesto_articulos`)
            // se agrega en 2026_09_01_000004_add_proyecto_presupuesto_fks_table
            // — la tabla `proyecto_presupuesto_articulos` no existía todavía
            // cuando se escribió esta migración.
            $table->unsignedBigInteger('proyecto_presupuesto_articulo_id')->nullable();
            // regular / compra_especial — texto libre validado en la capa de
            // aplicación (no requiere catálogo propio, mismo patrón que
            // `urgencia` en SolicitudSicBorrador).
            $table->string('tipo_solicitud');
            // solicitada -> parcialmente_recibida|recibida|facturada|cancelada.
            // Esta etapa solo escribe solicitada/cancelada; los demás
            // estatus los escribirán las futuras etapas de Recepción y
            // Facturación.
            $table->string('estatus')->default('solicitada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_proveedor');
    }
};
