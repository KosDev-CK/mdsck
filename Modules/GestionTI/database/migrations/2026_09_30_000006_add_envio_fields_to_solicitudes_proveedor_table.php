<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de envío/reenvío por correo al proveedor (ver
 * docs/gestionti-progreso.md) — `enviada_at` se escribe solo la primera vez
 * que se envía (es el campo que determina el bloqueo de edición para un
 * usuario normal), `ultimo_envio_at` se actualiza en cada envío y reenvío
 * (solo de referencia). `creado_por_user_id` es el usuario que creó la
 * Solicitud a Proveedor, escrito una sola vez en `create()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->timestamp('enviada_at')->nullable()->after('estatus');
            $table->timestamp('ultimo_envio_at')->nullable()->after('enviada_at');
            $table->foreignId('creado_por_user_id')->nullable()->after('ultimo_envio_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->dropForeign(['creado_por_user_id']);
            $table->dropColumn(['enviada_at', 'ultimo_envio_at', 'creado_por_user_id']);
        });
    }
};
