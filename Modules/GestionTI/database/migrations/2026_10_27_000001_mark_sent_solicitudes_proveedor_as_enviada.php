<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Las solicitudes que ya se enviaron al proveedor pasan del estatus "solicitada" a "enviada". */
    public function up(): void
    {
        DB::table('solicitudes_proveedor')
            ->where('estatus', 'solicitada')
            ->whereNotNull('enviada_at')
            ->update(['estatus' => 'enviada']);
    }

    public function down(): void
    {
        DB::table('solicitudes_proveedor')
            ->where('estatus', 'enviada')
            ->update(['estatus' => 'solicitada']);
    }
};
