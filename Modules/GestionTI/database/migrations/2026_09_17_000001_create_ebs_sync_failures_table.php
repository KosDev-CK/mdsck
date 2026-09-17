<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5, punto 1 (EBS) — seguimiento persistente de días que fallaron al
 * sincronizar contra Oracle EBS (`errorCode=10` con `payload` vacío,
 * observado sobre todo fines de semana y en los 2-3 días más recientes —
 * ver docs/gestionti-progreso.md), para poder reintentarlos de forma
 * selectiva (`gestionti:ebs-reintentar-fallidos`) sin perder de vista qué
 * días quedaron pendientes.
 *
 * Un solo registro por fecha+método (unique compuesto) — un fallo repetido
 * el mismo día+método actualiza el mismo registro (`intentos++`) en vez de
 * duplicarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebs_sync_failures', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('metodo');
            $table->integer('error_code')->nullable();
            $table->text('error_msg')->nullable();
            $table->unsignedInteger('intentos')->default(1);
            $table->dateTime('resuelto_at')->nullable();
            $table->string('resuelto_via')->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'metodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebs_sync_failures');
    }
};
