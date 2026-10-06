<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El "Lugar de entrega" (Zurich, CEDA, Sotelo) AGRUPA ubicaciones de
 * inventario, no al revés: la relación vive en `ubicaciones.lugar_entrega_id`
 * (se asigna desde Catálogos Núcleo → Ubicaciones). Reemplaza el
 * `lugares_entrega.ubicacion_id` de la migración anterior (una sola ubicación
 * por lugar); si alguien ya lo había llenado, se conserva pasándolo a la
 * ubicación correspondiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ubicaciones', function (Blueprint $table) {
            $table->foreignId('lugar_entrega_id')->nullable()->after('nombre_conocido')->constrained('lugares_entrega')->nullOnDelete();
        });

        DB::table('lugares_entrega')->whereNotNull('ubicacion_id')->get(['id', 'ubicacion_id'])->each(function ($lugar) {
            DB::table('ubicaciones')->where('id', $lugar->ubicacion_id)->update(['lugar_entrega_id' => $lugar->id]);
        });

        Schema::table('lugares_entrega', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ubicacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('lugares_entrega', function (Blueprint $table) {
            $table->foreignId('ubicacion_id')->nullable()->after('nombre')->constrained('ubicaciones')->nullOnDelete();
        });

        Schema::table('ubicaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lugar_entrega_id');
        });
    }
};
