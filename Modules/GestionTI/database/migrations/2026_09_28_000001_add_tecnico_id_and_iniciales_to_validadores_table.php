<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los 14 registros reales de `Validador` en producción son, en el fondo,
 * técnicos de TI capturados como códigos sueltos (ej. "NKSM"/"AEHM"), con
 * datos sucios (duplicados, un "No aplica", una fila en blanco). El catálogo
 * real de técnicos ya existe en `Modules\MesaServicio` (`sdp_technicians`,
 * pantalla "Técnicos", ya en producción) — en vez de duplicar esa fuente de
 * verdad, `tecnico_id` permite enlazar cada `Validador` al técnico real que
 * representa. FK real (con constraint) porque `Modules\MesaServicio` ya está
 * desplegado en producción — es seguro. `iniciales` guarda el código corto
 * original (ej. "NKSM") como referencia/búsqueda, sin forzar que coincida
 * con nada de `sdp_technicians`.
 *
 * Ambas columnas nullable a propósito: los 14 registros existentes empiezan
 * en `null` — el usuario los enlaza a mano desde la pantalla después de este
 * cambio (sin backfill automático), y casos como "No aplica" se quedan sin
 * técnico real asociado para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validadores', function (Blueprint $table) {
            $table->foreignId('tecnico_id')->nullable()->after('user_id')->constrained('sdp_technicians')->nullOnDelete();
            $table->string('iniciales', 20)->nullable()->after('tecnico_id');
        });
    }

    public function down(): void
    {
        Schema::table('validadores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tecnico_id');
            $table->dropColumn('iniciales');
        });
    }
};
