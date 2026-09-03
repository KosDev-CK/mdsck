<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_compliances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained('assets')->cascadeOnDelete();
            $table->boolean('crowdstrike')->nullable();
            $table->date('crowdstrike_fecha')->nullable();
            $table->boolean('bitlocker')->nullable();
            $table->foreignId('licencia_1_id')->nullable()->constrained('licencias')->nullOnDelete();
            $table->foreignId('licencia_2_id')->nullable()->constrained('licencias')->nullOnDelete();
            $table->date('mantenimiento_preventivo')->nullable();
            $table->date('fecha_validacion')->nullable();
            $table->foreignId('validado_por_id')->nullable()->constrained('validadores')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_compliances');
    }
};
