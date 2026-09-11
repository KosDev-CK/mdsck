<?php

namespace Modules\MesaServicio\Database\Seeders;

use App\Models\Screen;
use Illuminate\Database\Seeder;
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
    }
}
