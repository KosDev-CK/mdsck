<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 4, etapa 2 (PDF de Responsiva — formato real). El documento real
     * pide el RFC del empleado en el bloque de acuse. Vive en `Empleado`
     * (dato maestro de la persona), no en `asset_assignments` — un mismo
     * empleado puede recibir varios equipos a lo largo del tiempo sin volver
     * a capturar su RFC cada vez.
     */
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->string('rfc')->nullable()->after('correo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn('rfc');
        });
    }
};
