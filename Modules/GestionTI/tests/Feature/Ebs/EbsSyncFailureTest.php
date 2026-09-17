<?php

namespace Modules\GestionTI\Tests\Feature\Ebs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\GestionTI\Models\EbsSyncFailure;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Tests\TestCase;

/**
 * `EbsSyncFailure::registrar()`/`resolverSiPendiente()` — el registro
 * persistente de días fallidos de sincronización EBS, consumido por
 * `gestionti:ebs-reintentar-fallidos`. Ver docs/gestionti-progreso.md.
 */
class EbsSyncFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrar_creates_a_new_failure_with_intentos_1(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');

        $registro = EbsSyncFailure::first();

        $this->assertNotNull($registro);
        $this->assertSame('2026-09-06', $registro->fecha->toDateString());
        $this->assertSame(EbsRequisitionsClient::METHOD_CREADAS, $registro->metodo);
        $this->assertSame(10, $registro->error_code);
        $this->assertSame('sin datos', $registro->error_msg);
        $this->assertSame(1, $registro->intentos);
        $this->assertNull($registro->resuelto_at);
        $this->assertNull($registro->resuelto_via);
    }

    public function test_registrar_increments_intentos_and_refreshes_the_message_on_a_repeat_failure_the_same_day(): void
    {
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');
        EbsSyncFailure::registrar(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos (2do intento)');

        $this->assertSame(1, EbsSyncFailure::count());

        $registro = EbsSyncFailure::first();

        $this->assertSame(2, $registro->intentos);
        $this->assertSame('sin datos (2do intento)', $registro->error_msg);
    }

    public function test_registrar_reopens_an_already_resolved_record_because_a_real_failure_always_wins(): void
    {
        $fecha = Carbon::create(2026, 9, 6);

        EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');
        EbsSyncFailure::resolverSiPendiente($fecha, EbsRequisitionsClient::METHOD_CREADAS);

        $this->assertNotNull(EbsSyncFailure::first()->resuelto_at);

        EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_CREADAS, 10, 'volvió a fallar');

        $registro = EbsSyncFailure::first();

        $this->assertNull($registro->resuelto_at);
        $this->assertNull($registro->resuelto_via);
        $this->assertSame(2, $registro->intentos);
    }

    public function test_registrar_keeps_separate_records_per_metodo_for_the_same_day(): void
    {
        $fecha = Carbon::create(2026, 9, 6);

        EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');
        EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_APROBADAS, 10, 'sin datos');

        $this->assertSame(2, EbsSyncFailure::count());
    }

    public function test_resolver_si_pendiente_marks_a_pending_record_as_resolved_via_reintento(): void
    {
        $fecha = Carbon::create(2026, 9, 6);

        EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');
        EbsSyncFailure::resolverSiPendiente($fecha, EbsRequisitionsClient::METHOD_CREADAS);

        $registro = EbsSyncFailure::first();

        $this->assertNotNull($registro->resuelto_at);
        $this->assertSame(EbsSyncFailure::RESUELTO_VIA_REINTENTO, $registro->resuelto_via);
    }

    public function test_resolver_si_pendiente_is_a_no_op_when_no_record_exists(): void
    {
        EbsSyncFailure::resolverSiPendiente(Carbon::create(2026, 9, 6), EbsRequisitionsClient::METHOD_CREADAS);

        $this->assertSame(0, EbsSyncFailure::count());
    }

    public function test_pendientes_scope_only_returns_unresolved_records(): void
    {
        $fecha = Carbon::create(2026, 9, 6);

        EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_CREADAS, 10, 'sin datos');
        EbsSyncFailure::registrar($fecha, EbsRequisitionsClient::METHOD_APROBADAS, 10, 'sin datos');
        EbsSyncFailure::resolverSiPendiente($fecha, EbsRequisitionsClient::METHOD_CREADAS);

        $pendientes = EbsSyncFailure::pendientes()->get();

        $this->assertCount(1, $pendientes);
        $this->assertSame(EbsRequisitionsClient::METHOD_APROBADAS, $pendientes->first()->metodo);
    }
}
