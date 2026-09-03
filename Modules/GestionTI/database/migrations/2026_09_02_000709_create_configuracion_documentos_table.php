<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton (siempre `id = 1`, mismo patrón que `App\Models\SiteSetting`)
 * que decide, por tipo de documento, si `DocumentoDigitalizado::storeUploaded()`
 * sube a SharePoint o se queda en el disco `public` (comportamiento
 * histórico). Vive en este módulo (no en el core) porque los 5 valores de
 * `tipo_documento` ('sic', 'responsiva', 'remision_proveedor', 'factura',
 * 'orden_servicio') son un concepto propio de GestionTI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_documentos', function (Blueprint $table) {
            $table->id();
            // Subconjunto de los 5 valores de tipo_documento — texto libre en
            // JSON, no una tabla normalizada aparte: el volumen es de a lo
            // más 5 elementos y siempre se lee/escribe completo, nunca se
            // consulta por elemento individual.
            $table->json('tipos_sharepoint');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_documentos');
    }
};
