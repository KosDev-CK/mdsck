<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierra 2 de los TODOs de FK dejados pendientes por la Fase 2
     * (ver docs/gestionti-progreso.md, sección Fase 2): en ese momento
     * `recepcion_lineas` y `solicitudes_sic_borrador` todavía no existían,
     * así que `assets.recepcion_linea_id`/`assets.sic_reservada_id` se
     * dejaron como `unsignedBigInteger` planos sin constraint. Ambas tablas
     * ya existen (esta misma Fase 3: `recepcion_lineas` en la migración
     * anterior, `solicitudes_sic_borrador` desde la etapa 1) — se agrega la
     * constraint real vía `Schema::table()` en vez de recrear las columnas,
     * confirmado que Laravel soporta `->foreign()` sobre una columna ya
     * existente tanto en MySQL como en SQLite (usado por la suite de tests).
     *
     * `assets.proyecto_presupuesto_id`/`assets.invoice_id` (y los 3
     * placeholders de `asset_assignments`) siguen sin tabla real — no se
     * tocan aquí.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('recepcion_linea_id')->references('id')->on('recepcion_lineas')->nullOnDelete();
            $table->foreign('sic_reservada_id')->references('id')->on('solicitudes_sic_borrador')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['recepcion_linea_id']);
            $table->dropForeign(['sic_reservada_id']);
        });
    }
};
