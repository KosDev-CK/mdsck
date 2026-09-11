<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_technicians', function (Blueprint $table) {
            $table->id();
            $table->string('sdp_id')->unique();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('puesto')->nullable();
            // true si el técnico apareció en la ventana de sync más reciente
            // (últimos 12 meses de tickets) — nunca se borra el registro.
            $table->boolean('activo')->default(true);
            // Único campo editado manualmente desde la pantalla del catálogo
            // — jamás se sobreescribe en el comando de sincronización.
            $table->boolean('es_nivel_1')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_technicians');
    }
};
