<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 — FK opcional hacia el catálogo nuevo sdp_sites, resuelta en
 * SyncTicketsCommand::upsertTicket() de forma perezosa (igual que
 * resolveTechnicianId()). La columna string "sitio" ya existente NO se
 * quita — sigue siendo la forma simple de mostrar el sitio sin join para
 * código ya existente; esta FK es solo para las consultas geográficas más
 * ricas que habilita sdp_sites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->foreignId('sdp_site_id')->nullable()->after('sitio')
                ->constrained('sdp_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sdp_site_id');
        });
    }
};
