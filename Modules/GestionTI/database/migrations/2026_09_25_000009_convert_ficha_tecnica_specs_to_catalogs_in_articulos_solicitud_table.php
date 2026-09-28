<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convierte `procesador`/`ram`/`almacenamiento` de texto libre en
     * `articulos_solicitud` a catálogos reales (`procesadores`/`rams`/
     * `almacenamientos`, ya creados por las 3 migraciones anteriores) —
     * "Categoría" NO se toca (decisión explícita del usuario, se queda como
     * lista fija en código). Ver docs/gestionti-progreso.md, entrada
     * "Catálogos de Procesador/RAM/Almacenamiento".
     *
     * La BD de dev real ya tiene ~511 filas reales con texto libre en estas 3
     * columnas (generadas por `gestionti:generar-articulos-desde-historico`)
     * — este backfill preserva esos datos migrándolos a las FK nuevas, usando
     * `DB::table(...)` directo (no Eloquent) por ser más seguro dentro de una
     * migración.
     */
    public function up(): void
    {
        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->foreignId('procesador_id')->nullable()->after('modelo_id')->constrained('procesadores')->nullOnDelete();
            $table->foreignId('ram_id')->nullable()->after('procesador_id')->constrained('rams')->nullOnDelete();
            $table->foreignId('almacenamiento_id')->nullable()->after('ram_id')->constrained('almacenamientos')->nullOnDelete();
        });

        $this->backfill('procesador', 'procesadores', 'procesador_id');
        $this->backfill('ram', 'rams', 'ram_id');
        $this->backfill('almacenamiento', 'almacenamientos', 'almacenamiento_id');

        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->dropColumn(['procesador', 'ram', 'almacenamiento']);
        });
    }

    /**
     * Por cada valor DISTINTO no nulo/no vacío en `articulos_solicitud.
     * {$textColumn}`, crea (si no existe) una fila en `{$catalogTable}` con
     * ese `nombre`, y actualiza `{$fkColumn}` en todas las filas que
     * compartían ese mismo texto.
     */
    private function backfill(string $textColumn, string $catalogTable, string $fkColumn): void
    {
        $valores = DB::table('articulos_solicitud')
            ->whereNotNull($textColumn)
            ->where($textColumn, '!=', '')
            ->distinct()
            ->pluck($textColumn);

        foreach ($valores as $valor) {
            $catalogId = DB::table($catalogTable)->where('nombre', $valor)->value('id');

            if ($catalogId === null) {
                $catalogId = DB::table($catalogTable)->insertGetId([
                    'nombre' => $valor,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('articulos_solicitud')->where($textColumn, $valor)->update([$fkColumn => $catalogId]);
        }
    }

    /**
     * Nota: no reconstruye el texto original en las columnas restauradas
     * (solo las deja vacías/nulas) — aceptable, ver instrucción del
     * encargo: el `down()` no necesita reconstruir los datos de texto
     * perfectamente.
     */
    public function down(): void
    {
        Schema::table('articulos_solicitud', function (Blueprint $table) {
            $table->dropForeign(['procesador_id']);
            $table->dropForeign(['ram_id']);
            $table->dropForeign(['almacenamiento_id']);
            $table->dropColumn(['procesador_id', 'ram_id', 'almacenamiento_id']);

            $table->string('procesador')->nullable()->after('modelo_id');
            $table->string('ram')->nullable()->after('procesador');
            $table->string('almacenamiento')->nullable()->after('ram');
        });
    }
};
