<?php

namespace Modules\GestionTI\Tests\Feature\Avisos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Avisos\TiposAviso;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TiposAvisoTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'General',
            'name' => 'Configuración de Avisos',
            'slug' => 'gestionti-tipos-aviso',
            'route_name' => 'gestionti.tipos-aviso.index',
            'permission_name' => 'screens.gestionti-tipos-aviso.manage',
            'icon' => 'bell-alert',
            'order' => 2,
        ]);

        $role = Role::findOrCreate('Administrador', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/tipos-aviso')->assertForbidden();
    }

    public function test_can_create_a_tipo_aviso_with_destinatarios(): void
    {
        $this->actingAs($this->actingUser());
        $validador = Validador::create(['nombre' => 'Juan', 'activo' => true]);

        Livewire::test(TiposAviso::class)
            ->call('create')
            ->assertSet('form.activo', true)
            ->set('form.codigo', 'EVENTO_X')
            ->set('form.descripcion', 'Descripción de prueba')
            ->set('form.entidad_relacionada', 'Marca')
            ->set('form.evento_disparador', 'EVENTO_X')
            ->set('form.plantilla_mensaje', 'Hola {{nombre}}')
            ->call('addDestinatario')
            ->set('destinatarios.0.tipo_destinatario', 'validador_especifico')
            ->set('destinatarios.0.validador_id', $validador->id)
            ->call('addDestinatario')
            ->set('destinatarios.1.tipo_destinatario', 'dinamico_solicitante')
            ->call('save')
            ->assertHasNoErrors();

        $tipoAviso = TipoAviso::where('codigo', 'EVENTO_X')->firstOrFail();
        $this->assertCount(2, $tipoAviso->destinatarios);
        $this->assertSame('validador_especifico', $tipoAviso->destinatarios[0]->tipo_destinatario);
        $this->assertSame($validador->id, $tipoAviso->destinatarios[0]->validador_id);
        $this->assertSame('dinamico_solicitante', $tipoAviso->destinatarios[1]->tipo_destinatario);
    }

    public function test_validation_requires_core_fields(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(TiposAviso::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors([
                'form.codigo' => 'required',
                'form.descripcion' => 'required',
                'form.entidad_relacionada' => 'required',
                'form.evento_disparador' => 'required',
                'form.plantilla_mensaje' => 'required',
            ]);
    }

    public function test_codigo_and_evento_disparador_must_be_unique(): void
    {
        $this->actingAs($this->actingUser());

        TipoAviso::create([
            'codigo' => 'EVENTO_UNICO',
            'descripcion' => 'Existente',
            'entidad_relacionada' => 'Marca',
            'evento_disparador' => 'EVENTO_UNICO',
            'plantilla_mensaje' => 'Mensaje',
            'activo' => true,
        ]);

        Livewire::test(TiposAviso::class)
            ->call('create')
            ->set('form.codigo', 'EVENTO_UNICO')
            ->set('form.descripcion', 'Otro')
            ->set('form.entidad_relacionada', 'Marca')
            ->set('form.evento_disparador', 'EVENTO_UNICO')
            ->set('form.plantilla_mensaje', 'Mensaje')
            ->call('save')
            ->assertHasErrors(['form.codigo', 'form.evento_disparador']);
    }

    public function test_can_edit_an_existing_tipo_aviso_and_replace_destinatarios(): void
    {
        $this->actingAs($this->actingUser());

        $tipoAviso = TipoAviso::create([
            'codigo' => 'EVENTO_EDITAR',
            'descripcion' => 'Original',
            'entidad_relacionada' => 'Marca',
            'evento_disparador' => 'EVENTO_EDITAR',
            'plantilla_mensaje' => 'Mensaje original',
            'activo' => true,
        ]);
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'dinamico_solicitante']);

        Livewire::test(TiposAviso::class)
            ->call('edit', $tipoAviso->id)
            ->assertSet('form.descripcion', 'Original')
            ->assertCount('destinatarios', 1)
            ->set('form.descripcion', 'Actualizada')
            ->call('removeDestinatario', 0)
            ->call('addDestinatario')
            ->set('destinatarios.0.tipo_destinatario', 'rol_fijo')
            ->set('destinatarios.0.rol_nombre', 'Compras')
            ->call('save')
            ->assertHasNoErrors();

        $tipoAviso->refresh();
        $this->assertSame('Actualizada', $tipoAviso->descripcion);
        $this->assertCount(1, $tipoAviso->destinatarios);
        $this->assertSame('rol_fijo', $tipoAviso->destinatarios[0]->tipo_destinatario);
        $this->assertSame('Compras', $tipoAviso->destinatarios[0]->rol_nombre);
    }

    public function test_removing_a_destinatario_row_works(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(TiposAviso::class)
            ->call('create')
            ->call('addDestinatario')
            ->call('addDestinatario')
            ->assertCount('destinatarios', 2)
            ->call('removeDestinatario', 0)
            ->assertCount('destinatarios', 1);
    }

    /**
     * Los selects condicionales de rol/validador mandan '' cuando no
     * aplican al tipo de destinatario elegido — no debe tronar al guardar
     * (mismo bug/fix documentado repetidas veces en este módulo).
     */
    public function test_empty_string_role_and_validador_are_normalized_to_null(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(TiposAviso::class)
            ->call('create')
            ->set('form.codigo', 'EVENTO_NULL')
            ->set('form.descripcion', 'Descripción')
            ->set('form.entidad_relacionada', 'Marca')
            ->set('form.evento_disparador', 'EVENTO_NULL')
            ->set('form.plantilla_mensaje', 'Mensaje')
            ->call('addDestinatario')
            ->set('destinatarios.0.tipo_destinatario', 'dinamico_responsable')
            ->set('destinatarios.0.rol_nombre', '')
            ->set('destinatarios.0.validador_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $tipoAviso = TipoAviso::where('codigo', 'EVENTO_NULL')->firstOrFail();
        $this->assertNull($tipoAviso->destinatarios[0]->rol_nombre);
        $this->assertNull($tipoAviso->destinatarios[0]->validador_id);
    }

    public function test_can_toggle_activo(): void
    {
        $this->actingAs($this->actingUser());

        $tipoAviso = TipoAviso::create([
            'codigo' => 'EVENTO_TOGGLE',
            'descripcion' => 'Descripción',
            'entidad_relacionada' => 'Marca',
            'evento_disparador' => 'EVENTO_TOGGLE',
            'plantilla_mensaje' => 'Mensaje',
            'activo' => true,
        ]);

        Livewire::test(TiposAviso::class)->call('toggleActivo', $tipoAviso->id);

        $this->assertFalse($tipoAviso->fresh()->activo);
    }

    public function test_seeding_creates_the_8_expected_tipos_aviso(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $codigos = TipoAviso::pluck('codigo')->sort()->values()->all();

        $this->assertEquals([
            'MANTENIMIENTO_PROXIMO_VENCER',
            'MANTENIMIENTO_VENCIDO',
            'PRESUPUESTO_COSTO_PENDIENTE',
            'PRESUPUESTO_LISTO_PARA_AUTORIZAR',
            'PROYECTO_AUTORIZADO',
            'SIC_AUTORIZADA',
            'SIC_RECHAZADA',
            'STOCK_BAJO_MINIMO',
        ], $codigos);

        // SIC_LIGA_POR_EXPIRAR se difiere a Fase 5 — no debe sembrarse.
        $this->assertDatabaseMissing('tipos_aviso', ['codigo' => 'SIC_LIGA_POR_EXPIRAR']);
    }
}
