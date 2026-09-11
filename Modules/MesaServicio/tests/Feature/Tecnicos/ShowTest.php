<?php

namespace Modules\MesaServicio\Tests\Feature\Tecnicos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Tecnicos\Show;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Dashboard',
            'slug' => 'mesaservicio-dashboard',
            'route_name' => 'mesaservicio.dashboard.index',
            'permission_name' => 'screens.mesaservicio-dashboard.manage',
            'icon' => 'chart-bar',
            'order' => 0,
        ]);

        $role = Role::findOrCreate('Ficha Tecnico Test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function estado(string $tipo, string $nombre): SdpTicketStatus
    {
        return SdpTicketStatus::create([
            'sdp_id' => fake()->unique()->randomNumber(9),
            'nombre' => $nombre,
            'tipo' => $tipo,
            'activo' => true,
        ]);
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan', 'activo' => true, 'es_nivel_1' => false]);
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get("/mesa-servicio/tecnicos/{$technician->id}")->assertForbidden();
    }

    public function test_it_only_shows_tickets_belonging_to_the_technician(): void
    {
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO, 'Abierto');

        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'correo' => 'juan@example.test', 'activo' => true, 'es_nivel_1' => false]);
        $otro = SdpTechnician::create(['sdp_id' => 't2', 'nombre' => 'Ana Ruiz', 'activo' => true, 'es_nivel_1' => false]);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Ticket de Juan', 'created_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Ticket de Ana', 'created_time' => now(),
            'sdp_technician_id' => $otro->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Show::class, ['tecnico' => $technician])
            ->assertSee('Ticket de Juan')
            ->assertDontSee('Ticket de Ana');
    }

    public function test_it_separates_pendientes_from_atendidos(): void
    {
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO, 'Abierto');
        $completado = $this->estado(SdpTicketStatus::TIPO_COMPLETADO, 'Completado');

        $technician = SdpTechnician::create(['sdp_id' => 't1', 'nombre' => 'Juan Pérez', 'activo' => true, 'es_nivel_1' => false]);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Pendiente de Juan', 'created_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $enCurso->id, 'estado_nombre' => 'Abierto',
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Atendido de Juan', 'created_time' => now(), 'completed_time' => now(),
            'sdp_technician_id' => $technician->id, 'sdp_ticket_status_id' => $completado->id, 'estado_nombre' => 'Completado',
        ]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Show::class, ['tecnico' => $technician]);

        $component->assertSee('Pendiente de Juan')->assertSee('Atendido de Juan');
        $component->assertViewHas('pendientes', fn ($pendientes) => $pendientes->pluck('asunto')->all() === ['Pendiente de Juan']);
        $component->assertViewHas('atendidos', fn ($atendidos) => $atendidos->pluck('asunto')->all() === ['Atendido de Juan']);
    }
}
