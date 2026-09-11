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
        $this->assertSame(2, $report->resumen_metricas['por_tecnico']['Juan Pérez']);
        $this->assertSame(1, $report->resumen_metricas['por_tecnico']['Ana López']);
        $this->assertSame(2, $report->resumen_metricas['por_estado']['Abierto']);
        $this->assertSame(1, $report->resumen_metricas['por_estado']['Cerrado']);

        Storage::disk('local')->assertExists($report->ruta_archivo);

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($report->ruta_archivo));
        $this->assertSame(['Resumen', 'Detalle'], array_map(fn ($sheet) => $sheet->getTitle(), $spreadsheet->getAllSheets()));
        $this->assertSame(3, $spreadsheet->getSheetByName('Detalle')->getHighestRow() - 1);
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
}
