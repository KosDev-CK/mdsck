<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivote M:N Invoice<->Recepcion — el spec lo describe
     * explícitamente como M:N, no 1:N: una factura puede cubrir una o
     * varias remisiones y, en teoría, una misma remisión podría terminar
     * referenciada desde más de una factura en escenarios de facturación
     * parcial. Sin restricción que lo impida.
     */
    public function up(): void
    {
        Schema::create('invoice_recepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnDelete();

            $table->unique(['invoice_id', 'recepcion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_recepciones');
    }
};
