<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Nucleo;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NucleoMergeTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Catálogos',
            'name' => 'Catálogos Núcleo',
            'slug' => 'gestionti-catalogos-nucleo',
            'route_name' => 'gestionti.catalogos.nucleo',
            'permission_name' => 'screens.gestionti-catalogos-nucleo.manage',
            'icon' => 'building-office',
            'order' => 10,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_merge_button_appears_for_empresas(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Nucleo::class)->assertSee('Fusionar duplicados');
    }

    public function test_merging_two_empresas_reassigns_every_reference_and_deletes_the_duplicate(): void
    {
        $this->actingAs($this->actingUser());

        $duplicate = Empresa::create(['razon_social' => 'Grupo Profesional de Administracion y Consultoria, S.C.', 'nombre_comercial' => 'GPAC']);
        $keep = Empresa::create(['razon_social' => 'Grupo Profesional de Administracion y Consultoria, S.C.', 'nombre_comercial' => 'Grupo Profesional']);

        $centroCosto = CentroCosto::create([
            'codigo' => 'CC-100',
            'nombre' => 'Centro Demo',
            'empresa_id' => $duplicate->id,
        ]);

        $empleado = Empleado::create([
            'numero_empleado' => 'E-100',
            'nombre' => 'Ana López',
            'empresa_id' => $duplicate->id,
        ]);

        Livewire::test(Nucleo::class)
            ->call('openMerge')
            ->set('mergeDeleteId', $duplicate->id)
            ->set('mergeKeepId', $keep->id)
            ->call('confirmMerge')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('empresas', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('empresas', ['id' => $keep->id]);

        $this->assertSame($keep->id, $centroCosto->fresh()->empresa_id);
        $this->assertSame($keep->id, $empleado->fresh()->empresa_id);
    }

    public function test_merging_a_record_into_itself_is_rejected(): void
    {
        $this->actingAs($this->actingUser());

        $empresa = Empresa::create(['razon_social' => 'Kosmos Demo S.A. de C.V.', 'nombre_comercial' => 'Kosmos Demo']);

        Livewire::test(Nucleo::class)
            ->call('openMerge')
            ->set('mergeDeleteId', $empresa->id)
            ->set('mergeKeepId', $empresa->id)
            ->call('confirmMerge')
            ->assertHasErrors(['mergeDeleteId']);

        $this->assertDatabaseHas('empresas', ['id' => $empresa->id]);
    }
}
