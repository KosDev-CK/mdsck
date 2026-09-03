<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pares clave/valor de `requisition_header_approved` (el "método" de EBS que
 * trae `notes[]` en vez de `requisition_lines[]`). Las claves NO están
 * estandarizadas por Oracle (varían mayúsculas/redacción entre una SIC y
 * otra) — se guardan tal cual vienen, sin intentar mapearlas a columnas
 * fijas. Ver EbsRequisitionSyncService::sincronizarAprobadas().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebs_requisition_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ebs_requisition_id')->constrained('ebs_requisitions')->cascadeOnDelete();
            $table->string('clave');
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebs_requisition_notes');
    }
};
