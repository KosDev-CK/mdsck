<?php

namespace Modules\MesaServicio\Services;

use Illuminate\Http\Client\ConnectionException;
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
     *
     * timeout(60)+retry(3): la instancia real de SDP resultó lenta en
     * páginas profundas de paginación con historiales largos (ej.
     * sdp:sync-technicians sobre 12 meses) — un intento real dio cURL 28
     * ("Operation timed out after 30011 milliseconds") con el timeout por
     * defecto del cliente HTTP. 3 reintentos con 3s de espera absorben esa
     * lentitud puntual sin abortar el comando completo.
     *
     * El reintento se limita a ConnectionException (timeouts, DNS, etc.)
     * vía el callback $when — a propósito NO reintenta una respuesta HTTP
     * fallida (4xx/5xx real, ej. credenciales inválidas): no es un error
     * transitorio que un reintento vaya a resolver. `throw: false` es
     * aparte NECESARIO incluso con el filtro de $when: Laravel decide si
     * lanzar su propia RequestException al agotar intentos según
     * `tries > 1 && retryThrow` — una condición fija que ignora si el
     * callback $when realmente autorizó algún reintento — así que sin
     * throw:false, retry() seguiría reemplazando el RuntimeException
     * propio de getListInfo()/accessToken() por su RequestException aun
     * cuando $when nunca reintentó nada (confirmado con los tests que
     * simulan un 500 vía Http::fake()).
     */
    private function baseHttpClient(): PendingRequest
    {
        $client = $this->proxy
            ? Http::withOptions(['proxy' => $this->proxy])
            : Http::withOptions([]);

        return $client->timeout(60)->retry(
            3,
            3000,
            fn (\Throwable $exception) => $exception instanceof ConnectionException,
            false,
        );
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
     * Historial de eventos de UN ticket específico (Fase 8) — usado por
     * sdp:sync-tickets para detectar operaciones "merge_with" (folios
     * combinados). Confirmado contra la instancia real: mismo patrón de
     * paginación GET/input_data que los demás listados, pero el recurso es
     * por-ticket ("requests/{id}/history") y la clave de nivel superior de
     * la respuesta es "history", no "requests" — sin search_criteria ni
     * fields_required, solo row_count/start_index.
     */
    public function getRequestHistory(string $requestId, int $startIndex = 1, int $rowCount = 100): array
    {
        return $this->getListInfo("requests/{$requestId}/history", [], [], $startIndex, $rowCount);
    }

    /**
     * Catálogo de sitios geográficos configurados en la instancia de SDP
     * (id, name, country, state, region, city, street, door_no,
     * postal_code, location, landmark, timezone) — confirmado contra la
     * instancia real. Recurso "sites", sin search_criteria/fields_required:
     * el endpoint ya regresa el objeto completo por sitio.
     */
    public function listSites(int $startIndex = 1, int $rowCount = 100): array
    {
        return $this->getListInfo('sites', [], [], $startIndex, $rowCount);
    }

    /**
     * Catálogo de usuarios de la instancia (recurso "users", API v3 real,
     * scope SDPOnDemand.users.ALL) — confirmado contra la instancia real con
     * `search_criteria` sobre `is_technician` (condición "is", valor
     * booleano). A diferencia de `listRequests()`, cada usuario trae `zuid`
     * (id de cuenta de login de Zoho/SDP — "-1" si el usuario no tiene un
     * login real, un valor numérico si sí lo tiene), usado por
     * sdp:sync-technicians para derivar `tiene_acceso_sdp`.
     */
    public function listUsers(array $searchCriteria = [], array $fieldsRequired = [], int $startIndex = 1, int $rowCount = 100): array
    {
        return $this->getListInfo('users', $searchCriteria, $fieldsRequired, $startIndex, $rowCount);
    }

    /**
     * Catálogos de configuración ("setup") de SDP — genérico sobre los 12
     * recursos confirmados (categories, levels, modes, impacts, urgencies,
     * priorities, priority_matrices, request_types, task_types,
     * worklog_types, closure_codes, downtime_types): mismo patrón GET +
     * input_data que el resto, sin search_criteria/fields_required (cada uno
     * ya regresa el objeto completo). Usado por sdp:sync-catalogos.
     */
    public function listCatalog(string $resource, int $startIndex = 1, int $rowCount = 100): array
    {
        return $this->getListInfo($resource, [], [], $startIndex, $rowCount);
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
