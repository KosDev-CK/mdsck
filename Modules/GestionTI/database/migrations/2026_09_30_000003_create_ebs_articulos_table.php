<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapeo EBS -> Artículo estándar (Fase 5, mapeo automático de ítems de EBS a
 * un artículo genérico ya existente en el catálogo de Artículos, ver
 * docs/gestionti-progreso.md). `ebs_item_id` viene de
 * `EbsRequisitionLine.item_id` — único, lo crea automáticamente el sync
 * (`EbsRequisitionSyncService`) la primera vez que ve un `item_id` nuevo, sin
 * mapear. Un humano completa `articulo_id` después, desde el tab nuevo
 * "Artículos EBS" de `Catalogos\Compras`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebs_articulos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ebs_item_id')->unique();
            $table->string('ebs_item_description')->nullable();
            $table->foreignId('articulo_id')->nullable()->constrained('articulos_solicitud')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebs_articulos');
    }
};
