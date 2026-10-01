<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tercer origen de línea de `SolicitudProveedorLinea`, mutuamente excluyente
 * con `sic_id`/`folio_sic_manual` (ver ese modelo) — una requisición de EBS
 * que NUNCA tuvo match de SIC local (resuelta solo por el mapeo
 * `EbsArticulo`, ver `Modules\GestionTI\Models\EbsArticulo`) apunta DIRECTO
 * a la `EbsRequisition`, en vez de fabricar una SIC local sintética como
 * hacía el diseño anterior (`EbsRequisitionSyncService::autoCrearSolicitudDesdeArticuloMapeado()`,
 * ya eliminado — ver docs/gestionti-progreso.md, entrada del rediseño).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->foreignId('ebs_requisition_id')->nullable()->after('folio_sic_manual')
                ->constrained('ebs_requisitions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->dropForeign(['ebs_requisition_id']);
            $table->dropColumn('ebs_requisition_id');
        });
    }
};
