<?php

namespace Modules\GestionTI\Tests\Feature\Compras;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Compras as CatalogosCompras;
use Modules\GestionTI\Livewire\Compras\EntregasInternas;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Notifications\EntregaInternaConfirmadaNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proveedor INTERNO (un área) y la confirmación de entrega que sus
 * encargados hacen en "Entregas internas".
 */
class EntregasInternasTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConPantalla(string $nombre = 'Encargado'): User
    {
        $screen = Screen::firstOrCreate(['slug' => 'gestionti-entregas-internas'], [
            'module' => 'GestionTI',
            'group_label' => 'Compras',
            'name' => 'Entregas internas',
            'route_name' => 'gestionti.entregas-internas.index',
            'permission_name' => 'screens.gestionti-entregas-internas.manage',
            'icon' => 'truck',
            'order' => 22,
        ]);

        $role = Role::findOrCreate('Areas internas', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true, 'name' => $nombre]);
        $user->assignRole($role);

        return $user;
    }

    private function areaInterna(string $nombre, array $encargados): Proveedor
    {
        $area = Proveedor::create([
            'razon_social' => $nombre,
            'nombre_comercial' => $nombre,
            'contacto_correo' => 'area@example.test',
            'es_interno' => true,
        ]);
        $area->usuarios()->sync(collect($encargados)->pluck('id')->all());

        return $area;
    }

    private function solicitud(Proveedor $vendor, array $overrides = []): SolicitudProveedor
    {
        $creador = User::factory()->create(['is_active' => true, 'name' => 'Creador TI']);

        return SolicitudProveedor::create(array_merge([
            'folio' => 'SP-INT-'.random_int(100, 999),
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-10-20',
            'tipo_solicitud' => 'regular',
            'estatus' => SolicitudProveedor::ESTATUS_SOLICITADA,
            'enviada_at' => now(),
            'creado_por_user_id' => $creador->id,
        ], $overrides));
    }

    public function test_an_encargado_only_sees_the_requests_of_their_own_area(): void
    {
        $yo = $this->usuarioConPantalla('Yo');
        $otro = $this->usuarioConPantalla('Otro');
        $miArea = $this->areaInterna('Telefonía', [$yo]);
        $otraArea = $this->areaInterna('Infraestructura', [$otro]);

        $mia = $this->solicitud($miArea, ['folio' => 'SP-MIA']);
        $ajena = $this->solicitud($otraArea, ['folio' => 'SP-AJENA']);

        $this->actingAs($yo);

        Livewire::test(EntregasInternas::class)
            ->assertSee('SP-MIA')
            ->assertDontSee('SP-AJENA')
            ->call('openDetalle', $ajena->id)
            ->assertSet('showModal', false)
            ->call('openDetalle', $mia->id)
            ->assertSet('showModal', true);
    }

    public function test_requests_that_were_never_sent_or_go_to_an_external_vendor_do_not_appear(): void
    {
        $yo = $this->usuarioConPantalla();
        $area = $this->areaInterna('Telefonía', [$yo]);
        $externo = Proveedor::create(['razon_social' => 'Externo SA', 'nombre_comercial' => 'Externo']);

        $this->solicitud($area, ['folio' => 'SP-SIN-ENVIAR', 'enviada_at' => null]);
        $this->solicitud($externo, ['folio' => 'SP-EXTERNO']);
        $this->solicitud($area, ['folio' => 'SP-CANCELADA', 'estatus' => SolicitudProveedor::ESTATUS_CANCELADA]);

        $this->actingAs($yo);

        Livewire::test(EntregasInternas::class)
            ->assertDontSee('SP-SIN-ENVIAR')
            ->assertDontSee('SP-EXTERNO')
            ->assertDontSee('SP-CANCELADA');
    }

    public function test_confirming_the_delivery_saves_it_and_notifies_the_creator(): void
    {
        Notification::fake();

        $yo = $this->usuarioConPantalla('Yo');
        $area = $this->areaInterna('Telefonía', [$yo]);
        $solicitud = $this->solicitud($area);

        $this->actingAs($yo);

        Livewire::test(EntregasInternas::class)
            ->call('openDetalle', $solicitud->id)
            ->set('notas', 'Se entregó en el almacén')
            ->call('confirmarEntrega')
            ->assertSet('showModal', false);

        $solicitud->refresh();
        $this->assertNotNull($solicitud->entrega_confirmada_at);
        $this->assertSame($yo->id, $solicitud->entrega_confirmada_por_user_id);
        $this->assertSame('Se entregó en el almacén', $solicitud->entrega_confirmada_notas);

        Notification::assertSentTo($solicitud->creadoPor, EntregaInternaConfirmadaNotification::class);
    }

    public function test_it_cannot_be_confirmed_twice(): void
    {
        Notification::fake();

        $yo = $this->usuarioConPantalla();
        $area = $this->areaInterna('Telefonía', [$yo]);
        $solicitud = $this->solicitud($area);

        $this->actingAs($yo);

        $componente = Livewire::test(EntregasInternas::class)
            ->call('openDetalle', $solicitud->id)
            ->call('confirmarEntrega');

        $primera = $solicitud->fresh()->entrega_confirmada_at;

        $componente->call('openDetalle', $solicitud->id)->call('confirmarEntrega');

        $this->assertEquals($primera, $solicitud->fresh()->entrega_confirmada_at);
        Notification::assertSentToTimes($solicitud->creadoPor, EntregaInternaConfirmadaNotification::class, 1);
    }

    public function test_a_user_outside_the_area_cannot_confirm_by_forcing_the_id(): void
    {
        $yo = $this->usuarioConPantalla('Yo');
        $otro = $this->usuarioConPantalla('Otro');
        $areaAjena = $this->areaInterna('Infraestructura', [$otro]);
        $solicitud = $this->solicitud($areaAjena);

        $this->actingAs($yo);

        Livewire::test(EntregasInternas::class)
            ->set('detalleId', $solicitud->id)
            ->call('confirmarEntrega');

        $this->assertNull($solicitud->fresh()->entrega_confirmada_at);
    }

    public function test_an_administrator_sees_every_internal_request(): void
    {
        $admin = $this->usuarioConPantalla('Admin');
        Role::findOrCreate('Administrador', 'web');
        $admin->assignRole('Administrador');

        $otro = $this->usuarioConPantalla('Otro');
        $solicitud = $this->solicitud($this->areaInterna('Infraestructura', [$otro]), ['folio' => 'SP-DE-OTRO']);

        $this->actingAs($admin);

        Livewire::test(EntregasInternas::class)->assertSee('SP-DE-OTRO');
    }

    public function test_pending_request_helper_reflects_confirmation(): void
    {
        $yo = $this->usuarioConPantalla();
        $solicitud = $this->solicitud($this->areaInterna('Telefonía', [$yo]));

        $this->assertTrue($solicitud->fresh()->pendienteDeConfirmacionInterna());

        $solicitud->update(['entrega_confirmada_at' => now()]);

        $this->assertFalse($solicitud->fresh()->pendienteDeConfirmacionInterna());
    }

    public function test_an_internal_vendor_requires_email_and_at_least_one_encargado(): void
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

        Livewire::test(CatalogosCompras::class)
            ->call('create')
            ->set('form.nombre_comercial', 'Telefonía')
            ->set('form.razon_social', 'Telefonía')
            ->set('form.es_interno', true)
            ->call('save')
            ->assertHasErrors(['usuariosIds', 'form.contacto_correo']);

        Livewire::test(CatalogosCompras::class)
            ->call('create')
            ->set('form.nombre_comercial', 'Telefonía')
            ->set('form.razon_social', 'Telefonía')
            ->set('form.contacto_correo', 'tel@example.test')
            ->set('form.es_interno', true)
            ->set('usuariosIds', [$user->id])
            ->call('save')
            ->assertHasNoErrors();

        $area = Proveedor::where('nombre_comercial', 'Telefonía')->firstOrFail();
        $this->assertTrue($area->es_interno);
        $this->assertSame([$user->id], $area->usuarios()->pluck('users.id')->all());
    }
}
