<?php

namespace Database\Seeders;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $screens = [
            ['name' => 'Dashboard', 'slug' => 'dashboard', 'route_name' => 'dashboard', 'permission_name' => 'screens.dashboard.view', 'icon' => 'home', 'order' => 1],
            ['name' => 'Configuración de acceso', 'slug' => 'invitations', 'route_name' => 'invitations.index', 'permission_name' => 'screens.invitations.manage', 'icon' => 'user-plus', 'order' => 2],
            ['name' => 'Perfiles', 'slug' => 'roles', 'route_name' => 'roles.index', 'permission_name' => 'screens.roles.manage', 'icon' => 'shield', 'order' => 3],
            ['name' => 'Perfiles por usuario', 'slug' => 'user-roles', 'route_name' => 'user-roles.index', 'permission_name' => 'screens.user-roles.manage', 'icon' => 'users', 'order' => 4],
            ['name' => 'Conexiones a BD', 'slug' => 'connections', 'route_name' => 'connections.index', 'permission_name' => 'screens.connections.manage', 'icon' => 'database', 'order' => 5],
            ['name' => 'Módulos', 'slug' => 'modules', 'route_name' => 'modules.index', 'permission_name' => 'screens.modules.manage', 'icon' => 'puzzle', 'order' => 6],
            ['name' => 'Bitácora de seguridad', 'slug' => 'security-log', 'route_name' => 'security-log.index', 'permission_name' => 'screens.security.view', 'icon' => 'lock', 'order' => 7],
        ];

        foreach ($screens as $screen) {
            Screen::updateOrCreate(['slug' => $screen['slug']], $screen);
        }

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->syncPermissions(Screen::pluck('permission_name'));

        $admin = User::updateOrCreate(
            ['email' => 'victor.gonzalez@landit.com.mx'],
            [
                'name' => 'Victor Gonzalez',
                'is_active' => true,
                'invitation_accepted_at' => now(),
            ]
        );

        $admin->syncRoles(['Administrador']);
    }
}
