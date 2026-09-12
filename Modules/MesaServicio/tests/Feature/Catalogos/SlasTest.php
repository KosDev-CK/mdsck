<?php

namespace Modules\MesaServicio\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Catalogos\Slas;
use Modules\MesaServicio\Models\SdpSlaDefinition;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SlasTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Cumplimiento de SLA',
            'slug' => 'mesaservicio-slas',
            'route_name' => 'mesaservicio.slas.index',
            'permission_name' => 'screens.mesaservicio-slas.manage',
            'icon' => 'clock',
            'order' => 4,
        ]);

        $role = Role::findOrCreate('Rol SLAs test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/slas')->assertForbidden();
    }

    public function test_it_can_create_a_definition_with_a_priority(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Slas::class)
            ->set('newNombre', 'SLA Alta')
            ->set('newPrioridad', 'Alta')
            ->set('newTiempoPrimeraRespuesta', 60)
            ->set('newTiempoResolucion', 480)
            ->call('addDefinicion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sdp_sla_definitions', [
            'nombre' => 'SLA Alta',
            'prioridad' => 'Alta',
            'tiempo_primera_respuesta_minutos' => 60,
            'tiempo_resolucion_minutos' => 480,
            'activo' => true,
        ]);
    }

    public function test_it_can_create_a_catchall_definition_without_a_priority(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Slas::class)
            ->set('newNombre', 'SLA por defecto')
            ->set('newPrioridad', '')
            ->set('newTiempoResolucion', 1440)
            ->call('addDefinicion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sdp_sla_definitions', [
            'nombre' => 'SLA por defecto',
            'prioridad' => null,
            'tiempo_resolucion_minutos' => 1440,
        ]);
    }

    public function test_it_rejects_a_duplicate_name(): void
    {
        SdpSlaDefinition::create(['nombre' => 'SLA Alta', 'prioridad' => 'Alta', 'tiempo_resolucion_minutos' => 480, 'activo' => true]);

        $this->actingAs($this->actingUser());

        Livewire::test(Slas::class)
            ->set('newNombre', 'SLA Alta')
            ->set('newTiempoResolucion', 100)
            ->call('addDefinicion')
            ->assertHasErrors('newNombre');
    }

    public function test_it_rejects_a_definition_without_any_time(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Slas::class)
            ->set('newNombre', 'SLA Sin Tiempos')
            ->call('addDefinicion')
            ->assertHasErrors(['newTiempoPrimeraRespuesta', 'newTiempoResolucion']);

        $this->assertDatabaseMissing('sdp_sla_definitions', ['nombre' => 'SLA Sin Tiempos']);
    }

    public function test_it_can_edit_an_existing_definition(): void
    {
        $definicion = SdpSlaDefinition::create([
            'nombre' => 'SLA Alta', 'prioridad' => 'Alta',
            'tiempo_primera_respuesta_minutos' => 60, 'tiempo_resolucion_minutos' => 480, 'activo' => true,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Slas::class)
            ->call('edit', $definicion->id)
            ->assertSet('editNombre', 'SLA Alta')
            ->set('editNombre', 'SLA Alta Renombrada')
            ->set('editTiempoPrimeraRespuesta', 30)
            ->call('update')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sdp_sla_definitions', [
            'id' => $definicion->id,
            'nombre' => 'SLA Alta Renombrada',
            'tiempo_primera_respuesta_minutos' => 30,
        ]);
    }

    public function test_it_can_toggle_activo(): void
    {
        $definicion = SdpSlaDefinition::create([
            'nombre' => 'SLA Alta', 'prioridad' => 'Alta', 'tiempo_resolucion_minutos' => 480, 'activo' => true,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Slas::class)->call('toggleActivo', $definicion->id);

        $this->assertDatabaseHas('sdp_sla_definitions', ['id' => $definicion->id, 'activo' => false]);

        Livewire::test(Slas::class)->call('toggleActivo', $definicion->id);

        $this->assertDatabaseHas('sdp_sla_definitions', ['id' => $definicion->id, 'activo' => true]);
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Slas::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'sla'), escape: false);
    }

    /**
     * Cálculo de cumplimiento por técnico y por categoría, sobre una
     * definición de SLA conocida (Alta: 60 min de 1ra respuesta, 480 min de
     * resolución):
     *  - $cumple: dentro del rango, prioridad "Alta", categoría "Hardware",
     *    respondido en 30 min (cumple) y resuelto en 400 min (cumple).
     *  - $incumple: dentro del rango, misma prioridad/categoría, respondido
     *    en 90 min (incumple) y completado (sin resolved_time) en 500 min
     *    (incumple).
     *  - $sinDefinicion: dentro del rango, prioridad "Media" (sin
     *    definición activa que le aplique y sin catch-all sembrado en este
     *    test) — debe quedar EXCLUIDO de ambos cálculos, no contar como
     *    incumplimiento.
     *  - $fueraDeRango: misma prioridad "Alta" pero creado fuera del rango
     *    desde/hasta — debe quedar excluido también.
     *
     * Evaluables/cumplidas esperados para ambas métricas: 2 evaluables,
     * 1 cumplida => 50.0%.
     */
    public function test_it_calculates_compliance_percentages_excluding_tickets_without_applicable_definition_or_out_of_range(): void
    {
        SdpSlaDefinition::create([
            'nombre' => 'SLA Alta',
            'prioridad' => 'Alta',
            'tiempo_primera_respuesta_minutos' => 60,
            'tiempo_resolucion_minutos' => 480,
            'activo' => true,
        ]);

        $tecnico = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        $desde = Carbon::now()->subDays(10);
        $hasta = Carbon::now();

        $creadoDentro = Carbon::now()->subDays(2);

        SdpTicket::create([
            'sdp_id' => 'tk-cumple', 'asunto' => 'Cumple', 'categoria' => 'Hardware',
            'prioridad' => 'Alta', 'sdp_technician_id' => $tecnico->id,
            'created_time' => $creadoDentro,
            'responded_time' => $creadoDentro->copy()->addMinutes(30),
            'resolved_time' => $creadoDentro->copy()->addMinutes(400),
        ]);

        SdpTicket::create([
            'sdp_id' => 'tk-incumple', 'asunto' => 'Incumple', 'categoria' => 'Hardware',
            'prioridad' => 'Alta', 'sdp_technician_id' => $tecnico->id,
            'created_time' => $creadoDentro,
            'responded_time' => $creadoDentro->copy()->addMinutes(90),
            'completed_time' => $creadoDentro->copy()->addMinutes(500),
        ]);

        SdpTicket::create([
            'sdp_id' => 'tk-sin-definicion', 'asunto' => 'Sin definición', 'categoria' => 'Hardware',
            'prioridad' => 'Media', 'sdp_technician_id' => $tecnico->id,
            'created_time' => $creadoDentro,
            'responded_time' => $creadoDentro->copy()->addMinutes(5),
            'resolved_time' => $creadoDentro->copy()->addMinutes(10),
        ]);

        SdpTicket::create([
            'sdp_id' => 'tk-fuera-de-rango', 'asunto' => 'Fuera de rango', 'categoria' => 'Hardware',
            'prioridad' => 'Alta', 'sdp_technician_id' => $tecnico->id,
            'created_time' => Carbon::now()->subDays(20),
            'responded_time' => Carbon::now()->subDays(20)->addMinutes(1),
            'resolved_time' => Carbon::now()->subDays(20)->addMinutes(1),
        ]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Slas::class)
            ->set('desde', $desde->toDateString())
            ->set('hasta', $hasta->toDateString());

        $component->assertViewHas('porTecnico', function ($porTecnico) {
            $fila = $porTecnico->firstWhere('etiqueta', 'Juan Pérez');

            return $fila
                && $fila['primera_respuesta']['evaluables'] === 2
                && $fila['primera_respuesta']['cumplidas'] === 1
                && $fila['primera_respuesta']['pct'] === 50.0
                && $fila['resolucion']['evaluables'] === 2
                && $fila['resolucion']['cumplidas'] === 1
                && $fila['resolucion']['pct'] === 50.0;
        });

        $component->assertViewHas('porCategoria', function ($porCategoria) {
            $fila = $porCategoria->firstWhere('etiqueta', 'Hardware');

            return $fila
                && $fila['primera_respuesta']['evaluables'] === 2
                && $fila['primera_respuesta']['cumplidas'] === 1
                && $fila['resolucion']['evaluables'] === 2
                && $fila['resolucion']['cumplidas'] === 1;
        });
    }
}
