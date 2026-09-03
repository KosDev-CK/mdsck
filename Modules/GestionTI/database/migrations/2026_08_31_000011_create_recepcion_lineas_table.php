<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepcion_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnDelete();
            $table->foreignId('solicitud_proveedor_linea_id')->constrained('solicitud_proveedor_lineas')->restrictOnDelete();
            // 1 para cada unidad inventariable (1 fila = 1 Asset); la
            // cantidad real recibida para una línea NO inventariable (sin
            // Asset asociado).
            $table->unsignedInteger('cantidad_recibida');
            // Solo se llena para renglones que generaron un Asset
            // (es_activo_inventariable = true en la línea de origen).
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_lineas');
    }
};
