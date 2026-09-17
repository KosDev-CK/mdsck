<?php

namespace Modules\GestionTI\Tests\Feature\Ebs;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\EbsSyncFailure;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Notifications\AvisoNotification;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Tests\TestCase;

/**
 * `gestionti:ebs-backfill` sobre un rango corto (fake, nunca contra el API
 * real de EBS ni contra producción — eso lo corre el usuario manualmente).
 * Confirma: recorre todos los días del rango calculando el daysoffset
 * correcto, tolera que un solo día falle sin abortar los restantes, y NUNCA
 * dispara avisos sin importar nada.
 */
class EbsBackfillCommandTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function envelope(array $requisitions = [], int $errorCode = 0): array
    {
        return [
            'payload' => ['requisitions' => $requisitions],
            'status' => ['errorCode' => $errorCode, 'errorMsg' => $errorCode === 0 ? 'OK' : 'ERROR'],
            'track' => [],
        ];
    }

    public function test_requires_the_desde_option(): void
    {
        $this->artisan('gestionti:ebs-backfill')->assertExitCode(1);
    }

    public function test_rejects_an_invalid_date_format(): void
    {
        $this->artisan('gestionti:ebs-backfill', ['--desde' => 'not-a-date'])->assertExitCode(1);
    }

    public function test_rejects_a_future_desde_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 3, 10, 0, 0));

        $this->artisan('gestionti:ebs-backfill', ['--desde' => '2026-09-10'])->assertExitCode(1);
    }

    public function test_walks_every_day_of_the_range_and_never_dispatches_avisos_even_on_a_status_transition(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 3, 10, 0, 0));

        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $empleado = Empleado::create(['numero_empleado' => 'EMP-BF-1', 'nombre' => 'Solicitante Backfill']);
        $empleado->update(['correo' => 'ebs-backfill@example.com']);
        $solicitanteUser = User::factory()->create(['email' => 'ebs-backfill@example.com']);

        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $empresa = Empresa::create(['razon_social' => 'Kosmos Backfill S.A. de C.V.', 'nombre_comercial' => 'Kosmos Backfill']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-BF', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $tipoEquipo->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            'folio_sic' => 'BF-6415',
        ]);

        $requisicionAprobada = [
            'requisitionHeaderId' => 777,
            'requisition' => ['code' => 'BF-6415', 'description' => 'Equipo', 'status' => 'APPROVED', 'date' => '2026-09-01T00:00:00.000+00:00'],
            'wf' => ['itemKey' => '', 'itemType' => ''],
            'action' => ['code' => 'APPROVE', 'date' => '2026-09-01T00:00:00.000+00:00'],
            'approver' => ['user' => 'A', 'name' => 'A A', 'date' => '2026-09-01T00:00:00.000+00:00'],
            'sequenceNum' => 1,
            'create' => ['user' => 'A', 'decription' => 'A'],
            'organization' => ['code' => 'L01', 'description' => 'L01'],
            'notes' => [],
        ];

        // Rango de 3 días: 2026-09-01 (offset 2), 09-02 (offset 1, falla),
        // 09-03 (offset 0, "hoy"). El día con offset=1 responde 500 para
        // confirmar que el comando tolera un solo día fallido sin abortar
        // los restantes.
        Http::fake([
            '*daysoffset=1*' => Http::response('down', 500),
            '*' => Http::response($this->envelope([$requisicionAprobada])),
        ]);

        // Nota de test: cada assertion de `expectsOutputToContain()` debe
        // apuntar a una línea de salida DISTINTA — el mock de Mockery detrás
        // de esa aserción solo permite que UNA expectativa "reclame" una
        // misma línea de `doWrite()`, así que verificar 2 substrings que
        // coexisten en la misma línea (ej. la fecha Y "FALL" de la línea que
        // sí falló) hace que la 2ª quede sin ninguna llamada que la
        // satisfaga. Por eso el offset que falla se verifica combinando
        // fecha + resultado en una sola aserción. Desde que "creadas" y
        // "aprobadas" corren en 2 try/catch independientes, el día fallido
        // (offset=1, ambos métodos truenan con el mismo fake HTTP 500)
        // produce 2 líneas "FALLÓ" — el substring buscado sigue apareciendo
        // igual en cualquiera de las 2.
        $this->artisan('gestionti:ebs-backfill', ['--desde' => '2026-09-01'])
            ->expectsOutputToContain('2026-09-01')
            ->expectsOutputToContain('2026-09-02 (daysoffset=1) — FALL')
            ->expectsOutputToContain('2026-09-03')
            ->assertExitCode(0);

        // El día que sí respondió con éxito (offset 0 y offset 2) sí debió
        // aplicar la vinculación/mapeo de estatus...
        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, SolicitudSicBorrador::first()->estatus);

        // ...pero el backfill NUNCA dispara avisos, sin importar nada.
        Notification::assertNotSentTo($solicitanteUser, AvisoNotification::class);
    }

    /**
     * Antes de separar "creadas"/"aprobadas" en 2 try/catch independientes,
     * si "creadas" tronaba, "aprobadas" del mismo día ni se intentaba. Este
     * test confirma el fix: un día donde SOLO "creadas" falla debe registrar
     * SOLO ese fallo en `EbsSyncFailure` — y "aprobadas" de ese mismo día se
     * aplica igual.
     */
    public function test_a_day_where_only_creadas_fails_still_applies_aprobadas_and_registers_only_that_failure(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 10, 10, 0, 0));

        Http::fake([
            '*method=requisition_header_line*' => Http::response('down', 500),
            '*method=requisition_header_approved*' => Http::response($this->envelope([[
                'requisitionHeaderId' => 999,
                'requisition' => ['code' => null, 'description' => null, 'status' => 'APPROVED', 'date' => null],
                'action' => ['code' => 'APPROVE', 'date' => '2026-09-10T00:00:00.000+00:00'],
                'approver' => ['user' => 'A', 'name' => 'A A', 'date' => '2026-09-10T00:00:00.000+00:00'],
                'sequenceNum' => 1,
                'notes' => [['key' => 'k', 'value' => 'v']],
            ]])),
        ]);

        $this->artisan('gestionti:ebs-backfill', ['--desde' => '2026-09-10'])
            ->assertExitCode(0);

        $this->assertTrue(
            EbsSyncFailure::whereDate('fecha', '2026-09-10')
                ->where('metodo', EbsRequisitionsClient::METHOD_CREADAS)
                ->exists()
        );

        $this->assertFalse(
            EbsSyncFailure::whereDate('fecha', '2026-09-10')
                ->where('metodo', EbsRequisitionsClient::METHOD_APROBADAS)
                ->exists()
        );

        // "aprobadas" sí se aplicó ese día, aunque "creadas" haya tronado.
        $this->assertDatabaseHas('ebs_requisitions', [
            'requisition_header_id' => 999,
            'status' => 'APPROVED',
        ]);

        $this->assertSame(1, EbsRequisition::first()->notes()->count());
    }
}
