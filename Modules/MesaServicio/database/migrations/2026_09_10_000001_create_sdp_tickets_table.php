<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('sdp_id')->unique();
            // Folio visible del ticket (ej. "22226") — se necesitará en la
            // Fase 5 para la métrica de folios combinados, solo se guarda
            // aquí, no se calcula nada con él todavía.
            $table->string('display_id')->nullable();
            $table->string('asunto');

            $table->foreignId('sdp_technician_id')->nullable()
                ->constrained('sdp_technicians')->nullOnDelete();
            $table->foreignId('sdp_ticket_status_id')->nullable()
                ->constrained('sdp_ticket_statuses')->nullOnDelete();
            // Texto crudo del estado tal cual lo manda SDP, incluso cuando sí
            // matchea un registro del catálogo — permite reconstruir/depurar
            // sin depender de un join, y es la única fuente de verdad cuando
            // el estado no matchea ningún sdp_ticket_statuses.nombre (estado
            // nuevo no sembrado todavía).
            $table->string('estado_nombre')->nullable();

            $table->string('categoria')->nullable();
            $table->string('subcategoria')->nullable();
            $table->string('solicitante_nombre')->nullable();
            $table->string('solicitante_correo')->nullable();
            $table->string('departamento')->nullable();
            $table->string('sitio')->nullable();
            $table->string('prioridad')->nullable();
            $table->string('urgencia')->nullable();
            $table->string('impacto')->nullable();
            $table->string('tipo_solicitud')->nullable();
            $table->string('modo')->nullable();
            $table->string('grupo')->nullable();

            $table->dateTime('created_time');
            $table->dateTime('responded_time')->nullable();
            $table->dateTime('resolved_time')->nullable();
            $table->dateTime('completed_time')->nullable();
            $table->dateTime('due_time')->nullable();

            $table->boolean('primera_respuesta_vencida')->default(false);
            $table->boolean('vencido')->default(false);

            $table->longText('resolucion')->nullable();
            // Ticket completo tal cual lo manda SDP, para no perder campos
            // que no se modelaron individualmente arriba.
            $table->json('raw_payload')->nullable();
            $table->dateTime('last_synced_at')->nullable();

            $table->timestamps();

            $table->index('created_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_tickets');
    }
};
