<?php

namespace Modules\MesaServicio\Tests\Feature;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Dashboard;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
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

        $role = Role::findOrCreate('Dashboard Mesa de Servicio Test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function estado(string $tipo): SdpTicketStatus
    {
        return SdpTicketStatus::create([
            'sdp_id' => fake()->unique()->randomNumber(9),
            'nombre' => $tipo === SdpTicketStatus::TIPO_COMPLETADO ? 'Completado' : 'Abierto',
            'tipo' => $tipo,
            'activo' => true,
        ]);
    }

    private function tecnico(bool $nivel1 = false): SdpTechnician
    {
        return SdpTechnician::create([
            'sdp_id' => (string) fake()->unique()->randomNumber(9),
            'nombre' => fake()->name(),
            'activo' => true,
            'es_nivel_1' => $nivel1,
        ]);
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio')->assertForbidden();
    }

    public function test_it_counts_tickets_created_today(): void
    {
        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Uno', 'created_time' => now(),
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Dos', 'created_time' => now()->subDays(3),
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Dashboard::class)->assertViewHas('creadosHoy', 1);
    }

    public function test_it_counts_tickets_completed_today(): void
    {
        $completado = $this->estado(SdpTicketStatus::TIPO_COMPLETADO);
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO);

        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Completado hoy', 'created_time' => now()->subDays(2),
            'completed_time' => now(), 'sdp_ticket_status_id' => $completado->id,
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Completado ayer', 'created_time' => now()->subDays(5),
            'completed_time' => now()->subDay(), 'sdp_ticket_status_id' => $completado->id,
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-3', 'asunto' => 'En curso', 'created_time' => now(),
            'sdp_ticket_status_id' => $enCurso->id,
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Dashboard::class)->assertViewHas('atendidosHoy', 1);
    }

    public function test_it_counts_pending_tickets_by_status_type(): void
    {
        $completado = $this->estado(SdpTicketStatus::TIPO_COMPLETADO);
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO);

        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now(), 'sdp_ticket_status_id' => $enCurso->id]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now(), 'sdp_ticket_status_id' => $enCurso->id]);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now(), 'sdp_ticket_status_id' => $completado->id]);

        $this->actingAs($this->actingUser());

        Livewire::test(Dashboard::class)->assertViewHas('pendientes', 2);
    }

    public function test_it_filters_counts_by_nivel_1_technicians_only(): void
    {
        $nivel1 = $this->tecnico(nivel1: true);
        $otro = $this->tecnico(nivel1: false);

        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now(), 'sdp_technician_id' => $nivel1->id]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now(), 'sdp_technician_id' => $otro->id]);

        $this->actingAs($this->actingUser());

        Livewire::test(Dashboard::class)->assertViewHas('creadosHoy', 2);

        Livewire::test(Dashboard::class)
            ->set('soloNivel1', true)
            ->assertViewHas('creadosHoy', 1);
    }

    public function test_it_flags_first_response_sla_breaches_older_than_ten_minutes_without_response(): void
    {
        // Vencido: creado hace 20 min, sin responded_time.
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Vencido', 'created_time' => now()->subMinutes(20)]);

        // No vencido: creado hace 20 min, PERO ya tiene responded_time.
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Ya respondido', 'created_time' => now()->subMinutes(20),
            'responded_time' => now()->subMinutes(10),
        ]);

        // No vencido: creado hace solo 5 min, todavía dentro de la ventana.
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'Reciente', 'created_time' => now()->subMinutes(5)]);

        $this->actingAs($this->actingUser());

        Livewire::test(Dashboard::class)->assertViewHas('slaVencidosCount', 1);
    }

    public function test_hallazgos_del_dia_section_renders_top_categorias_without_historic_data(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'categoria' => 'Red', 'created_time' => now()]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'categoria' => 'Red', 'created_time' => now()]);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'categoria' => 'Hardware', 'created_time' => now()]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Dashboard::class);

        $component->assertSee('Hallazgos del día')
            ->assertSee('Categorías más frecuentes hoy')
            ->assertSee('Red')
            ->assertSee('Hardware')
            // Sin histórico sembrado, debe avisar en vez de intentar comparar.
            ->assertSee('Historial insuficiente');

        $this->assertSame(['Red', 'Hardware'], $component->viewData('topCategorias')->pluck('categoria')->all());
        $this->assertFalse($component->viewData('historicoSuficiente'));
        $this->assertTrue($component->viewData('picos')->isEmpty());
    }

    public function test_hallazgos_del_dia_section_renders_detected_spike(): void
    {
        // 10 días de histórico, 2 tickets/día de "Red" (promedio 2).
        $sdpIdSeq = 1;
        for ($dia = 1; $dia <= 10; $dia++) {
            for ($j = 0; $j < 2; $j++) {
                SdpTicket::create([
                    'sdp_id' => 'hist-'.$sdpIdSeq++,
                    'asunto' => 'Histórico',
                    'categoria' => 'Red',
                    'created_time' => now()->subDays($dia),
                ]);
            }
        }

        // Hoy: 6 tickets de Red (pico, >= 1.5 * 2).
        for ($j = 0; $j < 6; $j++) {
            SdpTicket::create([
                'sdp_id' => 'hoy-'.$j,
                'asunto' => 'Hoy',
                'categoria' => 'Red',
                'created_time' => now(),
            ]);
        }

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Dashboard::class);

        $component->assertSee('Picos detectados')
            ->assertSee('Red')
            ->assertSee('6 tickets hoy');

        $picos = $component->viewData('picos');
        $this->assertCount(1, $picos);
        $this->assertSame('Red', $picos->first()['categoria']);
    }

    public function test_sincronizar_button_runs_the_sync_command_and_flashes_a_status_message(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => Http::response([
                'requests' => [[
                    'id' => 'tk-boton', 'display_id' => '999', 'subject' => 'Vía botón',
                    'created_time' => ['value' => '1700000000000'],
                    'last_updated_time' => ['value' => '1700000000000'],
                ]],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->actingAs($this->actingUser());

        Livewire::test(Dashboard::class)
            ->call('sincronizar')
            ->assertSet('sincronizando', false);

        $this->assertSame(1, SdpTicket::count());
    }
}
