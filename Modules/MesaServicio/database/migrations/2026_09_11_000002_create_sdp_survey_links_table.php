<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla puente para la trazabilidad técnico -> ticket -> respuesta de
        // encuesta (Fase 6). Reusa Modules\FormBuilder\Models\TicketFormLink
        // tal cual existe hoy (no se toca ese módulo) — este es el único
        // lugar donde MesaServicio conoce la relación con ese enlace.
        // sdp_technician_id se guarda desnormalizado (copiado del ticket al
        // momento de generar el enlace) para no depender de un join hacia
        // sdp_tickets en la ficha de técnico.
        Schema::create('sdp_survey_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_form_link_id')->unique()
                ->constrained('ticket_form_links')->cascadeOnDelete();
            // Único por ticket: es la red de seguridad a nivel de BD de la
            // regla "no generar una segunda encuesta para el mismo ticket"
            // que ya aplica sdp:sync-tickets a nivel de aplicación.
            $table->foreignId('sdp_ticket_id')->unique()
                ->constrained('sdp_tickets')->cascadeOnDelete();
            $table->foreignId('sdp_technician_id')->nullable()
                ->constrained('sdp_technicians')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_survey_links');
    }
};
