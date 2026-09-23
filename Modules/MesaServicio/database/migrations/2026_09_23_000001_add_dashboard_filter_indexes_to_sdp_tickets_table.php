<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encontrado en producción (2026-09-23): el Dashboard Ejecutivo tarda hasta
 * 30-40s en cargar/filtrar con el histórico completo de tickets. `categoria`,
 * `departamento` y `tipo_solicitud` no tenían índice — el "drilldown" (clic
 * en una categoría/departamento/tipo, ver `Ejecutivo::seleccionarCategoria()`
 * y hermanos) agrega un `WHERE` de igualdad sobre esas columnas encima del
 * rango de `created_time` ya filtrado, y sin índice MySQL no tiene forma de
 * acotar esa combinación sin escanear el rango completo fila por fila.
 * Compuesto con `created_time` (no una columna sola) porque esa es la
 * combinación real que arma `Ejecutivo::ticketsEnRango()` — rango de fecha +
 * a lo más un filtro de igualdad adicional, nunca la columna categórica sin
 * el rango de fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->index(['categoria', 'created_time']);
            $table->index(['departamento', 'created_time']);
            $table->index(['tipo_solicitud', 'created_time']);
        });
    }

    public function down(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->dropIndex(['categoria', 'created_time']);
            $table->dropIndex(['departamento', 'created_time']);
            $table->dropIndex(['tipo_solicitud', 'created_time']);
        });
    }
};
