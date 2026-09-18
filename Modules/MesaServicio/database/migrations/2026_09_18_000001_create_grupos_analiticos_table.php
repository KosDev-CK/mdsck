<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo local, 100% manual (sin sync con SDP), de "grupos analíticos" —
 * una clasificación propia para agrupar técnicos con fines de reporte,
 * independiente de los grupos de SDP (que son muchos-a-muchos por técnico
 * y no tienen un único grupo "canónico" utilizable, ver
 * docs/mesaservicio-progreso.md). Deliberadamente genérico (nombre,
 * descripción, activo) y sin ninguna columna que lo amarre a "técnico" —
 * el usuario ya anticipó que este mismo catálogo se reutilizará después
 * para otras entidades (empresas, geografía, etc.), pero esa reutilización
 * es trabajo futuro: hoy solo se referencia desde sdp_technicians (ver
 * migración add_grupo_analitico_id_to_sdp_technicians_table). No se
 * construye ninguna abstracción polimórfica de asignación todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_analiticos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_analiticos');
    }
};
