<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Origen EBS del activo: la requisición (y la línea concreta, con su
 * `requisition_line_id` real) de la que salió lo recibido. Con esto lo recibido
 * de una línea de EBS queda reservado para su requisición, igual que lo
 * recibido de una SIC local (`sic_reservada_id`), y conserva la trazabilidad
 * SIC → solicitud → recepción → activo → asignación aunque no exista SIC local.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('ebs_requisition_id')->nullable()->after('sic_reservada_id')->constrained('ebs_requisitions')->nullOnDelete();
            $table->unsignedBigInteger('ebs_requisition_line_id')->nullable()->after('ebs_requisition_id')->index();
        });

        // Activos ya recibidos de líneas de EBS: se les conserva el origen (el
        // estatus no se toca, siguen disponibles como estaban).
        DB::table('assets')
            ->whereNotNull('recepcion_linea_id')
            ->orderBy('id')
            ->each(function ($activo) {
                $linea = DB::table('recepcion_lineas')
                    ->join('solicitud_proveedor_lineas', 'solicitud_proveedor_lineas.id', '=', 'recepcion_lineas.solicitud_proveedor_linea_id')
                    ->where('recepcion_lineas.id', $activo->recepcion_linea_id)
                    ->whereNotNull('solicitud_proveedor_lineas.ebs_requisition_id')
                    ->select('solicitud_proveedor_lineas.ebs_requisition_id', 'solicitud_proveedor_lineas.ebs_requisition_line_id')
                    ->first();

                if ($linea) {
                    DB::table('assets')->where('id', $activo->id)->update([
                        'ebs_requisition_id' => $linea->ebs_requisition_id,
                        'ebs_requisition_line_id' => $linea->ebs_requisition_line_id,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['ebs_requisition_id']);
            $table->dropColumn(['ebs_requisition_id', 'ebs_requisition_line_id']);
        });
    }
};
