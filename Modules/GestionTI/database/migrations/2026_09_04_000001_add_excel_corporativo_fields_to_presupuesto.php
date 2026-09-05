<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alinea la captura de Presupuesto por Proyecto con el Excel corporativo
     * real que se usa para conseguir la firma (compartido por el usuario
     * 2026-09-04, ver docs/gestionti-progreso.md) — hasta ahora "Exportar a
     * Excel" era un placeholder genérico de 6 columnas.
     *
     * `factor_administrativo` va en el encabezado del proyecto porque varía
     * por proyecto (confirmado con el usuario, no es una constante de
     * negocio) — se aplica en el bloque final de totales del export.
     */
    public function up(): void
    {
        Schema::table('proyecto_presupuestos', function (Blueprint $table) {
            $table->decimal('factor_administrativo', 6, 4)->default(1.0350)->after('fecha_limite_captura');
        });

        Schema::table('proyecto_presupuesto_articulos', function (Blueprint $table) {
            // Agrupación de 5 valores fijos que exige el Excel real
            // (Aplicativos/Infraestructura/Telco/Ciberseguridad/Gastos de
            // Implementación) — deliberadamente SEPARADA de `categoria` (11
            // valores finos que sí disparan lógica real, ver
            // ProyectoPresupuestoArticulo::solicitudProveedor()). No se
            // deriva de `categoria` por una función de mapeo: en el Excel
            // real, artículos que uno esperaría en "Infraestructura" (rack,
            // cámaras, cerradura eléctrica) quedan bajo "Telco" por decisión
            // manual de quien armó ese presupuesto, no por una regla de
            // nombre — así que se captura a mano, igual que `categoria`.
            $table->string('categoria_contable')->nullable()->after('categoria');

            // Datos de proveedor/forma de pago — capturados junto con el
            // costo (fase `en_captura_costos`), no junto con la composición
            // (fase `armado`), porque normalmente se conocen al mismo tiempo
            // que la cotización real.
            $table->string('proveedor')->nullable()->after('costo_unitario');
            $table->string('razon_social_facturada')->nullable()->after('proveedor');
            $table->string('tipo_servicio')->nullable()->after('razon_social_facturada');
            // 'one_time' | 'on_going' — ver ProyectoPresupuestoArticulo::CASHFLOW_TIPOS.
            $table->string('cashflow_tipo')->nullable()->after('tipo_servicio');
            $table->unsignedInteger('no_meses')->nullable()->default(1)->after('cashflow_tipo');
            $table->decimal('costo_unitario_usd', 10, 2)->nullable()->after('no_meses');
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_presupuesto_articulos', function (Blueprint $table) {
            $table->dropColumn([
                'categoria_contable', 'proveedor', 'razon_social_facturada',
                'tipo_servicio', 'cashflow_tipo', 'no_meses', 'costo_unitario_usd',
            ]);
        });

        Schema::table('proyecto_presupuestos', function (Blueprint $table) {
            $table->dropColumn('factor_administrativo');
        });
    }
};
