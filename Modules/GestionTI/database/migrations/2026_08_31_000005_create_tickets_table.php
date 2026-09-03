<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            // Referencia externa a ServiceDesk Plus — manual por ahora, la
            // integración real (API) queda pausada en Fase 5.
            $table->string('sdp_id')->nullable();
            $table->string('sdp_display_id')->nullable();
            $table->date('fecha');
            $table->foreignId('empleado_id')->constrained('empleados')->restrictOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
