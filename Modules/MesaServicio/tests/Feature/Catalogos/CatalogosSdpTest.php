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
            ->assertSet('tabActiva', SdpCatalogEntry::todos()[0]);
    }

    public function test_it_renders_all_15_tabs(): void
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
            ->assertSet('tabActiva', SdpCatalogEntry::todos()[0]);
    }

    public function test_items_tab_shows_the_category_and_subcategory_it_belongs_to(): void
    {
        SdpCatalogEntry::create([
            'catalogo' => 'items', 'sdp_id' => 'i1', 'nombre' => 'ABC', 'activo' => true,
            'extra' => ['category_nombre' => 'Libra', 'subcategory_nombre' => 'Accesos al Sistema'],
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->call('setTab', 'items')
            ->assertSee('ABC')
            ->assertSee('Libra › Accesos al Sistema')
            ->assertSee('sdp:sync-catalogos items');
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

    public function test_it_paginates_the_active_tab_and_resets_the_page_when_switching(): void
    {
        foreach (range(1, 30) as $n) {
            SdpCatalogEntry::create(['catalogo' => 'items', 'sdp_id' => "i{$n}", 'nombre' => sprintf('Artículo %02d', $n), 'activo' => true]);
        }
        SdpCatalogEntry::create(['catalogo' => 'modes', 'sdp_id' => 'm1', 'nombre' => 'E-Mail', 'activo' => true]);

        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->call('setTab', 'items')
            ->assertSee('Artículo 25')
            ->assertDontSee('Artículo 26')
            ->call('gotoPage', 2)
            ->assertSee('Artículo 26')
            ->assertDontSee('Artículo 01')
            ->call('setTab', 'modes')
            ->assertSee('E-Mail');
    }

    public function test_slas_tab_shows_times_criteria_and_calendar(): void
    {
        SdpCatalogEntry::create([
            'catalogo' => 'slas', 'sdp_id' => 'sla-1', 'nombre' => 'High SLA', 'descripcion' => 'Default SLA for priority High', 'activo' => true,
            'extra' => [
                'respuesta_minutos' => 0, 'resolucion_minutos' => 1560,
                'criterios_texto' => 'Prioridad es Alta', 'ignora_fines_de_semana' => true, 'ignora_feriados' => true,
            ],
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->call('setTab', 'slas')
            ->assertSee('High SLA')
            ->assertSee('Default SLA for priority High')
            ->assertSee('1 d 2 h')
            ->assertSee('Prioridad es Alta')
            ->assertSee('Ignora fines de semana, feriados');
    }

    public function test_duracion_formats_minutes(): void
    {
        $this->assertSame('—', CatalogosSdp::duracion(null));
        $this->assertSame('Sin límite', CatalogosSdp::duracion(0));
        $this->assertSame('30 min', CatalogosSdp::duracion(30));
        $this->assertSame('2 h', CatalogosSdp::duracion(120));
        $this->assertSame('1 d 4 h 15 min', CatalogosSdp::duracion(1440 + 240 + 15));
    }

    public function test_export_button_points_to_the_active_tab(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->call('setTab', 'items')
            ->assertSee(route('mesaservicio.catalogos-sdp.export', ['tab' => 'items']), escape: false)
            ->assertSee('Exportar a Excel');
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(CatalogosSdp::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'catalogos-sdp'), escape: false);
    }
}
