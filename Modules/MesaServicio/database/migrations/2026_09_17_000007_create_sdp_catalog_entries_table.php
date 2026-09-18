<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 (Parte 2) — espejo local de los 12 catálogos de configuración de
 * SDP (categories, levels, modes, impacts, urgencies, priorities,
 * priority_matrices, request_types, task_types, worklog_types,
 * closure_codes, downtime_types), todos en una sola tabla genérica
 * (`catalogo` distingue de cuál se trata) en vez de 12 tablas casi idénticas
 * — mismo criterio de "catálogo simple" ya usado por sdp_sites/
 * sdp_ticket_statuses, pero unificado porque los 12 comparten
 * mayoritariamente la misma forma (id/name/description/deleted, algunos con
 * color/internal_name).
 *
 * `priority_matrices` es la excepción estructural (sin id/name propios, ver
 * SyncCatalogosCommand): para esas filas se sintetiza un `sdp_id`
 * determinista (md5 de urgency_id+impact_id) en vez de dejarlo null, porque
 * la unicidad (`catalogo`,`sdp_id`) es más simple de expresar con un valor
 * no-null real que con una excepción a la constraint solo para un catálogo
 * — cualquier motor de BD soportado por este proyecto (MySQL en producción,
 * SQLite en tests) trata NULL de forma no comparable en un índice único de
 * todas formas, así que sintetizar el id evita depender de ese
 * comportamiento y mantiene la constraint simple y uniforme para los 12
 * catálogos por igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_catalog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('catalogo');
            $table->string('sdp_id');
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('color')->nullable();
            $table->boolean('activo')->default(true);
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['catalogo', 'sdp_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_catalog_entries');
    }
};
