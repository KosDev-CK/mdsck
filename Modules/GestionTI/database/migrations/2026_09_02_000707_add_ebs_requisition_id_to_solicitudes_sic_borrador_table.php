<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo opcional (y respaldo manual siempre disponible) entre una
 * `SolicitudSicBorrador` y la `EbsRequisition` real que le corresponde en
 * Oracle EBS. `unique()` porque una `EbsRequisition` solo puede estar
 * vinculada a una `SolicitudSicBorrador` — ver
 * EbsRequisitionSyncService::sincronizarCreadas()/sincronizarAprobadas() y
 * docs/gestionti-progreso.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_sic_borrador', function (Blueprint $table) {
            $table->foreignId('ebs_requisition_id')
                ->nullable()
                ->after('folio_sic')
                ->unique()
                ->constrained('ebs_requisitions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_sic_borrador', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ebs_requisition_id');
        });
    }
};
