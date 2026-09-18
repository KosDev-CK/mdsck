<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Models\SdpSite;
use Tests\TestCase;

class SyncSitesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function site(array $overrides = []): array
    {
        return array_replace([
            'id' => (string) fake()->unique()->randomNumber(9),
            'name' => 'Oficina CDMX',
            'country' => 'México',
            'state' => 'CDMX',
            'region' => 'Centro',
            'city' => 'Ciudad de México',
            'street' => 'Av. Reforma 123',
            'door_no' => '4',
            'postal_code' => '06600',
            'location' => 'Piso 4',
            'landmark' => 'Frente al parque',
            'timezone' => 'America/Mexico_City',
        ], $overrides);
    }

    protected function fakeTokenAndSites(array $sites, array $listInfo = []): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/sites*' => Http::response([
                'sites' => $sites,
                'list_info' => $listInfo,
            ], 200),
        ]);
    }

    public function test_it_upserts_sites_from_the_catalog_endpoint(): void
    {
        $this->fakeTokenAndSites([$this->site(['id' => 'site-1', 'name' => 'Oficina CDMX'])]);

        $this->artisan('sdp:sync-sites')->assertSuccessful();

        $site = SdpSite::where('sdp_id', 'site-1')->first();

        $this->assertNotNull($site);
        $this->assertSame('Oficina CDMX', $site->nombre);
        $this->assertSame('México', $site->pais);
        $this->assertSame('CDMX', $site->estado);
        $this->assertSame('Centro', $site->region);
        $this->assertSame('Ciudad de México', $site->ciudad);
        $this->assertSame('Av. Reforma 123', $site->calle);
        $this->assertSame('4', $site->numero_puerta);
        $this->assertSame('06600', $site->codigo_postal);
        $this->assertSame('Piso 4', $site->localidad);
        $this->assertSame('Frente al parque', $site->punto_referencia);
        $this->assertSame('America/Mexico_City', $site->zona_horaria);
    }

    public function test_it_is_idempotent_running_it_twice(): void
    {
        $this->fakeTokenAndSites([$this->site(['id' => 'site-1'])]);

        $this->artisan('sdp:sync-sites')->assertSuccessful();
        $this->artisan('sdp:sync-sites')->assertSuccessful();

        $this->assertSame(1, SdpSite::count());
    }

    public function test_it_paginates_across_multiple_pages(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/sites*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $inputData = json_decode($query['input_data'], true);
                $startIndex = $inputData['list_info']['start_index'];

                if ($startIndex === 1) {
                    return Http::response([
                        'sites' => [$this->site(['id' => 'site-1'])],
                        'list_info' => ['has_more_rows' => true],
                    ], 200);
                }

                return Http::response([
                    'sites' => [$this->site(['id' => 'site-2'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-sites')->assertSuccessful();

        $this->assertSame(2, SdpSite::count());
        $this->assertDatabaseHas('sdp_sites', ['sdp_id' => 'site-1']);
        $this->assertDatabaseHas('sdp_sites', ['sdp_id' => 'site-2']);
    }

    public function test_it_handles_zero_results_without_error(): void
    {
        $this->fakeTokenAndSites([], ['has_more_rows' => false]);

        $this->artisan('sdp:sync-sites')->assertSuccessful();

        $this->assertSame(0, SdpSite::count());
    }

    public function test_it_fails_gracefully_when_the_api_call_fails(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/sites*' => Http::response('server error', 500),
        ]);

        $this->artisan('sdp:sync-sites')->assertFailed();
    }
}
