<?php

namespace Modules\GestionTI\Tests\Feature\Inventarios\FichaActivo;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Inventarios\FichaActivo\Buscar;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\TipoEquipo;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuscarTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Ficha de Activo',
            'slug' => 'gestionti-ficha-activo',
            'route_name' => 'gestionti.ficha-activo.index',
            'permission_name' => 'screens.gestionti-ficha-activo.manage',
            'icon' => 'clock',
            'order' => 35,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function tipoEquipo(): TipoEquipo
    {
        return TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
    }

    private function estatus(): EstatusActivo
    {
        return EstatusActivo::firstOrCreate(['codigo' => 'en_stock'], ['nombre' => 'En stock']);
    }

    private function asset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'tipo_equipo_id' => $this->tipoEquipo()->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $this->estatus()->id,
        ], $overrides));
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/ficha-activo')->assertForbidden();
    }

    public function test_search_filters_by_codigo(): void
    {
        $this->actingAs($this->actingUser());

        $match = $this->asset(['codigo' => 'KOS-LAPTOP-000001']);
        $other = $this->asset(['codigo' => 'KOS-LAPTOP-000002']);

        $records = Livewire::test(Buscar::class)->set('search', '000001')->viewData('records');

        $this->assertTrue($records->contains($match));
        $this->assertFalse($records->contains($other));
    }

    public function test_search_filters_by_numero_serie(): void
    {
        $this->actingAs($this->actingUser());

        $match = $this->asset(['codigo' => 'KOS-LAPTOP-000003', 'numero_serie' => 'SN-ABC-123']);
        $other = $this->asset(['codigo' => 'KOS-LAPTOP-000004', 'numero_serie' => 'SN-XYZ-999']);

        $records = Livewire::test(Buscar::class)->set('search', 'ABC-123')->viewData('records');

        $this->assertTrue($records->contains($match));
        $this->assertFalse($records->contains($other));
    }

    public function test_search_filters_by_service_tag(): void
    {
        $this->actingAs($this->actingUser());

        $match = $this->asset(['codigo' => 'KOS-LAPTOP-000005', 'service_tag' => 'TAG-111']);
        $other = $this->asset(['codigo' => 'KOS-LAPTOP-000006', 'service_tag' => 'TAG-222']);

        $records = Livewire::test(Buscar::class)->set('search', 'TAG-111')->viewData('records');

        $this->assertTrue($records->contains($match));
        $this->assertFalse($records->contains($other));
    }

    public function test_each_row_links_to_the_correct_asset_ficha(): void
    {
        $this->actingAs($this->actingUser());

        $asset = $this->asset(['codigo' => 'KOS-LAPTOP-000007']);

        Livewire::test(Buscar::class)
            ->assertSee(route('gestionti.ficha-activo.show', $asset), false);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-ficha-activo',
            'route_name' => 'gestionti.ficha-activo.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-ficha-activo.manage'));
    }
}
