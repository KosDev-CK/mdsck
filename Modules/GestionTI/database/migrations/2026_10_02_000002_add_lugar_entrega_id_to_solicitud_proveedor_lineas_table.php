<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->foreignId('lugar_entrega_id')->nullable()->after('ebs_requisition_id')
                ->constrained('lugares_entrega')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->dropForeign(['lugar_entrega_id']);
            $table->dropColumn('lugar_entrega_id');
        });
    }
};
