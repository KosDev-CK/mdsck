<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierra los 3 TODOs de FK dejados pendientes por la Fase 2 en
     * `asset_assignments` (ver docs/gestionti-progreso.md, sección Fase 2):
     * en ese momento `tickets`/`solicitudes_sic_borrador`/
     * `documentos_digitalizados` todavía no existían, así que `ticket_id`/
     * `sic_id`/`documento_responsiva_id` se dejaron como `unsignedBigInteger`
     * planos sin constraint. Las 3 tablas ya existen (Fase 3, etapa 1) — se
     * agrega la constraint real vía `Schema::table()` sobre las columnas ya
     * existentes, mismo patrón ya confirmado en
     * `2026_08_31_000012_add_recepcion_and_sic_reservada_fk_to_assets_table`.
     */
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreign('sic_id')->references('id')->on('solicitudes_sic_borrador')->nullOnDelete();
            $table->foreign('documento_responsiva_id')->references('id')->on('documentos_digitalizados')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropForeign(['sic_id']);
            $table->dropForeign(['documento_responsiva_id']);
        });
    }
};
