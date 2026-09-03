<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_aviso_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_aviso_id')->constrained('tipos_aviso')->cascadeOnDelete();
            $table->string('tipo_destinatario');
            $table->string('rol_nombre')->nullable();
            $table->foreignId('validador_id')->nullable()->constrained('validadores')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_aviso_destinatarios');
    }
};
