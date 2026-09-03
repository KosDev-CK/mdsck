<?php

namespace Modules\GestionTI\Tests\Feature\Ebs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncException;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Tests\TestCase;

class EbsRequisitionsClientTest extends TestCase
{
    use RefreshDatabase;

    protected function client(): EbsRequisitionsClient
    {
        return new EbsRequisitionsClient(
            baseUrl: 'https://ebs.example.test/getRequisitionDetail',
            organizationCode: 'L01',
            username: 'ebs_user',
            password: 'ebs_pass',
        );
    }

    protected function fakeResponse(array $requisitions = [], int $errorCode = 0): array
    {
        return [
            'payload' => ['requisitions' => $requisitions],
            'status' => ['errorCode' => $errorCode, 'errorMsg' => $errorCode === 0 ? 'OK' : 'ERROR'],
            'track' => [],
        ];
    }

    public function test_obtener_creadas_sends_basic_auth_and_the_right_query_string_with_an_empty_json_body(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->fakeResponse([['requisitionHeaderId' => 1]])),
        ]);

        $result = $this->client()->obtenerCreadas(1);

        $this->assertSame([['requisitionHeaderId' => 1]], $result);

        Http::assertSent(function ($request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://ebs.example.test/getRequisitionDetail')
                && $query['method'] === 'requisition_header_line'
                && $query['organization_code'] === 'L01'
                && $query['daysoffset'] === '1'
                && $request->hasHeader('Authorization')
                && $request->body() === '{}';
        });
    }

    public function test_obtener_aprobadas_uses_the_approved_method_and_a_custom_days_offset(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->fakeResponse([['requisitionHeaderId' => 2]])),
        ]);

        $result = $this->client()->obtenerAprobadas(7);

        $this->assertSame([['requisitionHeaderId' => 2]], $result);

        Http::assertSent(function ($request) {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['method'] === 'requisition_header_approved'
                && $query['daysoffset'] === '7';
        });
    }

    public function test_throws_when_the_http_request_fails(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response('server error', 500),
        ]);

        $this->expectException(EbsRequisitionSyncException::class);

        $this->client()->obtenerCreadas(1);
    }

    public function test_throws_when_ebs_responds_with_a_non_zero_error_code_even_on_http_200(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->fakeResponse([], errorCode: 1)),
        ]);

        $this->expectException(EbsRequisitionSyncException::class);

        $this->client()->obtenerAprobadas(1);
    }

    public function test_returns_an_empty_array_when_the_payload_has_no_requisitions(): void
    {
        Http::fake([
            'https://ebs.example.test/*' => Http::response($this->fakeResponse([])),
        ]);

        $this->assertSame([], $this->client()->obtenerCreadas(1));
    }
}
