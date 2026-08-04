<?php

namespace Modules\Ejemplo\Database\Seeders;

use App\Models\Screen;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class EjemploDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $screen = Screen::updateOrCreate(
            ['slug' => 'ejemplo'],
            [
                'module' => 'Ejemplo',
                'group_label' => 'Módulos',
                'name' => 'Ejemplo',
                'route_name' => 'ejemplo.index',
                'permission_name' => 'screens.ejemplo.manage',
                'icon' => 'cube',
                'order' => 90,
            ]
        );

        Role::findOrCreate('Administrador', 'web')->givePermissionTo($screen->permission_name);
    }
}
