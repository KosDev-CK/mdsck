<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Validador` no tenía ningún dato de contacto — necesario para que
 * "Configuración de Avisos" (Fase 4) pueda resolverlo a un `App\Models\User`
 * real cuando se usa como destinatario específico de un `TipoAviso`. Sin
 * `user_id` poblado (manual, nadie lo llena automáticamente) simplemente no
 * se le puede avisar por este sistema — mismo criterio de omisión silenciosa
 * que la resolución por correo de `Empleado`. Ver docs/gestionti-progreso.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('validadores', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('nombre')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('validadores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
