<?php

namespace Modules\MesaServicio\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\MesaServicio\Console\Commands\MonthlyCloseCommand;
use Modules\MesaServicio\Models\SdpReport;
use Modules\MesaServicio\Models\SdpReportRecipientEmail;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Modules\MesaServicio\Notifications\CierreMensualGeneradoNotification;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonthlyCloseCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function tecnico(string $nombre): SdpTechnician
    {
        return SdpTechnician::create([
            'sdp_id' => (string) fake()->unique()->randomNumber(9),
            'nombre' => $nombre,
            'activo' => true,
            'es_nivel_1' => false,
        ]);
    }

    private function estado(string $nombre, string $tipo): SdpTicketStatus
    {
        return SdpTicketStatus::create([
            'sdp_id' => fake()->unique()->randomNumber(9),
            'nombre' => $nombre,
            'tipo' => $tipo,
            'activo' => true,
        ]);
    }

    private function ticket(array $attributes): SdpTicket
    {
        return SdpTicket::create(array_merge([
            'sdp_id' => (string) fake()->unique()->randomNumber(9),
            'asunto' => 'Ticket de prueba',
        ], $attributes));
    }

    public function test_it_groups_last_months_tickets_by_technician_and_status_without_filtering_by_status(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $juan = $this->tecnico('Juan Pérez');
        $ana = $this->tecnico('Ana López');
        $abierto = $this->estado('Abierto', SdpTicketStatus::TIPO_EN_CURSO);
        $cerrado = $this->estado('Cerrado', SdpTicketStatus::TIPO_COMPLETADO);

        // Ticket todavía abierto creado el mes pasado — debe contar igual.
        $this->ticket([
            'created_time' => $mesPasado->copy()->addDays(2),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $abierto->id, 'estado_nombre' => 'Abierto',
            'display_id' => '100',
        ]);
        $this->ticket([
            'created_time' => $mesPasado->copy()->addDays(5),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $cerrado->id, 'estado_nombre' => 'Cerrado',
            'display_id' => '101',
        ]);
        $this->ticket([
            'created_time' => $mesPasado->copy()->addDays(10),
            'sdp_technician_id' => $ana->id, 'sdp_ticket_status_id' => $abierto->id, 'estado_nombre' => 'Abierto',
            'display_id' => '102',
        ]);
        // Ticket de otro mes (este mes) — no debe contarse.
        $this->ticket([
            'created_time' => now(),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $abierto->id, 'estado_nombre' => 'Abierto',
            'display_id' => '999',
        ]);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $report = SdpReport::first();
        $this->assertNotNull($report);
        $this->assertSame(SdpReport::TIPO_MENSUAL, $report->tipo);
        $this->assertSame($mesPasado->toDateString(), $report->periodo->toDateString());
        $this->assertSame(3, $report->resumen_metricas['total']);

        $porTecnico = collect($report->resumen_metricas['por_tecnico'])->keyBy('tecnico');
        $this->assertSame(2, $porTecnico['Juan Pérez']['total']);
        $this->assertSame(1, $porTecnico['Ana López']['total']);

        $grupos = collect($report->resumen_metricas['por_estado']['grupos'])->keyBy('tipo');
        $estadosEnCurso = collect($grupos[SdpTicketStatus::TIPO_EN_CURSO]['estados'])->keyBy('nombre');
        $estadosCompletado = collect($grupos[SdpTicketStatus::TIPO_COMPLETADO]['estados'])->keyBy('nombre');
        $this->assertSame(2, $estadosEnCurso['Abierto']['cantidad']);
        $this->assertSame(1, $estadosCompletado['Cerrado']['cantidad']);

        Storage::disk('local')->assertExists($report->ruta_archivo);

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($report->ruta_archivo));
        $this->assertSame(['Resumen', 'Detalle'], array_map(fn ($sheet) => $sheet->getTitle(), $spreadsheet->getAllSheets()));
        $this->assertSame(3, $spreadsheet->getSheetByName('Detalle')->getHighestRow() - 1);
    }

    public function test_escalado_a_proveedor_and_combinado_compose_correctly_with_the_monthly_specific_metrics(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $juan = $this->tecnico('Juan Pérez');
        $escalado = $this->estado('Escalado a Proveedor', SdpTicketStatus::TIPO_EN_CURSO);
        $abierto = $this->estado('Abierto', SdpTicketStatus::TIPO_EN_CURSO);
        // Presente en el catálogo activo pero sin ningún ticket este mes —
        // debe seguir apareciendo con cantidad 0 en el árbol.
        $this->estado('Asignado', SdpTicketStatus::TIPO_EN_CURSO);
        $combinado = $this->estado('Combinado', SdpTicketStatus::TIPO_COMPLETADO);

        $this->ticket([
            'created_time' => $mesPasado->copy()->addDay(),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $escalado->id,
            'estado_nombre' => 'Escalado a Proveedor', 'display_id' => '100',
        ]);
        $this->ticket([
            'created_time' => $mesPasado->copy()->addDays(2),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $abierto->id,
            'estado_nombre' => 'Abierto', 'display_id' => '101',
        ]);
        $this->ticket([
            'created_time' => $mesPasado->copy()->addDays(3),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $combinado->id,
            'estado_nombre' => 'Combinado', 'combinado_con_display_id' => '999', 'display_id' => '102',
        ]);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $metricas = SdpReport::first()->resumen_metricas;

        // Los 3 tickets caen bajo Juan: 1 escalado, 1 en curso, 1 completado
        // (Combinado) — ninguno duplicado entre columnas.
        $juanRow = collect($metricas['por_tecnico'])->firstWhere('tecnico', 'Juan Pérez');
        $this->assertSame(1, $juanRow['en_curso']);
        $this->assertSame(1, $juanRow['escalado_a_proveedor']);
        $this->assertSame(1, $juanRow['completados']);
        $this->assertSame(3, $juanRow['total']);

        $grupos = collect($metricas['por_estado']['grupos'])->keyBy('tipo');
        $estadosEnCurso = collect($grupos[SdpTicketStatus::TIPO_EN_CURSO]['estados'])->keyBy('nombre');
        $this->assertSame(0, $estadosEnCurso['Asignado']['cantidad']);
        $this->assertSame(1, $estadosEnCurso['Abierto']['cantidad']);
        $this->assertSame(1, $estadosEnCurso['Escalado a Proveedor']['cantidad']);
        $this->assertSame(0, $metricas['por_estado']['sin_catalogar']);

        // Las métricas propias del mensual (folios combinados) conviven sin
        // problema con la nueva forma de por_estado/por_tecnico dentro del
        // mismo array $resumen.
        $this->assertSame(100, $metricas['folio_min']);
        $this->assertSame(102, $metricas['folio_max']);
        $this->assertSame(3, $metricas['conteo_real']);
    }

    public function test_it_calculates_the_combined_folios_metric_correctly(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();

        // Folios 100, 101, 102 pero solo 2 tickets reales -> SDP fusionó uno.
        $this->ticket(['created_time' => $mesPasado->copy()->addDay(), 'display_id' => '100']);
        $this->ticket(['created_time' => $mesPasado->copy()->addDays(2), 'display_id' => '102']);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $metricas = SdpReport::first()->resumen_metricas;
        $this->assertSame(100, $metricas['folio_min']);
        $this->assertSame(102, $metricas['folio_max']);
        $this->assertSame(2, $metricas['conteo_real']);
        // (102 - 100) - 2 = 0
        $this->assertSame(0, $metricas['estimado_combinados']);
    }

    public function test_the_combined_folios_estimate_can_be_negative_and_is_stored_as_is(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();

        // Folios consecutivos 100,101,102 con 3 tickets reales -> rango(2) - conteo(3) = -1.
        $this->ticket(['created_time' => $mesPasado->copy()->addDay(), 'display_id' => '100']);
        $this->ticket(['created_time' => $mesPasado->copy()->addDays(2), 'display_id' => '101']);
        $this->ticket(['created_time' => $mesPasado->copy()->addDays(3), 'display_id' => '102']);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $metricas = SdpReport::first()->resumen_metricas;
        $this->assertSame(-1, $metricas['estimado_combinados']);
    }

    public function test_null_or_non_numeric_display_ids_are_ignored_without_breaking_the_command(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();

        $this->ticket(['created_time' => $mesPasado->copy()->addDay(), 'display_id' => null]);
        $this->ticket(['created_time' => $mesPasado->copy()->addDays(2), 'display_id' => 'N/A']);
        $this->ticket(['created_time' => $mesPasado->copy()->addDays(3), 'display_id' => '200']);
        $this->ticket(['created_time' => $mesPasado->copy()->addDays(4), 'display_id' => '205']);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $metricas = SdpReport::first()->resumen_metricas;
        $this->assertSame(4, $metricas['total']);
        $this->assertSame(4, $metricas['conteo_real']);
        $this->assertSame(200, $metricas['folio_min']);
        $this->assertSame(205, $metricas['folio_max']);
        // (205 - 200) - 4 = 1
        $this->assertSame(1, $metricas['estimado_combinados']);
    }

    public function test_a_month_without_tickets_yields_a_sensible_metric_without_dividing_by_zero(): void
    {
        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $report = SdpReport::first();
        $this->assertNotNull($report);
        $this->assertSame(0, $report->resumen_metricas['total']);
        $this->assertNull($report->resumen_metricas['folio_min']);
        $this->assertNull($report->resumen_metricas['folio_max']);
        $this->assertSame(0, $report->resumen_metricas['conteo_real']);
        $this->assertNull($report->resumen_metricas['estimado_combinados']);

        Storage::disk('local')->assertExists($report->ruta_archivo);
    }

    public function test_a_month_where_no_ticket_has_a_numeric_display_id_yields_null_metrics(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $this->ticket(['created_time' => $mesPasado->copy()->addDay(), 'display_id' => null]);
        $this->ticket(['created_time' => $mesPasado->copy()->addDays(2), 'display_id' => 'N/A']);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $metricas = SdpReport::first()->resumen_metricas;
        $this->assertSame(2, $metricas['total']);
        $this->assertSame(2, $metricas['conteo_real']);
        $this->assertNull($metricas['folio_min']);
        $this->assertNull($metricas['folio_max']);
        $this->assertNull($metricas['estimado_combinados']);
    }

    public function test_running_the_same_month_twice_updates_the_existing_report_instead_of_duplicating(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $this->ticket(['created_time' => $mesPasado->copy()->addDay(), 'display_id' => '100']);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);
        $this->assertSame(1, SdpReport::first()->resumen_metricas['total']);

        $this->ticket(['created_time' => $mesPasado->copy()->addDays(2), 'display_id' => '101']);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $this->assertSame(1, SdpReport::count());
        $this->assertSame(2, SdpReport::first()->resumen_metricas['total']);
    }

    public function test_a_past_month_can_be_regenerated_manually_via_the_optional_argument(): void
    {
        $mes = today()->subMonths(3)->startOfMonth();
        $this->ticket(['created_time' => $mes->copy()->addDay(), 'display_id' => '50']);

        $this->artisan('sdp:monthly-close', ['mes' => $mes->format('Y-m')])->assertExitCode(0);

        $report = SdpReport::first();
        $this->assertSame($mes->toDateString(), $report->periodo->toDateString());
        $this->assertSame(1, $report->resumen_metricas['total']);
    }

    public function test_an_invalid_month_argument_fails_gracefully(): void
    {
        $this->artisan('sdp:monthly-close', ['mes' => '2026-13'])->assertExitCode(1);
        $this->assertSame(0, SdpReport::count());
    }

    public function test_it_notifies_the_supervisor_role_and_each_recipient_email(): void
    {
        Notification::fake();

        $role = Role::findOrCreate(MonthlyCloseCommand::ROL_SUPERVISOR, 'web');
        $supervisor = User::factory()->create(['is_active' => true]);
        $supervisor->assignRole($role);
        $inactiveSupervisor = User::factory()->create(['is_active' => false]);
        $inactiveSupervisor->assignRole($role);

        SdpReportRecipientEmail::create(['email' => 'suelto1@example.test']);
        SdpReportRecipientEmail::create(['email' => 'suelto2@example.test']);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        Notification::assertSentTo($supervisor, CierreMensualGeneradoNotification::class);
        Notification::assertNotSentTo($inactiveSupervisor, CierreMensualGeneradoNotification::class);
        Notification::assertSentOnDemand(
            CierreMensualGeneradoNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'suelto1@example.test'
        );
        Notification::assertSentOnDemand(
            CierreMensualGeneradoNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'suelto2@example.test'
        );
    }

    public function test_it_does_not_fail_when_there_are_no_recipients_configured(): void
    {
        Notification::fake();

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    // --- Fase 8 (Parte 5) — backlog histórico ---

    public function test_the_historical_backlog_counts_a_still_open_ticket_from_a_prior_year(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $juan = $this->tecnico('Juan Pérez');
        $abierto = $this->estado('Abierto', SdpTicketStatus::TIPO_EN_CURSO);

        // Creado hace más de un año, sigue abierto — debe contar en el
        // backlog histórico del cierre de este mes aunque no se haya creado
        // este mes.
        $this->ticket([
            'created_time' => $mesPasado->copy()->subYear(),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $abierto->id,
            'estado_nombre' => 'Abierto', 'categoria' => 'Oracle', 'display_id' => '1',
        ]);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $metricas = SdpReport::first()->resumen_metricas;
        $this->assertSame(1, $metricas['backlog_historico_total']);
        $this->assertSame(1, $metricas['backlog_historico_por_tecnico']['Juan Pérez']);
        $this->assertSame(1, $metricas['backlog_historico_por_categoria']['Oracle']);
    }

    public function test_the_historical_backlog_excludes_a_prior_month_ticket_already_resolved(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $juan = $this->tecnico('Juan Pérez');
        $cerrado = $this->estado('Cerrado', SdpTicketStatus::TIPO_COMPLETADO);

        // Creado el mes pasado pero ya resuelto — no debe contar en el
        // backlog histórico (sí cuenta en el resumen normal del mes, eso no
        // cambia).
        $this->ticket([
            'created_time' => $mesPasado->copy()->subMonths(2),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $cerrado->id,
            'estado_nombre' => 'Cerrado', 'display_id' => '2',
        ]);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $metricas = SdpReport::first()->resumen_metricas;
        $this->assertSame(0, $metricas['backlog_historico_total']);
        $this->assertSame([], $metricas['backlog_historico_por_tecnico']);
        $this->assertSame([], $metricas['backlog_historico_por_categoria']);
    }

    public function test_the_historical_backlog_treats_combinado_as_resolved_and_excludes_it(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $combinado = $this->estado('Combinado', SdpTicketStatus::TIPO_COMPLETADO);

        $this->ticket([
            'created_time' => $mesPasado->copy()->subYear(),
            'sdp_ticket_status_id' => $combinado->id, 'estado_nombre' => 'Combinado',
            'combinado_con_display_id' => '999', 'display_id' => '3',
        ]);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $this->assertSame(0, SdpReport::first()->resumen_metricas['backlog_historico_total']);
    }

    public function test_the_backlog_block_is_written_to_the_summary_sheet(): void
    {
        $mesPasado = today()->subMonthNoOverflow()->startOfMonth();
        $juan = $this->tecnico('Juan Pérez');
        $abierto = $this->estado('Abierto', SdpTicketStatus::TIPO_EN_CURSO);

        $this->ticket([
            'created_time' => $mesPasado->copy()->subYear(),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $abierto->id,
            'estado_nombre' => 'Abierto', 'categoria' => 'Oracle', 'display_id' => '1',
        ]);

        $this->artisan('sdp:monthly-close')->assertExitCode(0);

        $report = SdpReport::first();
        $spreadsheet = IOFactory::load(Storage::disk('local')->path($report->ruta_archivo));
        $resumen = $spreadsheet->getSheetByName('Resumen');

        $valores = [];
        for ($row = 1; $row <= $resumen->getHighestRow(); $row++) {
            $valores[] = $resumen->getCell("A{$row}")->getValue();
        }

        $this->assertContains('Backlog histórico (pendientes al cierre)', $valores);
        $this->assertContains('Total pendiente', $valores);
        $this->assertContains('Backlog por técnico', $valores);
        $this->assertContains('Backlog por categoría', $valores);
    }
}
