<?php

namespace Modules\GestionTI\Tests\Feature\Ebs;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Notifications\AvisoNotification;
use Tests\TestCase;

/**
 * `gestionti:ebs-sincronizar-creadas`/`-aprobadas` de punta a punta contra
 * `Http::fake()` (nunca contra el API real de EBS) — confirma que un fallo
 * de EBS (errorCode != 0, HTTP 500) se registra y el comando termina en
 * FAILURE sin lanzar una excepción sin capturar.
 */
class EbsSyncCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ebs.base_url' => 'https://ebs.example.test/getRequisitionDetail',
            'services.ebs.organization_code' => 'L01',
            'services.ebs.username' => 'ebs_user',
            'services.ebs.password' => 'ebs_pass',
        ]);
    }

    protected function envelope(array $requisitions = [], int $errorCode = 0): array
    {
        return [
            'payload' => ['requisitions' => $requisitions],
            'status' => ['errorCode' => $errorCode, 'errorMsg' => $errorCode === 0 ? 'OK' : 'ERROR'],
            'track' => [],
        ];
    }

    public function test_sincronizar_creadas_command_completes_successfully_with_no_requisitions(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->envelope()),
        ]);

        $this->artisan('gestionti:ebs-sincronizar-creadas', ['--dias' => 2])
            ->assertExitCode(0);
    }

    public function test_sincronizar_creadas_command_does_not_crash_on_a_non_zero_error_code(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->envelope(errorCode: 1)),
        ]);

        $this->artisan('gestionti:ebs-sincronizar-creadas')
            ->assertExitCode(1);
    }

    public function test_sincronizar_creadas_command_does_not_crash_on_an_http_failure(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response('down', 503),
        ]);

        $this->artisan('gestionti:ebs-sincronizar-creadas')
            ->assertExitCode(1);
    }

    public function test_sincronizar_aprobadas_command_completes_successfully_with_no_requisitions(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->envelope()),
        ]);

        $this->artisan('gestionti:ebs-sincronizar-aprobadas', ['--dias' => 1])
            ->assertExitCode(0);
    }

    public function test_sincronizar_aprobadas_command_does_not_crash_on_a_non_zero_error_code(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->envelope(errorCode: 2)),
        ]);

        $this->artisan('gestionti:ebs-sincronizar-aprobadas')
            ->assertExitCode(1);
    }

    public function test_sin_avisos_flag_prevents_the_aviso_from_being_dispatched(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $empleado = Empleado::create(['numero_empleado' => 'EMP-CMD-1', 'nombre' => 'Solicitante Comando']);
        $empleado->update(['correo' => 'ebs-comando@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'ebs-comando@example.com']);

        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $empresa = Empresa::create(['razon_social' => 'Kosmos Comando S.A. de C.V.', 'nombre_comercial' => 'Kosmos Comando']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-CMD', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $tipoEquipo->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'CMD-6415',
        ]);

        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->envelope([[
                'requisitionHeaderId' => 42,
                'requisition' => ['code' => 'CMD-6415', 'description' => 'Equipo', 'status' => 'APPROVED', 'date' => '2026-08-30T00:00:00.000+00:00'],
                'wf' => ['itemKey' => '', 'itemType' => ''],
                'action' => ['code' => 'APPROVE', 'date' => '2026-08-31T00:00:00.000+00:00'],
                'approver' => ['user' => 'A', 'name' => 'A A', 'date' => '2026-08-31T00:00:00.000+00:00'],
                'sequenceNum' => 1,
                'create' => ['user' => 'A', 'decription' => 'A'],
                'organization' => ['code' => 'L01', 'description' => 'L01'],
                'notes' => [],
            ]])),
        ]);

        $this->artisan('gestionti:ebs-sincronizar-aprobadas', ['--sin-avisos' => true])
            ->assertExitCode(0);

        Notification::assertNotSentTo($solicitanteUser, AvisoNotification::class);
    }
}
