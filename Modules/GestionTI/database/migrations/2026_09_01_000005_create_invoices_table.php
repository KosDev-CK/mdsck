<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facturación (sección 7.9 del spec original) — SIN Orden de Compra.
     * Ver docs/gestionti-progreso.md, Fase 3 etapa 6, para el recorte de
     * alcance explícito: `PurchaseOrder` no se construye en este módulo,
     * vive en el ERP externo del usuario.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('folio_factura');
            $table->foreignId('vendor_id')->constrained('proveedores')->restrictOnDelete();
            $table->date('fecha_recepcion');
            $table->decimal('monto_total', 10, 2);
            // MXN / USD — texto libre validado en la capa de aplicación
            // contra Invoice::MONEDAS, mismo patrón que tipo_solicitud/
            // urgencia de etapas anteriores (no es catálogo con tabla propia).
            $table->string('moneda')->default('MXN');
            // recibida -> registrada -> autorizada -> pagada. Espejo del
            // flujo real de otro sistema (Oracle Payables o similar), sin
            // lógica de negocio real, solo transiciones manuales
            // secuenciales — no hay estatus de rechazo.
            $table->string('estatus')->default('recibida');
            $table->date('fecha_autorizacion')->nullable();
            $table->date('fecha_pago')->nullable();
            // Ganchos sin lógica para un proyecto externo futuro
            // ("Presupuesto de TI", capex/gasto general — distinto de
            // "Presupuesto por Proyecto" ya construido). Solo texto libre
            // opcional, sin FK ni validación especial.
            $table->string('partida_presupuestal')->nullable();
            $table->string('ejercicio_fiscal')->nullable();
            $table->boolean('diferencia_a_revisar')->default(false);
            $table->timestamps();

            $table->unique(['vendor_id', 'folio_factura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
