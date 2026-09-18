<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 — campos nuevos confirmados contra la instancia real de SDP:
 * item.name, service_category.name, level.name, assigned_time (mismo shape
 * {value,display_value} que created_time/etc.), time_elapsed (string plano
 * de segundos, NO envuelto en {value,display_value}), y
 * resolution.submitted_by.name (reutiliza el campo "resolution" ya pedido en
 * fields_required, sin agregarlo otra vez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->string('articulo')->nullable()->after('subcategoria');
            $table->string('categoria_servicio')->nullable()->after('articulo');
            $table->string('nivel')->nullable()->after('grupo');
            $table->dateTime('assigned_time')->nullable()->after('due_time');
            // time_elapsed llega como string plano de segundos (ej.
            // "256000"), no como objeto {value, display_value}.
            $table->unsignedInteger('tiempo_transcurrido_segundos')->nullable()->after('assigned_time');
            $table->string('resuelto_por')->nullable()->after('resolucion');
        });
    }

    public function down(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->dropColumn([
                'articulo',
                'categoria_servicio',
                'nivel',
                'assigned_time',
                'tiempo_transcurrido_segundos',
                'resuelto_por',
            ]);
        });
    }
};
