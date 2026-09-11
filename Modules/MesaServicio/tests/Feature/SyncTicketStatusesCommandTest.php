<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Tests\TestCase;

class SyncTicketStatusesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeTokenAndStatuses(array $statuses, array $listInfo = []): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/statuses*' => Http::response([
                'response_status' => [['status_code' => 2000, 'status' => 'success']],
                'statuses' => $statuses,
                'list_info' => $listInfo,
            ], 200),
        ]);
    }

    public function test_it_upserts_statuses_mapping_in_progress_to_tipo_and_deleted_to_activo(): void
    {
        $this->fakeTokenAndStatuses([
            [
                'id' => '2907000000008025',
                'name' => 'Abierto',
                'internal_name' => 'Open',
                'in_progress' => true,
                'deleted' => false,
            ],
            [
                'id' => '2907000000008029',
                'name' => 'Completado',
                'internal_name' => 'Closed',
                'in_progress' => false,
                'deleted' => false,
            ],
            [
                'id' => '2907000000008030',
                'name' => 'Archivado',
                'internal_name' => 'Archived',
                'in_progress' => false,
                'deleted' => true,
            ],
        ]);

        $this->artisan('sdp:sync-ticket-statuses')->assertSuccessful();

        $this->assertDatabaseHas('sdp_ticket_statuses', [
            'sdp_id' => '2907000000008025',
            'nombre' => 'Abierto',
            'tipo' => SdpTicketStatus::TIPO_EN_CURSO,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('sdp_ticket_statuses', [
            'sdp_id' => '2907000000008029',
            'nombre' => 'Completado',
            'tipo' => SdpTicketStatus::TIPO_COMPLETADO,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('sdp_ticket_statuses', [
            'sdp_id' => '2907000000008030',
            'nombre' => 'Archivado',
            'tipo' => SdpTicketStatus::TIPO_COMPLETADO,
            'activo' => false,
        ]);

        $this->assertSame(3, SdpTicketStatus::count());
    }

    public function test_it_is_idempotent_running_it_twice(): void
    {
        $this->fakeTokenAndStatuses([
            ['id' => '1', 'name' => 'Abierto', 'internal_name' => 'Open', 'in_progress' => true, 'deleted' => false],
        ]);

        $this->artisan('sdp:sync-ticket-statuses')->assertSuccessful();
        $this->artisan('sdp:sync-ticket-statuses')->assertSuccessful();

        $this->assertSame(1, SdpTicketStatus::count());
    }

    public function test_it_updates_an_existing_status_when_it_changes_upstream(): void
    {
        SdpTicketStatus::create([
            'sdp_id' => '1',
            'nombre' => 'Abierto',
            'internal_name' => 'Open',
            'tipo' => SdpTicketStatus::TIPO_EN_CURSO,
            'activo' => true,
        ]);

        $this->fakeTokenAndStatuses([
            ['id' => '1', 'name' => 'Abierto', 'internal_name' => 'Open', 'in_progress' => false, 'deleted' => true],
        ]);

        $this->artisan('sdp:sync-ticket-statuses')->assertSuccessful();

        $this->assertDatabaseHas('sdp_ticket_statuses', [
            'sdp_id' => '1',
            'tipo' => SdpTicketStatus::TIPO_COMPLETADO,
            'activo' => false,
        ]);
    }
}
