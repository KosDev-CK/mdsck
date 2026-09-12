<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton (un solo registro, mismo patrón que App\Models\SiteSetting)
        // que decide cuál Form de Modules\FormBuilder es la encuesta de
        // satisfacción disparada automáticamente por sdp:sync-tickets. Mientras
        // form_id sea null, el disparo automático queda completamente
        // desactivado (comportamiento seguro por defecto) — ver
        // Modules\MesaServicio\Models\SdpSurveySetting::current().
        Schema::create('sdp_survey_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_survey_settings');
    }
};
