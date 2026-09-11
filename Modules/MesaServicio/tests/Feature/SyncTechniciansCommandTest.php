<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Models\SdpTechnician;
use Tests\TestCase;

class SyncTechniciansCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeTokenAndRequests(array $requests, array $listInfo = []): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => Http::response([
                'response_status' => [['status_code' => 2000, 'status' => 'success']],
                'requests' => $requests,
                'list_info' => $listInfo,
            ], 200),
        ]);
    }

    protected function ticket(?array $technician): array
    {
        return [
            'id' => (string) fake()->randomNumber(9),
            'subject' => 'Ticket de prueba',
            'technician' => $technician,
        ];
    }

    public function test_it_upserts_technicians_derived_from_the_embedded_technician_object(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista']),
            $this->ticket(['id' => 't2', 'name' => 'Ana Ruiz', 'email_id' => 'ana@example.test', 'job_title' => 'Técnico']),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'correo' => 'juan@example.test',
            'puesto' => 'Analista',
            'activo' => true,
            'es_nivel_1' => false,
        ]);

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 't2',
            'nombre' => 'Ana Ruiz',
            'activo' => true,
        ]);

        $this->assertSame(2, SdpTechnician::count());
    }

    public function test_it_skips_tickets_without_an_assigned_technician(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(null),
            $this->ticket(['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista']),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertSame(1, SdpTechnician::count());
    }

    public function test_it_deduplicates_the_same_technician_across_multiple_tickets(): void
    {
        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista']),
            $this->ticket(['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista']),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertSame(1, SdpTechnician::count());
    }

    /**
     * es_nivel_1 es el único campo que un usuario edita manualmente desde la
     * pantalla — el comando de sync nunca debe tocarlo, ni en el alta ni en
     * corridas subsecuentes.
     */
    public function test_it_never_overwrites_the_manually_edited_es_nivel_1_flag(): void
    {
        SdpTechnician::create([
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez (nombre viejo)',
            'correo' => 'juan@example.test',
            'puesto' => 'Analista',
            'activo' => true,
            'es_nivel_1' => true,
        ]);

        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista Senior']),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'puesto' => 'Analista Senior',
            'es_nivel_1' => true,
        ]);
    }

    public function test_it_marks_previously_active_technicians_as_inactive_when_they_stop_appearing(): void
    {
        SdpTechnician::create([
            'sdp_id' => 't-old',
            'nombre' => 'Técnico Antiguo',
            'correo' => 'antiguo@example.test',
            'activo' => true,
            'es_nivel_1' => false,
        ]);

        // Un técnico que ya estaba inactivo antes de correr el comando debe
        // permanecer sin tocarse (no es un mass-update ciego).
        SdpTechnician::create([
            'sdp_id' => 't-inactive-already',
            'nombre' => 'Ya inactivo',
            'correo' => 'inactivo@example.test',
            'activo' => false,
            'es_nivel_1' => false,
        ]);

        $this->fakeTokenAndRequests([
            $this->ticket(['id' => 't-new', 'name' => 'Técnico Nuevo', 'email_id' => 'nuevo@example.test']),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertDatabaseHas('sdp_technicians', ['sdp_id' => 't-old', 'activo' => false]);
        $this->assertDatabaseHas('sdp_technicians', ['sdp_id' => 't-new', 'activo' => true]);
        $this->assertDatabaseHas('sdp_technicians', ['sdp_id' => 't-inactive-already', 'activo' => false]);
    }

    public function test_it_paginates_across_multiple_pages(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/requests*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $inputData = json_decode($query['input_data'], true);
                $startIndex = $inputData['list_info']['start_index'];

                if ($startIndex === 1) {
                    return Http::response([
                        'requests' => [$this->ticket(['id' => 't1', 'name' => 'Uno', 'email_id' => 'uno@example.test'])],
                        'list_info' => ['has_more_rows' => true],
                    ], 200);
                }

                return Http::response([
                    'requests' => [$this->ticket(['id' => 't2', 'name' => 'Dos', 'email_id' => 'dos@example.test'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertSame(2, SdpTechnician::count());
        $this->assertDatabaseHas('sdp_technicians', ['sdp_id' => 't1']);
        $this->assertDatabaseHas('sdp_technicians', ['sdp_id' => 't2']);
    }
}
