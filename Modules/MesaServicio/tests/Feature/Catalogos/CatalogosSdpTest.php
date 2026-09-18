<?php

namespace Modules\MesaServicio\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Catalogos\CatalogosSdp;
use Modules\MesaServicio\Models\SdpCatalogEntry;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogosSdpTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Catálogos SDP',
            'slug' => 'mesaservicio-catalogos-sdp',
            'route_name' => 'mesaservicio.catalogos-sdp.index',
            'permission_name' => 'screens.mesaservicio-catalogos-sdp.manage',
            'icon' => 'book-open',
            'order' => 5,
        ]);

        $role = Role::findOrCreate('Rol Catalogos SDP test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/catalogos-sdp')->assertForbidden();
    }

    public function test_it_defaults_to_the_first_known_catalog_tab(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->assertSet('tabActiva', SdpCatalogEntry::CATALOGOS[0]);
    }

    public function test_it_renders_all_12_tabs(): void
    {
        $this->actingAs($this->actingUser());

        $component = Livewire::test(CatalogosSdp::class);

        foreach (CatalogosSdp::etiquetas() as $etiqueta) {
            $component->assertSee($etiqueta);
        }
    }

    public function test_switching_tabs_shows_the_entries_of_that_catalog_only(): void
    {
        SdpCatalogEntry::create(['catalogo' => 'categories', 'sdp_id' => 'cat-1', 'nombre' => 'Hardware', 'activo' => true]);
        SdpCatalogEntry::create(['catalogo' => 'modes', 'sdp_id' => 'mode-1', 'nombre' => 'E-Mail', 'activo' => true]);

        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->assertSee('Hardware')
            ->assertDontSee('E-Mail')
            ->call('setTab', 'modes')
            ->assertSet('tabActiva', 'modes')
            ->assertSee('E-Mail')
            ->assertDontSee('Hardware');
    }

    public function test_setting_an_unknown_tab_is_ignored(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->call('setTab', 'no-existe')
            ->assertSet('tabActiva', SdpCatalogEntry::CATALOGOS[0]);
    }

    public function test_it_shows_the_color_swatch_and_activo_badge(): void
    {
        SdpCatalogEntry::create([
            'catalogo' => 'priorities', 'sdp_id' => 'p1', 'nombre' => 'Urgente',
            'color' => '#ff0000', 'activo' => true,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->call('setTab', 'priorities')
            ->assertSee('#ff0000')
            ->assertSee('Activo');
    }

    public function test_sincronizar_runs_the_sync_command_and_flashes_status(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/categories*' => Http::response([
                'categories' => [['id' => 'cat-1', 'name' => 'Hardware', 'deleted' => false]],
                'list_info' => ['has_more_rows' => false],
            ], 200),
            '*/api/v3/*' => Http::response([
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->call('sincronizar')
            ->assertSet('sincronizando', false);

        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'categories', 'sdp_id' => 'cat-1']);
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'catalogos-sdp'), escape: false);
    }
}
