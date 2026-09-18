<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Models\SdpTechnician;
use Tests\TestCase;

class SyncTechniciansCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeTokenAndUsers(array $users, array $listInfo = []): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/users*' => Http::response([
                'response_status' => [['status_code' => 2000, 'status' => 'success']],
                'users' => $users,
                'list_info' => $listInfo,
            ], 200),
        ]);
    }

    protected function user(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) fake()->randomNumber(9),
            'name' => 'Juan Pérez',
            'email_id' => 'juan@example.test',
            'job_title' => 'Analista',
            'is_technician' => true,
            'zuid' => '20067336226',
        ], $overrides);
    }

    public function test_it_upserts_technicians_from_the_users_endpoint(): void
    {
        $this->fakeTokenAndUsers([
            $this->user(['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista', 'zuid' => '20067336226']),
            $this->user(['id' => 't2', 'name' => 'Ana Ruiz', 'email_id' => 'ana@example.test', 'job_title' => 'Técnico', 'zuid' => '20099999999']),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 't1',
            'nombre' => 'Juan Pérez',
            'correo' => 'juan@example.test',
            'puesto' => 'Analista',
            'zuid' => '20067336226',
            'tiene_acceso_sdp' => true,
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

    /**
     * Caso real confirmado por el usuario: "Adrian Guerrero Gonzalez" tiene
     * zuid "-1" (marcado técnico en SDP pero sin login real), "Adan Manuel
     * Cortes Palomec" tiene un zuid numérico real (20067336226).
     */
    public function test_it_derives_tiene_acceso_sdp_from_zuid(): void
    {
        $this->fakeTokenAndUsers([
            $this->user(['id' => 'sin-login', 'name' => 'Adrian Guerrero Gonzalez', 'zuid' => '-1']),
            $this->user(['id' => 'con-login', 'name' => 'Adan Manuel Cortes Palomec', 'zuid' => '20067336226']),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 'sin-login',
            'zuid' => '-1',
            'tiene_acceso_sdp' => false,
        ]);

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 'con-login',
            'zuid' => '20067336226',
            'tiene_acceso_sdp' => true,
        ]);
    }

    public function test_it_derives_tiene_acceso_sdp_false_when_zuid_is_missing(): void
    {
        $this->fakeTokenAndUsers([
            $this->user(['id' => 't1', 'zuid' => null]),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertDatabaseHas('sdp_technicians', [
            'sdp_id' => 't1',
            'zuid' => null,
            'tiene_acceso_sdp' => false,
        ]);
    }

    public function test_it_skips_users_without_an_id(): void
    {
        $this->fakeTokenAndUsers([
            ['id' => null, 'name' => 'Sin id'],
            $this->user(['id' => 't1']),
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

        $this->fakeTokenAndUsers([
            $this->user(['id' => 't1', 'name' => 'Juan Pérez', 'email_id' => 'juan@example.test', 'job_title' => 'Analista Senior']),
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

        $this->fakeTokenAndUsers([
            $this->user(['id' => 't-new', 'name' => 'Técnico Nuevo', 'email_id' => 'nuevo@example.test']),
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
            '*/api/v3/users*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $inputData = json_decode($query['input_data'], true);
                $startIndex = $inputData['list_info']['start_index'];

                if ($startIndex === 1) {
                    return Http::response([
                        'users' => [$this->user(['id' => 't1', 'name' => 'Uno', 'email_id' => 'uno@example.test'])],
                        'list_info' => ['has_more_rows' => true],
                    ], 200);
                }

                return Http::response([
                    'users' => [$this->user(['id' => 't2', 'name' => 'Dos', 'email_id' => 'dos@example.test'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        $this->assertSame(2, SdpTechnician::count());
        $this->assertDatabaseHas('sdp_technicians', ['sdp_id' => 't1']);
        $this->assertDatabaseHas('sdp_technicians', ['sdp_id' => 't2']);
    }

    /**
     * search_criteria enviado debe filtrar por is_technician=true, condición
     * "is" — verificado reflejando la query real armada por el cliente HTTP
     * (mismo patrón que SdpClientTest para inspeccionar input_data en un GET).
     */
    public function test_it_filters_by_is_technician_true(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/users*' => Http::response([
                'users' => [],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->artisan('sdp:sync-technicians')->assertSuccessful();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/v3/users')) {
                return false;
            }

            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $inputData = json_decode($query['input_data'], true);

            return $inputData['list_info']['search_criteria'] === [
                ['field' => 'is_technician', 'condition' => 'is', 'value' => true],
            ];
        });
    }
}
