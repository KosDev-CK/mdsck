<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierra los últimos 2 TODOs de FK dejados pendientes por Fase 2/Fase 3
     * etapa 2 (ver docs/gestionti-progreso.md): en ese momento
     * `proyecto_presupuestos`/`proyecto_presupuesto_articulos` todavía no
     * existían, así que `assets.proyecto_presupuesto_id` y
     * `solicitudes_proveedor.proyecto_presupuesto_articulo_id` se dejaron
     * como `unsignedBigInteger` planos sin constraint. Mismo patrón
     * `Schema::table()->foreign(...)` ya usado 2 veces antes en este módulo
     * (Fase 3 etapas 3 y 4) para cerrar FKs de `assets`/`asset_assignments`.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('proyecto_presupuesto_id')->references('id')->on('proyecto_presupuestos')->nullOnDelete();
        });

        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->foreign('proyecto_presupuesto_articulo_id')->references('id')->on('proyecto_presupuesto_articulos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['proyecto_presupuesto_id']);
        });

        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->dropForeign(['proyecto_presupuesto_articulo_id']);
        });
    }
};
