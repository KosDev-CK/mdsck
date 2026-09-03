<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 4, etapa 2 (PDF de Responsiva — formato real, ver
     * docs/gestionti-progreso.md). El documento real de la empresa incluye
     * una sección de "Configuración de software/red" que el sistema no
     * capturaba hasta ahora. Todos los campos son nullable/opcionales — no
     * todos los tipos de equipo (ej. un Access Point) tienen esta
     * información, y no se quiere bloquear el alta de una asignación por un
     * dato que no aplica.
     *
     * `sistema_operativo_id` reutiliza el catálogo `SistemaOperativo` ya
     * existente (Fase 1) en vez de texto libre — es el único de los 9 campos
     * nuevos que ya tenía un catálogo dedicado en el módulo.
     * `version_office`/`antivirus`/`dominio`/`usuario_dominio`/
     * `id_producto_so` son texto libre porque no existe (ni se pidió) un
     * catálogo para ellos. `libra_cloud`/`oracle_ebs` son boolean nullable a
     * propósito (no default `false`) — `null` significa "no capturado",
     * distinto de "capturado y es No".
     *
     * No se agrega FK nueva para "Responsable de Soporte": la tabla ya tiene
     * `responsable_entrega_id` (hacia `validadores`) desde la migración
     * original de `asset_assignments` — se reutiliza ese mismo campo para
     * ese rol en el PDF, documentado también en `Asignaciones.php`/el PDF.
     * Tampoco se agrega campo para "# de Requerimiento": ya existe
     * `ticket_id`, el PDF resuelve el número mostrado vía
     * `$assignment->ticket?->sdp_display_id`.
     */
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->string('ip')->nullable()->after('observaciones');
            $table->string('mac_wifi')->nullable()->after('ip');
            $table->string('mac_ethernet')->nullable()->after('mac_wifi');
            $table->foreignId('sistema_operativo_id')->nullable()->after('mac_ethernet')
                ->constrained('sistemas_operativos')->nullOnDelete();
            $table->string('version_office')->nullable()->after('sistema_operativo_id');
            $table->string('antivirus')->nullable()->after('version_office');
            $table->string('dominio')->nullable()->after('antivirus');
            $table->string('usuario_dominio')->nullable()->after('dominio');
            $table->string('id_producto_so')->nullable()->after('usuario_dominio');
            $table->boolean('libra_cloud')->nullable()->after('id_producto_so');
            $table->boolean('oracle_ebs')->nullable()->after('libra_cloud');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sistema_operativo_id');
            $table->dropColumn([
                'ip', 'mac_wifi', 'mac_ethernet', 'version_office',
                'antivirus', 'dominio', 'usuario_dominio', 'id_producto_so',
                'libra_cloud', 'oracle_ebs',
            ]);
        });
    }
};
