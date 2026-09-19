<?php

namespace Modules\MesaServicio\Tests\Feature\Dashboards;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MesaServicio\Livewire\Dashboards\Ejecutivo;
use Modules\MesaServicio\Models\SdpSlaDefinition;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EjecutivoTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Dashboards',
            'name' => 'Dashboard Ejecutivo',
            'slug' => 'mesaservicio-dashboard-ejecutivo',
            'route_name' => 'mesaservicio.dashboards.ejecutivo',
            'permission_name' => 'screens.mesaservicio-dashboard-ejecutivo.manage',
            'icon' => 'presentation-chart-line',
            'order' => 7,
        ]);

        $role = Role::findOrCreate('Rol Dashboard Ejecutivo test', 'web');
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

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/dashboards/ejecutivo')->assertForbidden();
    }

    public function test_a_user_with_the_permission_can_see_the_screen(): void
    {
        $this->actingAs($this->actingUser());

        $this->get('/mesa-servicio/dashboards/ejecutivo')->assertOk();

        Livewire::test(Ejecutivo::class)->assertOk();
    }

    public function test_it_shows_the_help_button_with_its_pdf_route(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->assertSee(route('mesaservicio.ayuda.pdf', 'dashboard-ejecutivo'), escape: false);
    }

    public function test_it_defaults_the_date_range_to_year_to_date(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->assertSet('desde', now()->startOfYear()->toDateString())
            ->assertSet('hasta', now()->toDateString());
    }

    public function test_it_counts_total_tickets_and_completados_in_the_range(): void
    {
        $completado = $this->estado(SdpTicketStatus::TIPO_COMPLETADO);
        $enCurso = $this->estado(SdpTicketStatus::TIPO_EN_CURSO);

        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now()->subDays(5), 'sdp_ticket_status_id' => $completado->id]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now()->subDays(4), 'sdp_ticket_status_id' => $enCurso->id]);
        // Fuera del rango (antes del inicio de año).
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now()->subYears(2), 'sdp_ticket_status_id' => $completado->id]);

        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->assertViewHas('totalTickets', 2)
            ->assertViewHas('completados', 1);
    }

    public function test_total_tickets_includes_combinados_but_sla_pct_excludes_them(): void
    {
        SdpSlaDefinition::create([
            'nombre' => 'Default', 'prioridad' => null,
            'tiempo_resolucion_minutos' => 60, 'activo' => true,
        ]);

        // Cumple SLA, no combinado.
        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now()->subDays(10),
            'resolved_time' => now()->subDays(10)->addMinutes(30),
        ]);
        // No cumple SLA, no combinado.
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now()->subDays(9),
            'resolved_time' => now()->subDays(9)->addMinutes(120),
        ]);
        // Combinado — cuenta en el total, pero se excluye del cálculo de SLA
        // aunque "cumpliría" de sobra (resuelto en 5 min).
        SdpTicket::create([
            'sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now()->subDays(8),
            'resolved_time' => now()->subDays(8)->addMinutes(5),
            'combinado_con_display_id' => '9999',
        ]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Ejecutivo::class)
            ->assertViewHas('totalTickets', 3)
            ->assertViewHas('combinados', 1);

        // Solo tk-1 y tk-2 son evaluables: 1 de 2 cumple = 50%.
        $this->assertSame(50.0, $component->viewData('pctSlaCumplido'));
    }

    public function test_it_calculates_the_median_resolution_time_in_hours(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now()->subDays(10), 'resolved_time' => now()->subDays(10)->addHours(2)]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now()->subDays(9), 'resolved_time' => now()->subDays(9)->addHours(4)]);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now()->subDays(8), 'resolved_time' => now()->subDays(8)->addHours(6)]);

        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)->assertViewHas('medianaResolucionHoras', 4.0);
    }

    public function test_it_counts_tickets_by_tipo_solicitud(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now(), 'tipo_solicitud' => 'Incidente']);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now(), 'tipo_solicitud' => 'Incidente']);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now(), 'tipo_solicitud' => 'Solicitud']);
        SdpTicket::create(['sdp_id' => 'tk-4', 'asunto' => 'D', 'created_time' => now(), 'tipo_solicitud' => 'Requerimiento']);

        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->assertViewHas('incidentes', 2)
            ->assertViewHas('solicitudes', 1)
            ->assertViewHas('requerimientos', 1);
    }

    public function test_nivel_distribucion_groups_null_as_sin_nivel(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now(), 'nivel' => '1. Mesa de Ayuda']);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now(), 'nivel' => '1. Mesa de Ayuda']);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now(), 'nivel' => null]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Ejecutivo::class);
        $distribucion = $component->viewData('nivelDistribucion')->keyBy('etiqueta');

        $this->assertSame(2, $distribucion['1. Mesa de Ayuda']['total']);
        $this->assertSame(1, $distribucion['Sin nivel']['total']);

        $component->assertSee('Sin nivel');
    }

    public function test_hallazgos_reports_the_dominant_category_and_combinados_percentage(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now(), 'categoria' => 'Hardware']);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now(), 'categoria' => 'Hardware']);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now(), 'categoria' => 'Hardware']);
        SdpTicket::create(['sdp_id' => 'tk-4', 'asunto' => 'D', 'created_time' => now(), 'categoria' => 'Software', 'combinado_con_display_id' => '9999']);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Ejecutivo::class);
        $hallazgos = collect($component->viewData('hallazgos'));

        $this->assertTrue($hallazgos->contains(fn (array $h) => str_contains($h['texto'], 'Hardware') && str_contains($h['texto'], '75%')));
        $this->assertTrue($hallazgos->contains(fn (array $h) => $h['titulo'] === 'Tickets combinados' && str_contains($h['texto'], '25%')));
    }

    public function test_hallazgos_omits_rules_that_do_not_apply_to_a_short_range(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now()]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Ejecutivo::class)
            ->set('desde', now()->toDateString())
            ->set('hasta', now()->toDateString());

        $hallazgos = collect($component->viewData('hallazgos'));

        // Rango de un solo día: ningún hallazgo de "mes con más tickets" ni
        // de mejor/peor mes de SLA debe aparecer (requieren 2+ meses).
        $this->assertFalse($hallazgos->contains(fn (array $h) => $h['titulo'] === 'Mes con más tickets creados'));
        $this->assertFalse($hallazgos->contains(fn (array $h) => $h['titulo'] === 'Mejor mes de cumplimiento de SLA'));
    }

    public function test_resumen_mensual_includes_a_recalculated_total_row(): void
    {
        $completado = $this->estado(SdpTicketStatus::TIPO_COMPLETADO);

        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'A', 'created_time' => now()->subMonth(), 'sdp_ticket_status_id' => $completado->id]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'B', 'created_time' => now(), 'sdp_ticket_status_id' => $completado->id]);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'C', 'created_time' => now()]);

        $this->actingAs($this->actingUser());

        $component = Livewire::test(Ejecutivo::class);
        $resumen = $component->viewData('resumenMensual');

        $filaTotal = $resumen->firstWhere('esTotal', true);

        $this->assertNotNull($filaTotal);
        $this->assertSame('Total', $filaTotal['etiqueta']);
        $this->assertSame(3, $filaTotal['total']);
        $this->assertSame(2, $filaTotal['completados']);

        $component->assertSee('Total');
    }

    public function test_date_range_filters_out_tickets_outside_the_range(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Dentro', 'created_time' => now()]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'Fuera', 'created_time' => now()->subYears(3)]);

        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->set('desde', now()->startOfMonth()->toDateString())
            ->set('hasta', now()->toDateString())
            ->assertViewHas('totalTickets', 1);
    }

    public function test_it_defaults_tipo_periodo_to_rango_and_mes_ejercicio_to_the_current_month(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->assertSet('tipoPeriodo', 'rango')
            ->assertSet('mes', now()->month)
            ->assertSet('ejercicio', now()->year);
    }

    public function test_tipo_periodo_mes_acota_al_mes_y_anio_elegidos(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Dentro del mes', 'created_time' => now()->subMonth()->startOfMonth()->addDays(3)]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'Mes actual', 'created_time' => now()]);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'Otro año', 'created_time' => now()->subMonth()->subYear()]);

        $this->actingAs($this->actingUser());

        $mesPasado = now()->subMonth();

        Livewire::test(Ejecutivo::class)
            ->set('tipoPeriodo', 'mes')
            ->set('mes', $mesPasado->month)
            ->set('ejercicio', $mesPasado->year)
            ->assertViewHas('totalTickets', 1);
    }

    public function test_tipo_periodo_ejercicio_acota_al_anio_calendario_completo(): void
    {
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Enero de este año', 'created_time' => now()->startOfYear()]);
        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'Diciembre de este año', 'created_time' => now()->endOfYear()]);
        SdpTicket::create(['sdp_id' => 'tk-3', 'asunto' => 'Año pasado', 'created_time' => now()->subYear()]);

        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->set('tipoPeriodo', 'ejercicio')
            ->set('ejercicio', now()->year)
            ->assertViewHas('totalTickets', 2);
    }

    public function test_resumen_periodo_shows_the_active_filter_for_each_mode(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->set('tipoPeriodo', 'mes')
            ->set('mes', 9)
            ->set('ejercicio', 2026)
            ->assertViewHas('resumenPeriodo', 'Septiembre 2026');

        Livewire::test(Ejecutivo::class)
            ->set('tipoPeriodo', 'ejercicio')
            ->set('ejercicio', 2026)
            ->assertViewHas('resumenPeriodo', 'Ejercicio 2026');
    }

    public function test_aplicar_filtro_dispatches_the_close_event_for_the_slide_over(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Ejecutivo::class)
            ->call('aplicarFiltro')
            ->assertDispatched('close-filtros-periodo');
    }
}
