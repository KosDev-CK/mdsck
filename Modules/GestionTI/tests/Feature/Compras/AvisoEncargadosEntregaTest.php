<?php

namespace Modules\GestionTI\Tests\Feature\Compras;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Compras as CatalogosCompras;
use Modules\GestionTI\Livewire\Compras\SolicitudesProveedor;
use Modules\GestionTI\Mail\SolicitudProveedorEncargadoMail;
use Modules\GestionTI\Mail\SolicitudProveedorMail;
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\SolicitudProveedor;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Copia de la Solicitud a Proveedores a los encargados de cada lugar de
 * entrega (lo que deben recibir en su sitio), al enviarla al proveedor o con
 * "Avisar a encargados" (solo a ellos).
 */
class AvisoEncargadosEntregaTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI', 'group_label' => 'Compras', 'name' => 'Solicitud a Proveedores',
            'slug' => 'gestionti-solicitudes-proveedor', 'route_name' => 'gestionti.solicitudes-proveedor.index',
            'permission_name' => 'screens.gestionti-solicitudes-proveedor.manage', 'icon' => 'shopping-cart', 'order' => 21,
        ]);
        $role = Role::findOrCreate('Compras', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function sitio(string $nombre, array $encargados = []): LugarEntrega
    {
        $sitio = LugarEntrega::create(['nombre' => $nombre, 'activo' => true]);
        $sitio->usuarios()->sync(collect($encargados)->pluck('id')->all());

        return $sitio;
    }

    /** @param  array<int, LugarEntrega>  $sitios  Una línea por cada sitio. */
    private function solicitud(array $sitios): SolicitudProveedor
    {
        $vendor = Proveedor::create([
            'razon_social' => 'Proveedor SA', 'nombre_comercial' => 'Proveedor', 'contacto_correo' => 'ventas@proveedor.test',
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-ENC-001', 'vendor_id' => $vendor->id, 'fecha_solicitud' => '2026-10-24',
            'tipo_solicitud' => 'regular', 'estatus' => SolicitudProveedor::ESTATUS_SOLICITADA,
        ]);

        foreach ($sitios as $i => $sitio) {
            $solicitud->lineas()->create([
                'descripcion_libre' => 'Equipo '.($i + 1),
                'cantidad_solicitada' => $i + 2,
                'lugar_entrega_id' => $sitio->id,
            ]);
        }

        return $solicitud;
    }

    public function test_sending_to_the_vendor_also_sends_each_encargado_only_their_own_lines(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $ana = User::factory()->create(['is_active' => true, 'email' => 'ana@example.test']);
        $luis = User::factory()->create(['is_active' => true, 'email' => 'luis@example.test']);
        $solicitud = $this->solicitud([$this->sitio('Zurich', [$ana]), $this->sitio('CEDA', [$luis])]);

        Livewire::test(SolicitudesProveedor::class)->call('enviarAProveedor', $solicitud->id);

        Mail::assertSent(SolicitudProveedorMail::class, 1);
        Mail::assertSent(SolicitudProveedorEncargadoMail::class, 2);
        Mail::assertSent(SolicitudProveedorEncargadoMail::class, fn ($m) => $m->hasTo('ana@example.test')
            && $m->lineas->count() === 1 && $m->lineas->first()->descripcion_libre === 'Equipo 1');
        Mail::assertSent(SolicitudProveedorEncargadoMail::class, fn ($m) => $m->hasTo('luis@example.test')
            && $m->lineas->count() === 1 && $m->lineas->first()->descripcion_libre === 'Equipo 2');
        $this->assertNotNull($solicitud->fresh()->encargados_avisados_at);
    }

    public function test_an_encargado_of_two_sites_gets_a_single_mail_with_both_lines(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $ana = User::factory()->create(['is_active' => true, 'email' => 'ana@example.test']);
        $solicitud = $this->solicitud([$this->sitio('Zurich', [$ana]), $this->sitio('CEDA', [$ana])]);

        Livewire::test(SolicitudesProveedor::class)->call('avisarEncargados', $solicitud->id);

        Mail::assertSent(SolicitudProveedorEncargadoMail::class, 1);
        Mail::assertSent(SolicitudProveedorEncargadoMail::class, fn ($m) => $m->lineas->count() === 2);
    }

    public function test_notify_encargados_only_writes_to_them_and_never_to_the_vendor(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $ana = User::factory()->create(['is_active' => true, 'email' => 'ana@example.test']);
        $solicitud = $this->solicitud([$this->sitio('Zurich', [$ana])]);
        $solicitud->update(['enviada_at' => now()->subDay()]);

        Livewire::test(SolicitudesProveedor::class)->call('avisarEncargados', $solicitud->id);

        Mail::assertNotSent(SolicitudProveedorMail::class);
        Mail::assertSent(SolicitudProveedorEncargadoMail::class, 1);
        $this->assertNotNull($solicitud->fresh()->encargados_avisados_at);
    }

    public function test_sites_without_encargados_and_inactive_users_are_skipped_and_reported(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $inactivo = User::factory()->create(['is_active' => false, 'email' => 'baja@example.test']);
        $solicitud = $this->solicitud([$this->sitio('Zurich', [$inactivo]), $this->sitio('Sotelo')]);

        Livewire::test(SolicitudesProveedor::class)
            ->call('avisarEncargados', $solicitud->id)
            ->assertSee('Sin encargados en');

        Mail::assertNothingSent();
        $this->assertNull($solicitud->fresh()->encargados_avisados_at);
    }

    public function test_a_cancelled_request_does_not_notify(): void
    {
        Mail::fake();
        $this->actingAs($this->actingUser());

        $ana = User::factory()->create(['is_active' => true, 'email' => 'ana@example.test']);
        $solicitud = $this->solicitud([$this->sitio('Zurich', [$ana])]);
        $solicitud->update(['estatus' => SolicitudProveedor::ESTATUS_CANCELADA]);

        Livewire::test(SolicitudesProveedor::class)->call('avisarEncargados', $solicitud->id);

        Mail::assertNothingSent();
    }

    public function test_lugar_de_entrega_catalog_saves_its_encargados(): void
    {
        $screen = Screen::create([
            'module' => 'GestionTI', 'group_label' => 'Compras', 'name' => 'Catálogos de Compras',
            'slug' => 'gestionti-catalogos-compras', 'route_name' => 'gestionti.catalogos-compras.index',
            'permission_name' => 'screens.gestionti-catalogos-compras.manage', 'icon' => 'tag', 'order' => 1,
        ]);
        $role = Role::findOrCreate('Catalogos', 'web');
        $role->givePermissionTo($screen->permission_name);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);

        $ana = User::factory()->create(['is_active' => true]);
        $luis = User::factory()->create(['is_active' => true]);

        Livewire::test(CatalogosCompras::class)
            ->set('tab', 'lugares_entrega')
            ->call('create')
            ->set('form.nombre', 'Patanco')
            ->set('usuariosIds', [$ana->id, $luis->id])
            ->call('save')
            ->assertHasNoErrors();

        $sitio = LugarEntrega::where('nombre', 'Patanco')->firstOrFail();
        $this->assertEqualsCanonicalizing([$ana->id, $luis->id], $sitio->usuarios()->pluck('users.id')->all());

        Livewire::test(CatalogosCompras::class)
            ->set('tab', 'lugares_entrega')
            ->call('edit', $sitio->id)
            ->assertSet('usuariosIds', $sitio->usuarios()->pluck('users.id')->map(fn ($i) => (int) $i)->all())
            ->set('usuariosIds', [$ana->id])
            ->call('save');

        $this->assertSame([$ana->id], $sitio->usuarios()->pluck('users.id')->all());
    }

    public function test_the_encargado_mail_renders_with_the_site_the_line_code_and_quantity(): void
    {
        $ana = User::factory()->create(['is_active' => true, 'email' => 'ana@example.test']);
        $solicitud = $this->solicitud([$this->sitio('Zurich', [$ana]), $this->sitio('CEDA')]);
        $solicitud->load(['vendor', 'creadoPor', 'lineas.articulo', 'lineas.sic', 'lineas.ebsRequisition.lines', 'lineas.lugarEntrega']);

        $html = (new SolicitudProveedorEncargadoMail($solicitud, $solicitud->lineas->where('lugar_entrega_id', $solicitud->lineas->first()->lugar_entrega_id)->values()))->render();

        $this->assertStringContainsString('SP-ENC-001-L1', $html);
        $this->assertStringContainsString('Zurich', $html);
        $this->assertStringContainsString('Equipo 1', $html);
        $this->assertStringNotContainsString('Equipo 2', $html);
    }
}
