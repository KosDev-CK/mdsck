<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 — catálogo de sitios geográficos de SDP (GET /sites), mismo estilo
 * simple de catálogo que sdp_technicians/sdp_ticket_statuses (sdp_id único +
 * columnas planas, sin jerarquía). Alimentado por `sdp:sync-sites` (manual,
 * sitios cambian rara vez) y, de forma perezosa/parcial (solo id+nombre), por
 * `SyncTicketsCommand::resolveSiteId()` cuando un ticket trae un sitio que
 * todavía no existe localmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_sites', function (Blueprint $table) {
            $table->id();
            $table->string('sdp_id')->unique();
            $table->string('nombre');
            $table->string('pais')->nullable();
            $table->string('estado')->nullable();
            $table->string('region')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('calle')->nullable();
            $table->string('numero_puerta')->nullable();
            $table->string('codigo_postal')->nullable();
            $table->string('localidad')->nullable();
            $table->string('punto_referencia')->nullable();
            $table->string('zona_horaria')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_sites');
    }
};
