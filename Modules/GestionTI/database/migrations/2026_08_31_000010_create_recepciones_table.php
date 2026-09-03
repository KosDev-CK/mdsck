<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_proveedor_id')->constrained('solicitudes_proveedor')->restrictOnDelete();
            // Folio de remisión del PROVEEDOR (no un folio propio del
            // sistema) — texto libre, tal como lo trae el documento físico.
            $table->string('folio_remision');
            $table->date('fecha_recepcion');
            // "Persona que recibe" del spec — modelada como Validador, no
            // Empleado, mismo criterio ya usado en `Asset.dado_de_alta_por_id`,
            // `AssetCompliance.validado_por_id` y
            // `AssetAssignment.responsable_entrega_id` para "quien hizo la
            // acción internamente".
            $table->foreignId('recibido_por_id')->constrained('validadores')->restrictOnDelete();
            $table->foreignId('documento_remision_id')->nullable()->constrained('documentos_digitalizados')->nullOnDelete();
            // Destino físico de los activos que entran en esta remisión — un
            // solo destino por recepción, no por línea/unidad (ver nota de
            // diseño en docs/gestionti-progreso.md).
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepciones');
    }
};
