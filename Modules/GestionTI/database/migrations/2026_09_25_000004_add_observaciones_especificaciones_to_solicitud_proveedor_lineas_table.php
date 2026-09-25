<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nota libre por línea para specs que aún no se conocen al armar la
     * Solicitud a Proveedor (ej. "no se sabe RAM exacta, confirmar al
     * recibir") — no reescribe el catálogo de Artículo automáticamente, ver
     * docs/gestionti-progreso.md, entrada "Catálogo unificado de Artículos".
     */
    public function up(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->text('observaciones_especificaciones')->nullable()->after('detalle_adicional');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->dropColumn('observaciones_especificaciones');
        });
    }
};
