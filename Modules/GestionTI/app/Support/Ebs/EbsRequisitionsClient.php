<?php

namespace Modules\GestionTI\Support\Ebs;

use Illuminate\Support\Facades\Http;

/**
 * Cliente para la API REST de Oracle EBS (Oracle Integration Cloud) que
 * expone las Solicitudes Internas de Compra (SIC) — un solo endpoint, dos
 * "métodos" seleccionados por query string. Basic Auth, body vacío `{}`
 * (raw). Ver docs/gestionti-progreso.md para el detalle completo de ambos
 * métodos y el gotcha de los 2 typos reales del proveedor
 * (`decription`/`requsition_line_id`), que este cliente NO corrige — solo
 * decodifica la respuesta tal cual viene; la corrección de esos nombres
 * vive en las columnas de `EbsRequisitionSyncService`.
 */
class EbsRequisitionsClient
{
    /**
     * Trae TODAS las requisiciones creadas ese día sin importar estatus,
     * con cabecera + líneas.
     */
    public const METHOD_CREADAS = 'requisition_header_line';

    /**
     * Trae SOLO requisiciones ya `APPROVED` aprobadas ese día (sin importar
     * cuándo se crearon), con `notes[]` en vez de `requisition_lines[]`.
     */
    public const METHOD_APROBADAS = 'requisition_header_approved';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $organizationCode,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $proxy = null,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerCreadas(int $daysOffset): array
    {
        return $this->fetch(self::METHOD_CREADAS, $daysOffset);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerAprobadas(int $daysOffset): array
    {
        return $this->fetch(self::METHOD_APROBADAS, $daysOffset);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(string $method, int $daysOffset): array
    {
        $url = $this->baseUrl.'?'.http_build_query([
            'method' => $method,
            'organization_code' => $this->organizationCode,
            'daysoffset' => $daysOffset,
        ]);

        // Body raw JSON vacío "{}" — no un arreglo PHP vacío (json_encode([])
        // produciría "[]", no "{}"), tal como lo espera el flujo de EBS.
        $response = $this->httpClient()
            ->withBasicAuth($this->username, $this->password)
            ->withBody('{}', 'application/json')
            ->post($url);

        if ($response->failed()) {
            throw new EbsRequisitionSyncException(
                "EBS rechazó la solicitud a \"{$method}\" ({$response->status()}): {$response->body()}"
            );
        }

        $payload = $response->json() ?? [];
        $errorCode = $payload['status']['errorCode'] ?? null;

        if ($errorCode !== 0) {
            $errorMsg = $payload['status']['errorMsg'] ?? 'desconocido';

            throw new EbsRequisitionSyncException(
                "EBS respondió con error en \"{$method}\" (errorCode=".json_encode($errorCode)."): {$errorMsg}"
            );
        }

        return $payload['payload']['requisitions'] ?? [];
    }

    /**
     * Cliente HTTP para hablar con Oracle Integration Cloud — mismo soporte
     * de forward proxy opcional que `MicrosoftGraphTransport::httpClient()`
     * y `SharePointClient::httpClient()`, para cuando este servidor no tiene
     * salida directa a internet (confirmado el caso del app server de
     * producción de `mdsck` — ver `docs/gestionti-progreso.md`).
     */
    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->proxy
            ? Http::withOptions(['proxy' => $this->proxy])
            : Http::withOptions([]);
    }
}
