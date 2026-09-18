<?php

namespace Modules\MesaServicio\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Catalogos\Tecnicos;
use Modules\MesaServicio\Models\GrupoAnalitico;
use Modules\MesaServicio\Models\SdpTechnician;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TecnicosTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Técnicos',
            'slug' => 'mesaservicio-tecnicos',
            'route_name' => 'mesaservicio.tecnicos.index',
            'permission_name' => 'screens.mesaservicio-tecnicos.manage',
            'icon' => 'user-group',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Supervisor Mesa de Servicio Test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/tecnicos')->assertForbidden();
    }

    public function test_it_lists_technicians(): void
    {
        SdpTechnician::create([
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'correo' => 'juan@example.test',
            'puesto' => 'Analista',
            'activo' => true,
            'es_nivel_1' => false,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->assertSee('Juan Pérez')
            ->assertSee('juan@example.test');
    }

    public function test_it_can_toggle_nivel_1(): void
    {
        $technician = SdpTechnician::create([
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'correo' => 'juan@example.test',
            'activo' => true,
            'es_nivel_1' => false,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->call('toggleNivel1', $technician->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sdp_technicians', [
            'id' => $technician->id,
            'es_nivel_1' => true,
        ]);

        Livewire::test(Tecnicos::class)->call('toggleNivel1', $technician->id);

        $this->assertDatabaseHas('sdp_technicians', [
            'id' => $technician->id,
            'es_nivel_1' => false,
        ]);
    }

    public function test_it_filters_by_activo(): void
    {
        SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Activo Uno', 'activo' => true, 'es_nivel_1' => false]);
        SdpTechnician::create(['sdp_id' => 't2', 'nombre' => 'Inactivo Uno', 'activo' => false, 'es_nivel_1' => false]);

        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->set('filterActivo', 'activos')
            ->assertSee('Activo Uno')
            ->assertDontSee('Inactivo Uno');

        Livewire::test(Tecnicos::class)
            ->set('filterActivo', 'inactivos')
            ->assertSee('Inactivo Uno')
            ->assertDontSee('Activo Uno');
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'tecnicos'), escape: false);
    }

    public function test_it_filters_by_nivel_1(): void
    {
        SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Nivel Uno', 'activo' => true, 'es_nivel_1' => true]);
        SdpTechnician::create(['sdp_id' => 't2', 'nombre' => 'No Nivel Uno', 'activo' => true, 'es_nivel_1' => false]);

        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->set('filterNivel1', 'si')
            ->assertSee('Nivel Uno')
            ->assertDontSee('No Nivel Uno');
    }

    public function test_it_can_assign_a_grupo_analitico(): void
    {
        $grupo = GrupoAnalitico::create(['nombre' => 'Infraestructura', 'activo' => true]);

        $technician = SdpTechnician::create([
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'correo' => 'juan@example.test',
            'puesto' => 'Analista',
            'activo' => true,
            'es_nivel_1' => true,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->call('asignarGrupoAnalitico', $technician->id, $grupo->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sdp_technicians', [
            'id' => $technician->id,
            'grupo_analitico_id' => $grupo->id,
            // No debe tocar ningún otro campo del técnico.
            'nombre' => 'Juan Pérez',
            'correo' => 'juan@example.test',
            'puesto' => 'Analista',
            'activo' => true,
            'es_nivel_1' => true,
        ]);
    }

    public function test_it_can_unset_the_grupo_analitico_back_to_null(): void
    {
        $grupo = GrupoAnalitico::create(['nombre' => 'Infraestructura', 'activo' => true]);

        $technician = SdpTechnician::create([
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'activo' => true,
            'es_nivel_1' => false,
            'grupo_analitico_id' => $grupo->id,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->call('asignarGrupoAnalitico', $technician->id, null)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sdp_technicians', [
            'id' => $technician->id,
            'grupo_analitico_id' => null,
        ]);
    }

    public function test_it_filters_by_grupo_analitico(): void
    {
        $grupo = GrupoAnalitico::create(['nombre' => 'Infraestructura', 'activo' => true]);

        SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Con Grupo', 'activo' => true, 'es_nivel_1' => false, 'grupo_analitico_id' => $grupo->id]);
        SdpTechnician::create(['sdp_id' => 't2', 'nombre' => 'Sin Grupo', 'activo' => true, 'es_nivel_1' => false]);

        $this->actingAs($this->actingUser());

        Livewire::test(Tecnicos::class)
            ->set('filterGrupoAnalitico', (string) $grupo->id)
            ->assertSee('Con Grupo')
            ->assertDontSee('Sin Grupo');

        Livewire::test(Tecnicos::class)
            ->set('filterGrupoAnalitico', 'sin-asignar')
            ->assertSee('Sin Grupo')
            ->assertDontSee('Con Grupo');
    }
}
