<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 — detección de folios combinados vía el historial por-ticket de SDP
 * (`SyncTicketsCommand::detectMergesFromHistory()`). Cuando un ticket local
 * aparece como "absorbido" en una entrada `merge_with` del historial de otro
 * ticket, se marca aquí en vez de solo inferirlo desde la métrica estimada
 * de la Fase 5 (que sigue existiendo tal cual, sin tocarse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            // Folio (display_id) del ticket que absorbió a este — null
            // mientras el ticket no se detecte combinado.
            $table->string('combinado_con_display_id')->nullable()->after('estado_nombre');
            $table->dateTime('combinado_detectado_en')->nullable()->after('combinado_con_display_id');
        });
    }

    public function down(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->dropColumn(['combinado_con_display_id', 'combinado_detectado_en']);
        });
    }
};
