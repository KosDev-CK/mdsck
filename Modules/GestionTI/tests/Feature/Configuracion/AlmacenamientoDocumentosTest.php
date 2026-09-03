<?php

namespace Modules\GestionTI\Tests\Feature\Configuracion;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Configuracion\AlmacenamientoDocumentos;
use Modules\GestionTI\Models\ConfiguracionDocumentos;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlmacenamientoDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'General',
            'name' => 'Configuración de Almacenamiento',
            'slug' => 'gestionti-almacenamiento-documentos',
            'route_name' => 'gestionti.almacenamiento-documentos.index',
            'permission_name' => 'screens.gestionti-almacenamiento-documentos.manage',
            'icon' => 'cloud-arrow-up',
            'order' => 5,
        ]);

        $role = Role::findOrCreate('Administrador de TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/almacenamiento-documentos')->assertForbidden();
    }

    public function test_seeding_creates_the_default_configuration(): void
    {
        ConfiguracionDocumentos::current();

        $this->assertDatabaseHas('configuracion_documentos', ['id' => 1]);
        // Default deliberadamente vacío hasta que el permiso `Sites.Selected`
        // de Azure esté concedido — ver el docblock de
        // `ConfiguracionDocumentos::DEFAULTS`. Activar "responsiva"/
        // "remision_proveedor" es una acción manual desde la pantalla, no un
        // default de siembra, para no romper el primer upload real en un
        // despliegue donde SharePoint todavía no esté listo.
        $this->assertSame(
            [],
            ConfiguracionDocumentos::current()->tipos_sharepoint
        );
    }

    public function test_mount_preloads_the_current_configuration(): void
    {
        $this->actingAs($this->actingUser());
        ConfiguracionDocumentos::current()->update(['tipos_sharepoint' => ['sic', 'factura']]);

        Livewire::test(AlmacenamientoDocumentos::class)
            ->assertSet('tiposSharepoint', ['sic', 'factura']);
    }

    public function test_save_updates_the_singleton(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(AlmacenamientoDocumentos::class)
            ->set('tiposSharepoint', ['responsiva', 'orden_servicio'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['responsiva', 'orden_servicio'], ConfiguracionDocumentos::current()->fresh()->tipos_sharepoint);
    }

    public function test_save_rejects_a_value_outside_the_5_known_document_types(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(AlmacenamientoDocumentos::class)
            ->set('tiposSharepoint', ['no-es-un-tipo-valido'])
            ->call('save')
            ->assertHasErrors(['tiposSharepoint.0']);
    }

    public function test_save_allows_unchecking_everything_back_to_fully_local(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(AlmacenamientoDocumentos::class)
            ->set('tiposSharepoint', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([], ConfiguracionDocumentos::current()->fresh()->tipos_sharepoint);
    }
}
