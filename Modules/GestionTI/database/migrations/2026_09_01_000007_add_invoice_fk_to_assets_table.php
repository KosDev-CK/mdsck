<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierra el último TODO de FK pendiente de Fase 2/Fase 3 (ver
     * docs/gestionti-progreso.md): `assets.invoice_id` se dejó como
     * `unsignedBigInteger` plano porque `invoices` no existía todavía.
     * Mismo patrón `Schema::table()->foreign(...)` ya usado 3 veces antes
     * en este módulo.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });
    }
};
