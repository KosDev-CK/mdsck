<?php

namespace Modules\MesaServicio\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente para la API REST v3 de ServiceDesk Plus Cloud (SDPOD), autenticada
 * vía OAuth2 de Zoho Accounts. Ver docs/servicedesk-plus-oauth.md para el
 * runbook de registro del Self Client y obtención del refresh token.
 */
class SdpClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        private readonly string $portal,
        private readonly string $apiDomain,
        private readonly string $accountsDomain,
        private readonly ?string $proxy = null,
    ) {
    }

    /**
     * El refresh_token es permanente (canjeado una sola vez desde el grant
     * token), pero los access tokens que produce duran ~1h — se cachean 50
     * min (igual que el token de Microsoft Graph) para no pedir uno nuevo
     * en cada llamada sin arriesgarse a usar uno ya vencido.
     */
    public function accessToken(): string
    {
        return Cache::remember(
            "sdp-access-token:{$this->clientId}",
            now()->addMinutes(50),
            function () {
                $response = $this->baseHttpClient()
                    ->asForm()
                    ->post("{$this->accountsDomain}/oauth/v2/token", [
                        'grant_type' => 'refresh_token',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'refresh_token' => $this->refreshToken,
                    ]);

                if ($response->failed()) {
                    throw new \RuntimeException("No se pudo obtener el token de ServiceDesk Plus ({$response->status()}): {$response->body()}");
                }

                return $response->json('access_token');
            }
        );
    }

    protected function httpClient(): PendingRequest
    {
        return $this->baseHttpClient()->withToken($this->accessToken(), 'Zoho-oauthtoken');
    }

    /**
     * Mismo soporte de forward proxy opcional que
     * `MicrosoftGraphTransport::httpClient()`/`EbsRequisitionsClient::httpClient()`,
     * para cuando el app server no tiene salida directa a internet
     * (confirmado el caso en producción de mdsck — ver
     * docs/mesaservicio-progreso.md).
     */
    private function baseHttpClient(): PendingRequest
    {
        return $this->proxy
            ? Http::withOptions(['proxy' => $this->proxy])
            : Http::withOptions([]);
    }

    /**
     * No existe un recurso/scope "technicians" en la API v3 de SDP (ver
     * docs/servicedesk-plus-oauth.md) — los datos de técnico se obtienen del
     * objeto "technician" embebido en cada ticket de listRequests().
     */
    public function listRequests(array $searchCriteria = [], array $fieldsRequired = [], int $startIndex = 1, int $rowCount = 100): array
    {
        return $this->getListInfo('requests', $searchCriteria, $fieldsRequired, $startIndex, $rowCount);
    }

    /**
     * Catálogo de estados de solicitud configurados en la instancia de SDP
     * (típicamente ~7-10 registros) — trae "in_progress" (bool) y "deleted"
     * (bool) por estado, usados por sdp:sync-ticket-statuses para mapear a
     * tipo en_curso/completado y activo/inactivo respectivamente.
     */
    public function listStatuses(int $startIndex = 1, int $rowCount = 100): array
    {
        return $this->getListInfo('statuses', [], [], $startIndex, $rowCount);
    }

    /**
     * Los listados de la API v3 de SDP van por GET, con "input_data" como
     * parámetro de query string (JSON serializado) — no por POST con cuerpo
     * JSON: la API interpreta un POST como intento de creación y responde
     * "EXTRA_KEY_FOUND_IN_JSON" al no reconocer "list_info" en ese contexto.
     */
    protected function getListInfo(string $resource, array $searchCriteria, array $fieldsRequired, int $startIndex, int $rowCount): array
    {
        $listInfo = array_filter([
            'row_count' => $rowCount,
            'start_index' => $startIndex,
            'search_criteria' => $searchCriteria,
            'fields_required' => $fieldsRequired,
        ], fn ($value) => $value !== []);

        $response = $this->httpClient()
            ->get("{$this->apiDomain}/app/{$this->portal}/api/v3/{$resource}", [
                'input_data' => json_encode(['list_info' => $listInfo]),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("ServiceDesk Plus rechazó la solicitud a \"{$resource}\" ({$response->status()}): {$response->body()}");
        }

        return $response->json();
    }
}
