<?php

namespace Modules\GestionTI\Tests\Feature\Ebs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\EbsSyncFailure;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Tests\TestCase;

/**
 * `gestionti:ebs-reintentar-fallidos` — reintenta cada `EbsSyncFailure`
 * pendiente contra la API real, y si sigue fallando, intenta confirmar por
 * consecutivo de folio (solo "requisition_header_line") que no hubo SICs
 * ese día. Ver docs/gestionti-progreso.md.
 */
class EbsReintentarFallidosCommandTest extends TestCase
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

        Carbon::setTestNow(Carbon::create(2026, 9, 10, 10, 0, 0));
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

    public function test_a_failure_that_now_succeeds_is_resolved_via_reintento(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 5), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');

        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->envelope()),
        ]);

        $this->artisan('gestionti:ebs-reintentar-fallidos')
            ->expectsOutputToContain('OK, resuelto vía reintento real')
            ->assertExitCode(0);

        $registro = EbsSyncFailure::first();

        $this->assertNotNull($registro->resuelto_at);
        $this->assertSame(EbsSyncFailure::RESUELTO_VIA_REINTENTO, $registro->resuelto_via);
    }

    public function test_a_failure_still_failing_gets_confirmed_by_consecutive_folio(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');

        // El folio anterior (antes del día fallido) y el siguiente (después)
        // son consecutivos (100 -> 101): prueba que no se creó nada el
        // 2026-09-06.
        EbsRequisition::create(['requisition_header_id' => 1, 'code' => '100', 'fecha_creacion' => '2026-09-05 10:00:00']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '101', 'fecha_creacion' => '2026-09-07 10:00:00']);

        Http::fake([
            'https://ebs.example.test/*' => Http::response('down', 500),
        ]);

        $this->artisan('gestionti:ebs-reintentar-fallidos')
            ->expectsOutputToContain('confirmado sin SICs por folio consecutivo (anterior=100, siguiente=101)')
            ->assertExitCode(0);

        $registro = EbsSyncFailure::first();

        $this->assertNotNull($registro->resuelto_at);
        $this->assertSame(EbsSyncFailure::RESUELTO_VIA_CONSECUTIVO, $registro->resuelto_via);
    }

    public function test_a_failure_still_failing_with_non_consecutive_folios_stays_pending(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');

        EbsRequisition::create(['requisition_header_id' => 1, 'code' => '100', 'fecha_creacion' => '2026-09-05 10:00:00']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '105', 'fecha_creacion' => '2026-09-07 10:00:00']);

        Http::fake([
            'https://ebs.example.test/*' => Http::response('down', 500),
        ]);

        $this->artisan('gestionti:ebs-reintentar-fallidos')
            ->expectsOutputToContain('sigue fallando, pendiente')
            ->assertExitCode(0);

        $registro = EbsSyncFailure::first();

        $this->assertNull($registro->resuelto_at);
        $this->assertSame(2, $registro->intentos);
    }

    public function test_a_metodo_aprobadas_failure_never_gets_confirmed_by_consecutive_folio(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_APROBADAS, 10, 'sin datos');

        // Folios consecutivos que SÍ confirmarían el día para "creadas" —
        // pero este registro es de "aprobadas", que no tiene este concepto.
        EbsRequisition::create(['requisition_header_id' => 1, 'code' => '100', 'fecha_creacion' => '2026-09-05 10:00:00']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '101', 'fecha_creacion' => '2026-09-07 10:00:00']);

        Http::fake([
            'https://ebs.example.test/*' => Http::response('down', 500),
        ]);

        $this->artisan('gestionti:ebs-reintentar-fallidos')
            ->expectsOutputToContain('sigue fallando, pendiente')
            ->assertExitCode(0);

        $registro = EbsSyncFailure::first();

        $this->assertNull($registro->resuelto_at);
    }

    public function test_a_non_numeric_code_on_either_end_cannot_be_confirmed_and_stays_pending(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');

        EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'ABC', 'fecha_creacion' => '2026-09-05 10:00:00']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '101', 'fecha_creacion' => '2026-09-07 10:00:00']);

        Http::fake([
            'https://ebs.example.test/*' => Http::response('down', 500),
        ]);

        $this->artisan('gestionti:ebs-reintentar-fallidos')
            ->expectsOutputToContain('sigue fallando, pendiente')
            ->assertExitCode(0);

        $registro = EbsSyncFailure::first();

        $this->assertNull($registro->resuelto_at);
    }

    public function test_without_enough_real_data_on_either_side_it_cannot_be_confirmed_and_stays_pending(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');

        // Solo hay dato "anterior", nada "siguiente" real todavía.
        EbsRequisition::create(['requisition_header_id' => 1, 'code' => '100', 'fecha_creacion' => '2026-09-05 10:00:00']);

        Http::fake([
            'https://ebs.example.test/*' => Http::response('down', 500),
        ]);

        $this->artisan('gestionti:ebs-reintentar-fallidos')
            ->expectsOutputToContain('sigue fallando, pendiente')
            ->assertExitCode(0);

        $this->assertNull(EbsSyncFailure::first()->resuelto_at);
    }
}
