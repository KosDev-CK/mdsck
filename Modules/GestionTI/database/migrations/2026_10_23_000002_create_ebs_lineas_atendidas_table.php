<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas de una requisición de EBS que ya se atendieron FUERA del flujo
 * (compra o entrega hecha antes de usar el sistema, o que no requieren
 * ingreso): dejan de ofrecerse en Solicitud a Proveedores. La clave es el
 * `requisition_line_id` REAL de EBS (no el id local de `ebs_requisition_lines`,
 * que cambia cuando la sincronización recrea las líneas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebs_lineas_atendidas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ebs_requisition_line_id')->unique();
            $table->foreignId('ebs_requisition_id')->constrained('ebs_requisitions')->cascadeOnDelete();
            $table->text('motivo');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebs_lineas_atendidas');
    }
};
