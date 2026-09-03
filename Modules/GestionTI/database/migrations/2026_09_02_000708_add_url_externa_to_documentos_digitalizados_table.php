<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (SharePoint) — cuando `proveedor_almacenamiento = 'sharepoint'`,
 * `referencia` pasa a guardar el `driveItemId` de Microsoft Graph (ya no un
 * path del disco `public`). `url_externa` guarda el `webUrl` devuelto por
 * Graph al momento de subir/vincular — se persiste una sola vez para que
 * `DocumentoDigitalizado::url()` no dependa de volver a llamar a Graph (más
 * rápido y sigue funcionando aunque Graph esté caído). Nula para
 * `proveedor_almacenamiento = 'local'`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_digitalizados', function (Blueprint $table) {
            $table->string('url_externa')->nullable()->after('referencia');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_digitalizados', function (Blueprint $table) {
            $table->dropColumn('url_externa');
        });
    }
};
