<?php

namespace Modules\MesaServicio\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Catalogos\GruposAnaliticos;
use Modules\MesaServicio\Models\GrupoAnalitico;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GruposAnaliticosTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Grupos Analíticos',
            'slug' => 'mesaservicio-grupos-analiticos',
            'route_name' => 'mesaservicio.grupos-analiticos.index',
            'permission_name' => 'screens.mesaservicio-grupos-analiticos.manage',
            'icon' => 'tag',
            'order' => 6,
        ]);

        $role = Role::findOrCreate('Rol Grupos Analiticos test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/grupos-analiticos')->assertForbidden();
    }

    public function test_it_can_create_a_group(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(GruposAnaliticos::class)
            ->set('newNombre', 'Infraestructura')
            ->set('newDescripcion', 'Técnicos de infraestructura y redes')
            ->call('addGrupo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grupos_analiticos', [
            'nombre' => 'Infraestructura',
            'descripcion' => 'Técnicos de infraestructura y redes',
            'activo' => true,
        ]);
    }

    public function test_it_can_create_a_group_without_a_description(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(GruposAnaliticos::class)
            ->set('newNombre', 'Mesa de Ayuda')
            ->set('newDescripcion', '')
            ->call('addGrupo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grupos_analiticos', [
            'nombre' => 'Mesa de Ayuda',
            'descripcion' => null,
        ]);
    }

    public function test_it_rejects_a_duplicate_name(): void
    {
        GrupoAnalitico::create(['nombre' => 'Infraestructura', 'activo' => true]);

        $this->actingAs($this->actingUser());

        Livewire::test(GruposAnaliticos::class)
            ->set('newNombre', 'Infraestructura')
            ->call('addGrupo')
            ->assertHasErrors('newNombre');
    }

    public function test_it_can_edit_an_existing_group(): void
    {
        $grupo = GrupoAnalitico::create(['nombre' => 'Infraestructura', 'descripcion' => 'Original', 'activo' => true]);

        $this->actingAs($this->actingUser());

        Livewire::test(GruposAnaliticos::class)
            ->call('edit', $grupo->id)
            ->assertSet('editNombre', 'Infraestructura')
            ->set('editNombre', 'Infraestructura y Redes')
            ->set('editDescripcion', 'Actualizada')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grupos_analiticos', [
            'id' => $grupo->id,
            'nombre' => 'Infraestructura y Redes',
            'descripcion' => 'Actualizada',
        ]);
    }

    public function test_it_can_toggle_activo(): void
    {
        $grupo = GrupoAnalitico::create(['nombre' => 'Infraestructura', 'activo' => true]);

        $this->actingAs($this->actingUser());

        Livewire::test(GruposAnaliticos::class)->call('toggleActivo', $grupo->id);

        $this->assertDatabaseHas('grupos_analiticos', ['id' => $grupo->id, 'activo' => false]);

        Livewire::test(GruposAnaliticos::class)->call('toggleActivo', $grupo->id);

        $this->assertDatabaseHas('grupos_analiticos', ['id' => $grupo->id, 'activo' => true]);
    }

    public function test_it_can_delete_a_group(): void
    {
        $grupo = GrupoAnalitico::create(['nombre' => 'Temporal', 'activo' => true]);

        $this->actingAs($this->actingUser());

        Livewire::test(GruposAnaliticos::class)->call('delete', $grupo->id);

        $this->assertDatabaseMissing('grupos_analiticos', ['id' => $grupo->id]);
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(GruposAnaliticos::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'grupos-analiticos'), escape: false);
    }
}
