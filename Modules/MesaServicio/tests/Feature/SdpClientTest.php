<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\MesaServicio\Services\SdpClient;
use Tests\TestCase;

class SdpClientTest extends TestCase
{
    use RefreshDatabase;

    protected function client(): SdpClient
    {
        return new SdpClient(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            refreshToken: 'test-refresh-token',
            portal: 'testportal',
            apiDomain: 'https://api.example.test',
            accountsDomain: 'https://accounts.example.test',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        // El access token se cachea por client_id (ver SdpClient::accessToken) —
        // hay que limpiar entre tests para que no se filtre un token de un test
        // anterior con el mismo client_id de prueba.
        Cache::flush();
    }

    public function test_access_token_requests_a_refresh_token_grant_and_caches_it(): void
    {
        Http::fake([
            'https://accounts.example.test/oauth/v2/token' => Http::response([
                'access_token' => 'fake-access-token',
            ], 200),
        ]);

        $client = $this->client();

        $this->assertSame('fake-access-token', $client->accessToken());
        $this->assertSame('fake-access-token', $client->accessToken());

        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://accounts.example.test/oauth/v2/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['client_id'] === 'test-client-id'
                && $request['client_secret'] === 'test-client-secret'
                && $request['refresh_token'] === 'test-refresh-token';
        });
    }

    /**
     * Los listados van por GET con "input_data" como parámetro de query
     * string (JSON serializado) — no llega en el cuerpo, así que hay que
     * decodificar la URL en vez de usar $request['input_data'].
     */
    protected function inputDataFromUrl(string $url): array
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return json_decode($query['input_data'], true);
    }

    public function test_list_requests_gets_list_info_from_the_requests_endpoint(): void
    {
        Http::fake([
            'https://accounts.example.test/oauth/v2/token' => Http::response([
                'access_token' => 'fake-access-token',
            ], 200),
            'https://api.example.test/app/testportal/api/v3/requests*' => Http::response([
                'requests' => [],
                'list_info' => ['total_count' => 0],
            ], 200),
        ]);

        $payload = $this->client()->listRequests(startIndex: 1, rowCount: 50);

        $this->assertSame(['total_count' => 0], $payload['list_info']);

        Http::assertSent(function ($request) {
            if (! str_starts_with($request->url(), 'https://api.example.test/app/testportal/api/v3/requests')) {
                return false;
            }

            $listInfo = $this->inputDataFromUrl($request->url())['list_info'];

            return $request->method() === 'GET'
                && $request->hasHeader('Authorization', 'Zoho-oauthtoken fake-access-token')
                && $listInfo['start_index'] === 1
                && $listInfo['row_count'] === 50;
        });
    }

    public function test_list_requests_gets_search_criteria_and_fields_required_from_the_requests_endpoint(): void
    {
        Http::fake([
            'https://accounts.example.test/oauth/v2/token' => Http::response([
                'access_token' => 'fake-access-token',
            ], 200),
            'https://api.example.test/app/testportal/api/v3/requests*' => Http::response([
                'requests' => [],
                'list_info' => ['total_count' => 0],
            ], 200),
        ]);

        $searchCriteria = [['field' => 'status.name', 'condition' => 'is', 'value' => 'Open']];
        $fieldsRequired = ['id', 'subject', 'status'];

        $this->client()->listRequests($searchCriteria, $fieldsRequired, startIndex: 101, rowCount: 100);

        Http::assertSent(function ($request) use ($searchCriteria, $fieldsRequired) {
            if (! str_starts_with($request->url(), 'https://api.example.test/app/testportal/api/v3/requests')) {
                return false;
            }

            $listInfo = $this->inputDataFromUrl($request->url())['list_info'];

            return $request->method() === 'GET'
                && $listInfo['start_index'] === 101
                && $listInfo['row_count'] === 100
                && $listInfo['search_criteria'] === $searchCriteria
                && $listInfo['fields_required'] === $fieldsRequired;
        });
    }

    public function test_access_token_throws_when_the_token_request_fails(): void
    {
        Http::fake([
            'https://accounts.example.test/oauth/v2/token' => Http::response('invalid_client', 400),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->client()->accessToken();
    }

    public function test_list_requests_throws_when_the_api_request_fails(): void
    {
        Http::fake([
            'https://accounts.example.test/oauth/v2/token' => Http::response([
                'access_token' => 'fake-access-token',
            ], 200),
            'https://api.example.test/app/testportal/api/v3/requests*' => Http::response('server error', 500),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->client()->listRequests();
    }
}
