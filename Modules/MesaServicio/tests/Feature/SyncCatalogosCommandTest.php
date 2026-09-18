<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Models\SdpCatalogEntry;
use Tests\TestCase;

class SyncCatalogosCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Entradas "planas" por defecto para cada uno de los 11 catálogos que
     * comparten forma (todos menos priority_matrices) — closure_codes usa
     * "inactive"+"module" en vez de "deleted".
     */
    private function entradaPlana(string $catalogo, array $overrides = []): array
    {
        if ($catalogo === 'closure_codes') {
            return array_replace([
                'id' => 'cc-1',
                'name' => 'Resuelto',
                'inactive' => false,
                'module' => ['api_plural_name' => 'requests', 'name' => 'request'],
            ], $overrides);
        }

        return array_replace([
            'id' => 'entry-1',
            'name' => 'Entrada 1',
            'description' => 'Descripción de prueba',
            'deleted' => false,
        ], $overrides);
    }

    private function entradaPriorityMatrix(array $overrides = []): array
    {
        return array_replace([
            'urgency' => ['id' => 'u1', 'name' => 'Alta'],
            'impact' => ['id' => 'i1', 'name' => 'Alto'],
            'priority' => ['id' => 'p1', 'name' => 'Urgente', 'color' => '#ff0000'],
        ], $overrides);
    }

    /**
     * Fake genérico para los 12 recursos de catálogo, cada uno con una sola
     * entrada por defecto (o las que se le pasen en $entriesByResource).
     */
    private function fakeAllCatalogs(array $entriesByResource = []): void
    {
        $fakes = [
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
        ];

        foreach (SdpCatalogEntry::CATALOGOS as $catalogo) {
            $entries = $entriesByResource[$catalogo] ?? ($catalogo === 'priority_matrices'
                ? [$this->entradaPriorityMatrix()]
                : [$this->entradaPlana($catalogo)]);

            $fakes["*/api/v3/{$catalogo}*"] = Http::response([
                $catalogo => $entries,
                'list_info' => ['has_more_rows' => false],
            ], 200);
        }

        Http::fake($fakes);
    }

    public function test_it_syncs_all_12_catalogs_when_no_argument_is_given(): void
    {
        $this->fakeAllCatalogs();

        $this->artisan('sdp:sync-catalogos')->assertSuccessful();

        $this->assertSame(12, SdpCatalogEntry::query()->select('catalogo')->distinct()->count());
        $this->assertSame(12, SdpCatalogEntry::count());
    }

    public function test_it_syncs_only_the_given_catalog_when_an_argument_is_provided(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/categories*' => Http::response([
                'categories' => [$this->entradaPlana('categories', ['id' => 'cat-1', 'name' => 'Hardware'])],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'categories'])->assertSuccessful();

        $this->assertSame(1, SdpCatalogEntry::count());
        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'categories', 'sdp_id' => 'cat-1', 'nombre' => 'Hardware']);
    }

    public function test_it_rejects_an_unknown_catalog_argument(): void
    {
        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'no-existe'])->assertFailed();

        $this->assertSame(0, SdpCatalogEntry::count());
    }

    public function test_it_derives_activo_from_the_deleted_flag_for_regular_catalogs(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/modes*' => Http::response([
                'modes' => [
                    $this->entradaPlana('modes', ['id' => 'm1', 'name' => 'E-Mail', 'deleted' => false, 'internal_name' => 'email']),
                    $this->entradaPlana('modes', ['id' => 'm2', 'name' => 'Teléfono', 'deleted' => true]),
                ],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'modes'])->assertSuccessful();

        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'modes', 'sdp_id' => 'm1', 'activo' => true]);
        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'modes', 'sdp_id' => 'm2', 'activo' => false]);

        $entry = SdpCatalogEntry::where('sdp_id', 'm1')->first();
        $this->assertSame('email', $entry->extra['internal_name']);
    }

    public function test_it_derives_activo_from_the_inactive_flag_and_keeps_the_module_for_closure_codes(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/closure_codes*' => Http::response([
                'closure_codes' => [
                    $this->entradaPlana('closure_codes', ['id' => 'cc1', 'name' => 'Resuelto', 'inactive' => false, 'module' => ['api_plural_name' => 'requests', 'name' => 'request']]),
                    $this->entradaPlana('closure_codes', ['id' => 'cc2', 'name' => 'Cancelado', 'inactive' => true, 'module' => ['api_plural_name' => 'problems', 'name' => 'problem']]),
                ],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'closure_codes'])->assertSuccessful();

        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'closure_codes', 'sdp_id' => 'cc1', 'activo' => true]);
        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'closure_codes', 'sdp_id' => 'cc2', 'activo' => false]);

        $requests = SdpCatalogEntry::where('sdp_id', 'cc1')->first();
        $this->assertSame('requests', $requests->extra['module']['api_plural_name']);

        // No se filtra por módulo — se guarda igual la de "problems".
        $problems = SdpCatalogEntry::where('sdp_id', 'cc2')->first();
        $this->assertSame('problems', $problems->extra['module']['api_plural_name']);
    }

    public function test_it_stores_priority_matrix_entries_with_a_synthesized_deterministic_id(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/priority_matrices*' => Http::response([
                'priority_matrices' => [$this->entradaPriorityMatrix()],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'priority_matrices'])->assertSuccessful();

        $expectedSdpId = md5('u1|i1');

        $entry = SdpCatalogEntry::where('catalogo', 'priority_matrices')->first();

        $this->assertNotNull($entry);
        $this->assertSame($expectedSdpId, $entry->sdp_id);
        $this->assertSame('Alta + Alto → Urgente', $entry->nombre);
        $this->assertSame('#ff0000', $entry->color);
        $this->assertSame('u1', $entry->extra['urgency']['id']);
        $this->assertSame('i1', $entry->extra['impact']['id']);
        $this->assertSame('p1', $entry->extra['priority']['id']);

        // Re-correr con la misma combinación urgencia+impacto actualiza en
        // vez de duplicar (idempotencia vía el id sintetizado).
        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'priority_matrices'])->assertSuccessful();
        $this->assertSame(1, SdpCatalogEntry::where('catalogo', 'priority_matrices')->count());
    }

    public function test_it_is_idempotent_running_the_same_catalog_twice(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/categories*' => Http::response([
                'categories' => [$this->entradaPlana('categories', ['id' => 'cat-1'])],
                'list_info' => ['has_more_rows' => false],
            ], 200),
        ]);

        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'categories'])->assertSuccessful();
        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'categories'])->assertSuccessful();

        $this->assertSame(1, SdpCatalogEntry::count());
    }

    public function test_it_paginates_across_multiple_pages(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/categories*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $inputData = json_decode($query['input_data'], true);
                $startIndex = $inputData['list_info']['start_index'];

                if ($startIndex === 1) {
                    return Http::response([
                        'categories' => [$this->entradaPlana('categories', ['id' => 'cat-1'])],
                        'list_info' => ['has_more_rows' => true],
                    ], 200);
                }

                return Http::response([
                    'categories' => [$this->entradaPlana('categories', ['id' => 'cat-2'])],
                    'list_info' => ['has_more_rows' => false],
                ], 200);
            },
        ]);

        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'categories'])->assertSuccessful();

        $this->assertSame(2, SdpCatalogEntry::count());
        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'categories', 'sdp_id' => 'cat-1']);
        $this->assertDatabaseHas('sdp_catalog_entries', ['catalogo' => 'categories', 'sdp_id' => 'cat-2']);
    }

    public function test_it_fails_gracefully_when_one_catalog_call_fails(): void
    {
        Http::fake([
            '*/oauth/v2/token' => Http::response(['access_token' => 'fake-access-token'], 200),
            '*/api/v3/categories*' => Http::response('server error', 500),
        ]);

        $this->artisan('sdp:sync-catalogos', ['catalogo' => 'categories'])->assertFailed();
    }
}
