<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de entrega prometida por el proveedor: por defecto 3 días después de
 * la solicitud (editable a mano). Es la referencia para medir si el proveedor
 * entrega a tiempo (reporte de entregas). Las solicitudes ya pendientes de
 * recibir se rellenan con el mismo criterio; las ya recibidas/canceladas se
 * quedan sin fecha (al proveedor nunca se le comunicó una).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->date('fecha_entrega_prometida')->nullable()->after('fecha_solicitud');
        });

        DB::table('solicitudes_proveedor')
            ->whereIn('estatus', ['solicitada', 'parcialmente_recibida'])
            ->get(['id', 'fecha_solicitud'])
            ->each(function ($solicitud) {
                DB::table('solicitudes_proveedor')->where('id', $solicitud->id)->update([
                    'fecha_entrega_prometida' => \Illuminate\Support\Carbon::parse($solicitud->fecha_solicitud)->addDays(3)->toDateString(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->dropColumn('fecha_entrega_prometida');
        });
    }
};
