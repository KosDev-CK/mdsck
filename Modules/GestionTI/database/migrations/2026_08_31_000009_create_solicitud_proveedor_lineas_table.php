<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_proveedor_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_proveedor')->cascadeOnDelete();
            $table->foreignId('articulo_id')->nullable()->constrained('articulos_solicitud')->nullOnDelete();
            // Usado en vez de articulo_id para una compra especial / artículo
            // fuera de catálogo. Exactamente uno de los dos se captura, no
            // ambos ni ninguno — validado en la capa de aplicación.
            $table->string('descripcion_libre')->nullable();
            $table->unsignedInteger('cantidad_solicitada');
            // Escrita por la futura etapa de Recepción, no editable desde
            // este formulario — siempre 0 al crear.
            $table->unsignedInteger('cantidad_recibida')->default(0);
            $table->decimal('precio_unitario_cotizado', 10, 2)->nullable();
            $table->boolean('es_activo_inventariable')->default(false);
            // Flexible para casos especiales futuros — columna reservada,
            // sin UI de formulario todavía.
            $table->json('detalle_adicional')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_proveedor_lineas');
    }
};
