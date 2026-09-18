<?php

namespace Modules\MesaServicio\Database\Seeders;

use App\Models\Screen;
use Illuminate\Database\Seeder;
use Modules\MesaServicio\Models\SdpSlaDefinition;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Spatie\Permission\Models\Role;

class MesaServicioDatabaseSeeder extends Seeder
{
    /**
     * Nombre del rol de Spatie que representa al segundo tipo de
     * destinatario de reporte (junto con la tabla de correos sueltos) — se
     * asigna a usuarios reales desde /user-roles del core, no hay UI propia
     * de este módulo para asignarlo.
     */
    public const ROL_SUPERVISOR = 'Supervisor Mesa de Servicio';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $screens = [
            [
                'slug' => 'mesaservicio-dashboard',
                'module' => 'MesaServicio',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Dashboard',
                'route_name' => 'mesaservicio.dashboard.index',
                'permission_name' => 'screens.mesaservicio-dashboard.manage',
                'icon' => 'chart-bar',
                'order' => 0,
            ],
            [
                'slug' => 'mesaservicio-tecnicos',
                'module' => 'MesaServicio',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Técnicos',
                'route_name' => 'mesaservicio.tecnicos.index',
                'permission_name' => 'screens.mesaservicio-tecnicos.manage',
                'icon' => 'user-group',
                'order' => 1,
            ],
            [
                'slug' => 'mesaservicio-destinatarios',
                'module' => 'MesaServicio',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Destinatarios de reporte',
                'route_name' => 'mesaservicio.destinatarios.index',
                'permission_name' => 'screens.mesaservicio-destinatarios.manage',
                'icon' => 'envelope',
                'order' => 2,
            ],
            [
                'slug' => 'mesaservicio-reportes',
                'module' => 'MesaServicio',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Reportes',
                'route_name' => 'mesaservicio.reportes.index',
                'permission_name' => 'screens.mesaservicio-reportes.manage',
                'icon' => 'document-arrow-down',
                'order' => 3,
            ],
            [
                'slug' => 'mesaservicio-slas',
                'module' => 'MesaServicio',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Cumplimiento de SLA',
                'route_name' => 'mesaservicio.slas.index',
                'permission_name' => 'screens.mesaservicio-slas.manage',
                'icon' => 'clock',
                'order' => 4,
            ],
            [
                'slug' => 'mesaservicio-catalogos-sdp',
                'module' => 'MesaServicio',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Catálogos SDP',
                'route_name' => 'mesaservicio.catalogos-sdp.index',
                'permission_name' => 'screens.mesaservicio-catalogos-sdp.manage',
                'icon' => 'book-open',
                'order' => 5,
            ],
        ];

        foreach ($screens as $screen) {
            Screen::updateOrCreate(['slug' => $screen['slug']], $screen);
        }

        Role::findOrCreate('Administrador', 'web')->givePermissionTo(
            collect($screens)->pluck('permission_name')->all()
        );

        // Rol "de app" para el segundo canal de destinatarios de reporte —
        // se resuelve en tiempo real vía User::role(self::ROL_SUPERVISOR),
        // no tiene tabla propia en este módulo.
        Role::findOrCreate(self::ROL_SUPERVISOR, 'web');

        $this->seedSlaDefinitions();
        $this->seedCombinadoStatus();
    }

    /**
     * Fase 8: "Combinado" es un estado 100% local (no viene de
     * sdp:sync-ticket-statuses, que solo trae el catálogo real de SDP) — lo
     * asigna `SyncTicketsCommand::marcarTicketComoCombinado()` cuando detecta,
     * vía el historial por-ticket, que un ticket local fue absorbido por otro
     * ("merge_with"). `tipo = TIPO_COMPLETADO` a propósito, para que cuente
     * como resuelto en el dashboard/ficha de técnico y quede automáticamente
     * excluido del backlog histórico del cierre mensual (Fase 8, Parte 5),
     * sin necesitar un tercer valor de `tipo`.
     *
     * `sdp_id` no puede ser null (columna única, no nullable) pero tampoco
     * corresponde a ningún id real de SDP — no hay un precedente ya sembrado
     * en este archivo para un valor "inventado localmente" (los estados
     * sembrados hasta ahora vienen todos de sync-ticket-statuses, ninguno es
     * local), así que se usa un string legible y claramente no-numérico
     * (`local-combinado`) que no puede colisionar con un sdp_id real de SDP
     * (siempre numérico en esta instancia).
     */
    private function seedCombinadoStatus(): void
    {
        SdpTicketStatus::firstOrCreate(
            ['nombre' => 'Combinado'],
            [
                'sdp_id' => 'local-combinado',
                'tipo' => SdpTicketStatus::TIPO_COMPLETADO,
                'activo' => true,
            ]
        );
    }

    /**
     * ADVERTENCIA (Fase 7): estos 5 registros son PLACEHOLDERS de ejemplo,
     * NO los nombres de prioridad reales de la instancia SDP de este
     * proyecto (no confirmados todavía). El campo `prioridad` de cada uno
     * debe calzar EXACTO con el texto que trae `sdp_tickets.prioridad`
     * (tomado tal cual de `ticket['priority']['name']` en SDP) para que la
     * definición aplique — edita/renombra estos registros desde la pantalla
     * "Cumplimiento de SLA" (/mesa-servicio/slas) en cuanto se confirmen los
     * nombres reales. `updateOrCreate` por `nombre` para no duplicar en
     * corridas repetidas del seeder, pero SIN pisar los tiempos si el
     * usuario ya los editó desde la UI (solo se crea si el nombre no existe
     * todavía).
     */
    private function seedSlaDefinitions(): void
    {
        $definiciones = [
            ['nombre' => 'SLA Baja', 'prioridad' => 'Baja', 'tiempo_primera_respuesta_minutos' => 480, 'tiempo_resolucion_minutos' => 4320, 'activo' => true],
            ['nombre' => 'SLA Media', 'prioridad' => 'Media', 'tiempo_primera_respuesta_minutos' => 240, 'tiempo_resolucion_minutos' => 1440, 'activo' => true],
            ['nombre' => 'SLA Alta', 'prioridad' => 'Alta', 'tiempo_primera_respuesta_minutos' => 60, 'tiempo_resolucion_minutos' => 480, 'activo' => true],
            ['nombre' => 'SLA Urgente', 'prioridad' => 'Urgente', 'tiempo_primera_respuesta_minutos' => 15, 'tiempo_resolucion_minutos' => 120, 'activo' => true],
            ['nombre' => 'SLA por defecto', 'prioridad' => null, 'tiempo_primera_respuesta_minutos' => 240, 'tiempo_resolucion_minutos' => 1440, 'activo' => true],
        ];

        foreach ($definiciones as $definicion) {
            if (! SdpSlaDefinition::where('nombre', $definicion['nombre'])->exists()) {
                SdpSlaDefinition::create($definicion);
            }
        }
    }
}
