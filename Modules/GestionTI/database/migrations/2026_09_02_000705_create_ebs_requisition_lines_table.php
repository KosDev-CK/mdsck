<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de `requisition_header_line` (el "método" de EBS que trae
 * `requisition_lines[]`) — `requisition_header_approved` no las trae, ver
 * EbsRequisitionSyncService::sincronizarAprobadas() (nunca toca esta tabla).
 * `requisition_line_id` es el PK real de línea en Oracle, único aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebs_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebs_requisition_id')->constrained('ebs_requisitions')->cascadeOnDelete();
            $table->unsignedBigInteger('requisition_line_id')->unique();
            $table->unsignedInteger('line_number')->nullable();
            $table->unsignedBigInteger('line_type_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_description')->nullable();
            $table->string('unit_measurement')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebs_requisition_lines');
    }
};
