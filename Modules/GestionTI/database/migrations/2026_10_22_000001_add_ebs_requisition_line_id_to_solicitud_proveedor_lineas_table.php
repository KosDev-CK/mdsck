<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una requisición de EBS puede traer varias líneas (artículos) y cada una se
 * relaciona por separado en una Solicitud a Proveedor. Esta columna guarda
 * el `requisition_line_id` REAL de EBS (no el id local de `ebs_requisition_lines`,
 * que cambia cuando la sincronización recrea las líneas) de la línea que
 * recogió cada renglón del pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->unsignedBigInteger('ebs_requisition_line_id')->nullable()->after('ebs_requisition_id')->index();
        });

        // Los renglones ya guardados recogían la requisición completa a
        // través de su PRIMERA línea (por `line_number`): se les asigna esa.
        $primerasLineas = DB::table('ebs_requisition_lines')
            ->orderBy('line_number')
            ->get(['ebs_requisition_id', 'requisition_line_id'])
            ->unique('ebs_requisition_id')
            ->pluck('requisition_line_id', 'ebs_requisition_id');

        DB::table('solicitud_proveedor_lineas')
            ->whereNotNull('ebs_requisition_id')
            ->get(['id', 'ebs_requisition_id'])
            ->each(function ($linea) use ($primerasLineas) {
                $lineaEbs = $primerasLineas[$linea->ebs_requisition_id] ?? null;

                if ($lineaEbs !== null) {
                    DB::table('solicitud_proveedor_lineas')
                        ->where('id', $linea->id)
                        ->update(['ebs_requisition_line_id' => $lineaEbs]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->dropIndex(['ebs_requisition_line_id']);
            $table->dropColumn('ebs_requisition_line_id');
        });
    }
};
