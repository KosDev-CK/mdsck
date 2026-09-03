<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_enviados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_aviso_id')->nullable()->constrained('tipos_aviso')->nullOnDelete();
            $table->string('entidad_relacionada');
            $table->unsignedBigInteger('entidad_id');
            $table->foreignId('destinatario_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('canal');
            $table->dateTime('fecha_envio');
            $table->string('estatus_envio');
            // Solo tiene sentido para canal=in_app — foto del momento del
            // envío, NO se mantiene sincronizado con notifications.read_at
            // en tiempo real (ver docs/gestionti-progreso.md).
            $table->boolean('leido')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_enviados');
    }
};
