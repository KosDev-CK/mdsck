<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca de agua de sincronización, una fila por proceso incremental
     * ("tickets" por ahora, ver SdpSyncState::KEY_TICKETS). Se modela como
     * clave/valor en vez de una tabla de una sola fila fija — no cuesta más
     * y deja abierta la puerta a futuras marcas de agua independientes
     * (ej. si algún día se vuelve incremental sdp:sync-technicians) sin
     * otra migración.
     */
    public function up(): void
    {
        Schema::create('sdp_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sdp_sync_states');
    }
};
