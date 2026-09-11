<?php

namespace Modules\MesaServicio\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\MesaServicio\Console\Commands\DailyCloseCommand;
use Modules\MesaServicio\Models\SdpReport;
use Modules\MesaServicio\Models\SdpReportRecipientEmail;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Modules\MesaServicio\Notifications\CierreDiarioGeneradoNotification;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyCloseCommandTest extends TestCase
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

    public function test_it_groups_yesterdays_tickets_by_technician_without_filtering_by_status(): void
    {
        $ayer = today()->subDay();
        $juan = $this->tecnico('Juan Pérez');
        $ana = $this->tecnico('Ana López');
        $abierto = $this->estado('Abierto', SdpTicketStatus::TIPO_EN_CURSO);
        $cerrado = $this->estado('Cerrado', SdpTicketStatus::TIPO_COMPLETADO);

        // Ticket todavía abierto (sin cerrar) creado ayer — debe contar igual.
        SdpTicket::create([
            'sdp_id' => 'tk-1', 'asunto' => 'Abierto', 'created_time' => $ayer->copy()->addHours(9),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $abierto->id, 'estado_nombre' => 'Abierto',
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-2', 'asunto' => 'Cerrado', 'created_time' => $ayer->copy()->addHours(11),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $cerrado->id, 'estado_nombre' => 'Cerrado',
        ]);
        SdpTicket::create([
            'sdp_id' => 'tk-3', 'asunto' => 'De Ana', 'created_time' => $ayer->copy()->addHours(14),
            'sdp_technician_id' => $ana->id, 'sdp_ticket_status_id' => $abierto->id, 'estado_nombre' => 'Abierto',
        ]);
        // Ticket de otro día — no debe contarse.
        SdpTicket::create([
            'sdp_id' => 'tk-4', 'asunto' => 'De hoy', 'created_time' => now(),
            'sdp_technician_id' => $juan->id, 'sdp_ticket_status_id' => $abierto->id, 'estado_nombre' => 'Abierto',
        ]);

        $this->artisan('sdp:daily-close')->assertExitCode(0);

        $report = SdpReport::first();
        $this->assertNotNull($report);
        $this->assertSame(SdpReport::TIPO_DIARIO, $report->tipo);
        $this->assertSame($ayer->toDateString(), $report->periodo->toDateString());
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

    public function test_tickets_without_a_technician_are_grouped_as_sin_asignar(): void
    {
        $ayer = today()->subDay();

        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Sin técnico', 'created_time' => $ayer->copy()->addHours(9)]);

        $this->artisan('sdp:daily-close')->assertExitCode(0);

        $report = SdpReport::first();
        $this->assertSame(1, $report->resumen_metricas['por_tecnico']['Sin asignar']);
    }

    public function test_a_day_without_tickets_still_generates_a_report_with_zeros(): void
    {
        $this->artisan('sdp:daily-close')->assertExitCode(0);

        $report = SdpReport::first();
        $this->assertNotNull($report);
        $this->assertSame(0, $report->resumen_metricas['total']);
        $this->assertSame([], $report->resumen_metricas['por_tecnico']);
        $this->assertSame([], $report->resumen_metricas['por_estado']);

        Storage::disk('local')->assertExists($report->ruta_archivo);
    }

    public function test_running_the_same_date_twice_updates_the_existing_report_instead_of_duplicating(): void
    {
        $ayer = today()->subDay();
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Uno', 'created_time' => $ayer->copy()->addHours(9)]);

        $this->artisan('sdp:daily-close')->assertExitCode(0);
        $this->assertSame(1, SdpReport::first()->resumen_metricas['total']);

        SdpTicket::create(['sdp_id' => 'tk-2', 'asunto' => 'Dos', 'created_time' => $ayer->copy()->addHours(10)]);

        $this->artisan('sdp:daily-close')->assertExitCode(0);

        $this->assertSame(1, SdpReport::count());
        $this->assertSame(2, SdpReport::first()->resumen_metricas['total']);
    }

    public function test_a_past_date_can_be_regenerated_manually_via_the_optional_argument(): void
    {
        $fecha = today()->subDays(5);
        SdpTicket::create(['sdp_id' => 'tk-1', 'asunto' => 'Viejo', 'created_time' => $fecha->copy()->addHours(9)]);

        $this->artisan('sdp:daily-close', ['fecha' => $fecha->toDateString()])->assertExitCode(0);

        $report = SdpReport::first();
        $this->assertSame($fecha->toDateString(), $report->periodo->toDateString());
        $this->assertSame(1, $report->resumen_metricas['total']);
    }

    public function test_it_notifies_the_supervisor_role_and_each_recipient_email(): void
    {
        Notification::fake();

        $role = Role::findOrCreate(DailyCloseCommand::ROL_SUPERVISOR, 'web');
        $supervisor = User::factory()->create(['is_active' => true]);
        $supervisor->assignRole($role);
        $inactiveSupervisor = User::factory()->create(['is_active' => false]);
        $inactiveSupervisor->assignRole($role);

        SdpReportRecipientEmail::create(['email' => 'suelto1@example.test']);
        SdpReportRecipientEmail::create(['email' => 'suelto2@example.test']);

        $this->artisan('sdp:daily-close')->assertExitCode(0);

        Notification::assertSentTo($supervisor, CierreDiarioGeneradoNotification::class);
        Notification::assertNotSentTo($inactiveSupervisor, CierreDiarioGeneradoNotification::class);
        Notification::assertSentOnDemand(
            CierreDiarioGeneradoNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'suelto1@example.test'
        );
        Notification::assertSentOnDemand(
            CierreDiarioGeneradoNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'suelto2@example.test'
        );
    }

    public function test_it_does_not_fail_when_there_are_no_recipients_configured(): void
    {
        Notification::fake();

        $this->artisan('sdp:daily-close')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
