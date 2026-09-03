<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5, punto 1 — integración real con la API de Oracle EBS para
 * sincronizar Solicitudes Internas de Compra (SIC). Réplica local de las
 * requisiciones de EBS, independiente del esquema de negocio de
 * `solicitudes_sic_borrador` (que sigue siendo la fuente de verdad del
 * flujo interno) — ver docs/gestionti-progreso.md.
 *
 * `requisition_header_id` es el PK real de Oracle, único aquí. `code` es el
 * folio visible (ej. "6489") — texto libre sin unicidad forzada (dos
 * requisiciones de Oracle nunca deberían compartir folio, pero no se conoce
 * garantía documentada de eso, así que no se le puso `unique()`). `status`
 * es texto libre, no enum — pueden aparecer valores nuevos no vistos hasta
 * ahora (ver EbsRequisitionSyncService::mapearEstatusLocal()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebs_requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requisition_header_id')->unique();
            $table->string('code')->nullable()->index();
            $table->string('description')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('fecha_creacion')->nullable();
            $table->string('wf_item_key')->nullable();
            $table->string('wf_item_type')->nullable();
            $table->string('organization_code')->nullable();
            $table->string('organization_description')->nullable();
            $table->string('created_by_user')->nullable();
            // Del campo con typo real del proveedor "decription" (ver
            // EbsRequisitionsClient) — nuestra columna se llama correctamente.
            $table->string('created_by_description')->nullable();
            $table->integer('sequence_num')->nullable();
            $table->string('approver_user')->nullable();
            $table->string('approver_name')->nullable();
            $table->dateTime('approver_date')->nullable();
            $table->string('action_code')->nullable();
            $table->dateTime('action_date')->nullable();
            // Cuándo la tocó por última vez cada uno de los 2 "métodos" de
            // la API (requisition_header_line / requisition_header_approved)
            // — independientes porque cada uno sincroniza campos distintos.
            $table->dateTime('ultima_sincronizacion_creadas_at')->nullable();
            $table->dateTime('ultima_sincronizacion_aprobadas_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebs_requisitions');
    }
};
