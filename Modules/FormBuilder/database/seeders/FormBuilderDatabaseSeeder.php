<?php

namespace Modules\FormBuilder\Database\Seeders;

use App\Models\Screen;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class FormBuilderDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $screens = [
            [
                'slug' => 'formbuilder',
                'module' => 'FormBuilder',
                'group_label' => 'Módulos',
                'name' => 'Formularios',
                'route_name' => 'formbuilder.forms.index',
                'permission_name' => 'screens.formbuilder.manage',
                'icon' => 'clipboard-document-list',
                'order' => 91,
            ],
            [
                'slug' => 'formbuilder-mis-formularios',
                'module' => 'FormBuilder',
                'group_label' => 'Módulos',
                'name' => 'Mis Formularios',
                'route_name' => 'formbuilder.links.index',
                'permission_name' => 'screens.formbuilder.capture',
                'icon' => 'pencil-square',
                'order' => 92,
            ],
        ];

        foreach ($screens as $screen) {
            Screen::updateOrCreate(['slug' => $screen['slug']], $screen);
        }

        Role::findOrCreate('Administrador', 'web')->givePermissionTo(
            collect($screens)->pluck('permission_name')->all()
        );
    }
}
