<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->foreignId('tipo_equipo_id')->constrained('tipos_equipo')->restrictOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('modelo_id')->nullable()->constrained('modelos')->nullOnDelete();
            $table->string('numero_serie')->nullable();
            $table->string('service_tag')->nullable();
            $table->json('especificaciones')->nullable();
            $table->decimal('costo_adquisicion', 10, 2)->nullable();
            $table->string('origen_tipo');

            // La FK real (`nullOnDelete` hacia `recepcion_lineas`) se agrega
            // en 2026_08_31_000012_add_recepcion_and_sic_reservada_fk_to_assets_table
            // — la tabla `recepcion_lineas` no existía todavía cuando se
            // escribió esta migración.
            $table->unsignedBigInteger('recepcion_linea_id')->nullable();

            $table->text('motivo_alta_manual')->nullable();
            $table->foreignId('dado_de_alta_por_id')->nullable()->constrained('validadores')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->date('fecha_alta_stock')->nullable();
            $table->date('fecha_inicio_garantia')->nullable();
            $table->date('fecha_fin_garantia')->nullable();
            $table->foreignId('ubicacion_actual_id')->nullable()->constrained('ubicaciones')->nullOnDelete();

            // La FK real (`nullOnDelete` hacia `solicitudes_sic_borrador`) se
            // agrega en 2026_08_31_000012_add_recepcion_and_sic_reservada_fk_to_assets_table
            // — `SolicitudSicBorrador` no existía todavía cuando se escribió
            // esta migración.
            $table->unsignedBigInteger('sic_reservada_id')->nullable();

            // La FK real (`nullOnDelete` hacia `proyecto_presupuestos`) se
            // agrega en 2026_09_01_000004_add_proyecto_presupuesto_fks_table
            // — la tabla `proyecto_presupuestos` no existía todavía cuando se
            // escribió esta migración.
            $table->unsignedBigInteger('proyecto_presupuesto_id')->nullable();

            $table->foreignId('estatus_id')->constrained('estatus_activo')->restrictOnDelete();
            $table->foreignId('propiedad_id')->nullable()->constrained('propiedades')->nullOnDelete();

            // La FK real (`nullOnDelete` hacia `invoices`) se agrega en
            // 2026_09_01_000007_add_invoice_fk_to_assets_table — la tabla
            // `invoices` no existía todavía cuando se escribió esta migración.
            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->text('nota_adquisicion_original')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
