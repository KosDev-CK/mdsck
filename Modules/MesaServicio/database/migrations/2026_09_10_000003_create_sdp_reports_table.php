<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_reports', function (Blueprint $table) {
            $table->id();
            // 'diario' por ahora (Fase 4) — deja espacio para 'mensual' en la
            // Fase 5, sin implementarlo todavía.
            $table->string('tipo');
            // Día (o mes, en la futura Fase 5) que cubre el reporte.
            $table->date('periodo');
            // Path relativo dentro del disco 'local' (privado), ej.
            // "mesa-servicio/reportes/diario/2026-09-09.xlsx".
            $table->string('ruta_archivo');
            // Resumen de métricas ya calculado al momento de generar el
            // reporte (total, por estado, por técnico) — se guarda aparte
            // del Excel para poder mostrar un resumen rápido en la pantalla
            // de Reportes sin tener que abrir el archivo.
            $table->json('resumen_metricas');
            $table->dateTime('generado_en');

            $table->timestamps();

            $table->index(['tipo', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_reports');
    }
};
