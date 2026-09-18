<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 (Parte 3) — campos personalizados (UDF) de SDP mapeados con el
 * administrador real de la instancia ("Configuración → Personalización →
 * Campos adicionales → Solicitud"). Los char* llegan como string plano
 * dentro de `ticket.udf_fields`, los date* con el mismo shape
 * {value, display_value} que created_time/etc. — ver
 * SyncTicketsCommand::upsertTicket().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->string('area_operativa')->nullable()->after('grupo');
            $table->string('grupo_resolutor')->nullable()->after('area_operativa');
            $table->string('super_categoria')->nullable()->after('grupo_resolutor');
            $table->string('n3_area_escalamiento')->nullable()->after('super_categoria');
            $table->dateTime('n3_fecha_escalamiento')->nullable()->after('n3_area_escalamiento');
            $table->dateTime('n3_fecha_solucion')->nullable()->after('n3_fecha_escalamiento');
            $table->string('n3_no_seguimiento_proveedor')->nullable()->after('n3_fecha_solucion');
            $table->string('n3_recurso_escalamiento')->nullable()->after('n3_no_seguimiento_proveedor');
            $table->string('n4_area_escalamiento')->nullable()->after('n3_recurso_escalamiento');
            $table->dateTime('n4_fecha_escalamiento')->nullable()->after('n4_area_escalamiento');
            $table->dateTime('n4_fecha_solucion')->nullable()->after('n4_fecha_escalamiento');
            $table->string('n4_no_seguimiento_proveedor')->nullable()->after('n4_fecha_solucion');
            $table->string('n4_recurso_escalamiento')->nullable()->after('n4_no_seguimiento_proveedor');
        });
    }

    public function down(): void
    {
        Schema::table('sdp_tickets', function (Blueprint $table) {
            $table->dropColumn([
                'area_operativa',
                'grupo_resolutor',
                'super_categoria',
                'n3_area_escalamiento',
                'n3_fecha_escalamiento',
                'n3_fecha_solucion',
                'n3_no_seguimiento_proveedor',
                'n3_recurso_escalamiento',
                'n4_area_escalamiento',
                'n4_fecha_escalamiento',
                'n4_fecha_solucion',
                'n4_no_seguimiento_proveedor',
                'n4_recurso_escalamiento',
            ]);
        });
    }
};
