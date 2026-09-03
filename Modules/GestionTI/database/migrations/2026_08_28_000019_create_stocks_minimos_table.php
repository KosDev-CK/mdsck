<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks_minimos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_equipo_id')->constrained('tipos_equipo')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->unsignedInteger('cantidad_minima');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo_equipo_id', 'ubicacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks_minimos');
    }
};
