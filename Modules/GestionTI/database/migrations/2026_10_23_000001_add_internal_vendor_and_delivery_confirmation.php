<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedor INTERNO (un área de la empresa que surte equipo — telefonía,
 * infraestructura...) con sus encargados, y la confirmación de entrega que
 * esos encargados hacen en el sistema antes de que TI haga la Recepción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->boolean('es_interno')->default(false)->after('activo');
        });

        Schema::create('proveedor_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['proveedor_id', 'user_id']);
        });

        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->timestamp('entrega_confirmada_at')->nullable();
            $table->foreignId('entrega_confirmada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('entrega_confirmada_notas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_proveedor', function (Blueprint $table) {
            $table->dropForeign(['entrega_confirmada_por_user_id']);
            $table->dropColumn(['entrega_confirmada_at', 'entrega_confirmada_por_user_id', 'entrega_confirmada_notas']);
        });

        Schema::dropIfExists('proveedor_usuario');

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('es_interno');
        });
    }
};
