<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sdp_ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('sdp_id')->unique();
            $table->string('nombre');
            $table->string('internal_name')->nullable();
            // 'en_curso' | 'completado' — derivado de "in_progress" de SDP.
            $table->string('tipo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_ticket_statuses');
    }
};
