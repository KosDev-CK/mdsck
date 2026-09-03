<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodicidades_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_equipo_id')->unique()->constrained('tipos_equipo')->restrictOnDelete();
            $table->unsignedInteger('meses_sugeridos');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodicidades_mantenimiento');
    }
};
