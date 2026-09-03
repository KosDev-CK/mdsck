<?php

namespace Modules\GestionTI\Tests\Feature\Avisos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Avisos\Historial;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\TipoAviso;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistorialTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'General',
            'name' => 'Historial de Avisos',
            'slug' => 'gestionti-avisos-historial',
            'route_name' => 'gestionti.avisos-historial.index',
            'permission_name' => 'screens.gestionti-avisos-historial.manage',
            'icon' => 'clock',
            'order' => 3,
        ]);

        $role = Role::findOrCreate('Administrador', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function avisoEnviado(array $overrides = []): AvisoEnviado
    {
        $tipoAviso = TipoAviso::firstOrCreate(
            ['codigo' => $overrides['codigo'] ?? 'EVENTO_HIST'],
            [
                'descripcion' => 'Aviso histórico',
                'entidad_relacionada' => 'Marca',
                'evento_disparador' => $overrides['codigo'] ?? 'EVENTO_HIST',
                'plantilla_mensaje' => 'Mensaje',
                'activo' => true,
            ]
        );

        $destinatario = User::factory()->create();

        return AvisoEnviado::create(array_merge([
            'tipo_aviso_id' => $tipoAviso->id,
            'entidad_relacionada' => 'Marca',
            'entidad_id' => 1,
            'destinatario_user_id' => $destinatario->id,
            'canal' => AvisoEnviado::CANAL_IN_APP,
            'fecha_envio' => now(),
            'estatus_envio' => AvisoEnviado::ESTATUS_ENVIADO,
            'leido' => false,
        ], $overrides));
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/avisos-historial')->assertForbidden();
    }

    public function test_lists_avisos_enviados(): void
    {
        $this->actingAs($this->actingUser());
        $this->avisoEnviado();

        Livewire::test(Historial::class)
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    public function test_filters_by_canal(): void
    {
        $this->actingAs($this->actingUser());
        $this->avisoEnviado(['canal' => AvisoEnviado::CANAL_CORREO, 'leido' => null]);
        $this->avisoEnviado(['canal' => AvisoEnviado::CANAL_IN_APP]);

        Livewire::test(Historial::class)
            ->set('canalFilter', 'correo')
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->canal === 'correo');
    }

    public function test_filters_by_estatus(): void
    {
        $this->actingAs($this->actingUser());
        $this->avisoEnviado(['estatus_envio' => AvisoEnviado::ESTATUS_ENVIADO]);
        $this->avisoEnviado(['estatus_envio' => AvisoEnviado::ESTATUS_FALLIDO]);

        Livewire::test(Historial::class)
            ->set('estatusFilter', 'fallido')
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->estatus_envio === 'fallido');
    }

    public function test_filters_by_tipo_aviso(): void
    {
        $this->actingAs($this->actingUser());
        $unoAviso = $this->avisoEnviado(['codigo' => 'EVENTO_UNO']);
        $this->avisoEnviado(['codigo' => 'EVENTO_DOS']);

        Livewire::test(Historial::class)
            ->set('tipoAvisoFilter', (string) $unoAviso->tipo_aviso_id)
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    public function test_filters_by_date_range(): void
    {
        $this->actingAs($this->actingUser());
        $this->avisoEnviado(['fecha_envio' => now()->subDays(10)]);
        $this->avisoEnviado(['fecha_envio' => now()]);

        Livewire::test(Historial::class)
            ->set('fechaDesde', now()->subDays(1)->format('Y-m-d'))
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
    }

    /**
     * Solo lectura — no hay `create`/`edit`/`delete`/`save` en el componente.
     */
    public function test_component_has_no_write_methods(): void
    {
        $methods = get_class_methods(Historial::class);

        foreach (['create', 'edit', 'save', 'delete', 'toggleActivo'] as $writeMethod) {
            $this->assertNotContains($writeMethod, $methods);
        }
    }
}
