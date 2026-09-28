<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mueve `sic_id` de la cabecera (`solicitudes_proveedor`) a la línea
 * (`solicitud_proveedor_lineas`) — "de 1 a N SICs por solicitud" surge de
 * "N líneas, cada una con su propia SIC opcional", sin necesidad de una
 * tabla pivote nueva (mismo patrón que `articulo_id`, ya en esa misma
 * tabla). Ver docs/gestionti-progreso.md, entrada del rediseño de
 * "Solicitud a Proveedores: selección de 1 a N SICs autorizadas".
 *
 * Sin backfill de datos: `solicitudes_proveedor.sic_id` tenía 0 filas
 * pobladas de 1 registro total al momento de escribir esta migración
 * (verificado en la BD de dev real antes de escribirla) — el campo nunca se
 * usó con datos reales, se puede reemplazar por completo con seguridad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->dropForeign(['sic_id']);
            $table->dropColumn('sic_id');
        });

        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->foreignId('sic_id')->nullable()->after('articulo_id')
                ->constrained('solicitudes_sic_borrador')->nullOnDelete();
            // Folio de SIC capturado a mano (texto libre) cuando no existe
            // todavía el registro real de `SolicitudSicBorrador` — mismo
            // caso ya cubierto conceptualmente por
            // `observaciones_especificaciones`, pero ese campo es para notas
            // de specs, no para el folio en sí.
            $table->string('folio_sic_manual')->nullable()->after('sic_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->dropForeign(['sic_id']);
            $table->dropColumn(['sic_id', 'folio_sic_manual']);
        });

        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->foreignId('sic_id')->nullable()->constrained('solicitudes_sic_borrador')->nullOnDelete();
        });
    }
};
