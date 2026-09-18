<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * grupo_analitico_id es un SEGUNDO campo editable a mano desde la pantalla
 * "Técnicos", igual en espíritu que es_nivel_1: nullOnDelete porque
 * desactivar/borrar un grupo analítico no debe destruir el registro del
 * técnico, solo dejarlo sin clasificar. sdp:sync-technicians nunca toca
 * esta columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdp_technicians', function (Blueprint $table) {
            $table->foreignId('grupo_analitico_id')->nullable()->after('es_nivel_1')
                ->constrained('grupos_analiticos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sdp_technicians', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grupo_analitico_id');
        });
    }
};
