<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La asignación de un activo siempre lleva el FOLIO de la SIC que la cubre
 * (`sic_folio`), aunque esa SIC todavía no esté autorizada: la entrega no
 * debe esperar a la autorización. Además de la SIC local (`sic_id`, que ya
 * existía) la asignación puede ligarse a una requisición de EBS
 * (`ebs_requisition_id`) y, dentro de ella, a una línea concreta
 * (`ebs_requisition_line_id` = `requisition_line_id` real de EBS, el
 * identificador único de cada artículo de la SIC). Las asignaciones hechas
 * antes de que la SIC estuviera autorizada se marcan como excepción, con su
 * motivo y quién las registró / ligó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->string('sic_folio', 100)->nullable()->after('sic_id')->index();
            $table->foreignId('ebs_requisition_id')->nullable()->after('sic_folio')->constrained('ebs_requisitions')->nullOnDelete();
            $table->unsignedBigInteger('ebs_requisition_line_id')->nullable()->after('ebs_requisition_id')->index();
            $table->boolean('excepcion_sin_sic')->default(false)->after('ebs_requisition_line_id');
            $table->text('motivo_excepcion')->nullable()->after('excepcion_sin_sic');
            $table->foreignId('registrado_por_user_id')->nullable()->after('motivo_excepcion')->constrained('users')->nullOnDelete();
            $table->timestamp('sic_ligada_at')->nullable()->after('registrado_por_user_id');
            $table->foreignId('sic_ligada_por_user_id')->nullable()->after('sic_ligada_at')->constrained('users')->nullOnDelete();
        });

        // Las asignaciones que ya tenían SIC local conservan su folio.
        DB::table('asset_assignments')
            ->whereNotNull('sic_id')
            ->orderBy('id')
            ->each(function ($asignacion) {
                $folio = DB::table('solicitudes_sic_borrador')->where('id', $asignacion->sic_id)->value('folio_sic');

                if ($folio !== null && $folio !== '') {
                    DB::table('asset_assignments')->where('id', $asignacion->id)->update(['sic_folio' => $folio]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropForeign(['ebs_requisition_id']);
            $table->dropForeign(['registrado_por_user_id']);
            $table->dropForeign(['sic_ligada_por_user_id']);
            $table->dropColumn([
                'sic_folio', 'ebs_requisition_id', 'ebs_requisition_line_id', 'excepcion_sin_sic',
                'motivo_excepcion', 'registrado_por_user_id', 'sic_ligada_at', 'sic_ligada_por_user_id',
            ]);
        });
    }
};
