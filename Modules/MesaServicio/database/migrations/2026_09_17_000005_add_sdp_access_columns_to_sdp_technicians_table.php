<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 (Parte 1) — sdp:sync-technicians pasó a derivar el catálogo del
 * recurso /users (search_criteria is_technician=true) en vez de escanear
 * tickets de los últimos 12 meses. `zuid` es el id de cuenta de login de
 * Zoho/SDP: un valor numérico real indica que el técnico tiene un login
 * funcional; "-1" indica que el usuario está marcado como técnico en el
 * sentido de rol de SDP pero SIN acceso real. `tiene_acceso_sdp` es la
 * columna derivada (true cuando zuid está presente y no es "-1") que
 * responde directamente a "¿tiene inicio de sesión?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdp_technicians', function (Blueprint $table) {
            $table->string('zuid')->nullable()->after('puesto');
            $table->boolean('tiene_acceso_sdp')->default(false)->after('zuid');
        });
    }

    public function down(): void
    {
        Schema::table('sdp_technicians', function (Blueprint $table) {
            $table->dropColumn(['zuid', 'tiene_acceso_sdp']);
        });
    }
};
