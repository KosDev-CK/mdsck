<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `item_id` gana un índice — el sync lo consulta por cada línea nueva vista
 * para resolver/crear su `EbsArticulo` (ver `EbsRequisitionSyncService`), y
 * "SIC en EBS" también lo necesita al intentar la auto-creación de SIC
 * esqueleto desde la primera línea de la requisición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebs_requisition_lines', function (Blueprint $table) {
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('ebs_requisition_lines', function (Blueprint $table) {
            $table->dropIndex(['item_id']);
        });
    }
};
