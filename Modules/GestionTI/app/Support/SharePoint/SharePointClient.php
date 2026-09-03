<?php

namespace Modules\GestionTI\Support\SharePoint;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente para Microsoft Graph que sube/lista archivos de la biblioteca de
 * documentos default de un sitio de SharePoint — usado por
 * `Modules\GestionTI\Models\DocumentoDigitalizado::storeUploaded()` (Fase 5).
 * Autenticación OAuth2 client credentials, mismo mecanismo/patrón que
 * `App\Mail\Transport\MicrosoftGraphTransport` (sin SDK, solo
 * `Illuminate\Support\Facades\Http`), reutilizando el mismo App Registration
 * de Azure AD (tenant/client id/secret) ya usado para correo — solo cambia
 * el permiso de aplicación consentido (`Sites.Selected` en vez de
 * `Mail.Send`).
 *
 * El id de sitio (`site-id`) y el id del drive default de ese sitio
 * (`drive-id`) se resuelven en tiempo real por hostname+ruta (no hay
 * `site_id` fijo en `.env`, más portable entre entornos) y se cachean
 * (nunca cambian una vez creado el sitio) — `subirArchivo()`/`listarArchivos()`
 * dirigen sus llamadas a `/drives/{drive-id}/root:/...` (equivalente a
 * `/sites/{site-id}/drive/root:/...` según la documentación de Graph, pero
 * evita repetir la resolución "drive default de este sitio" en cada
 * llamada una vez que el id ya está cacheado).
 */
class SharePointClient
{
    /**
     * @param  array<string, ?string>  $carpetas  Mapa tipo_documento => carpeta configurada (o null si no se configuró todavía).
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $siteHostname,
        private readonly string $sitePath,
        private readonly array $carpetas = [],
        private readonly ?string $proxy = null,
    ) {
    }

    /**
     * Carpeta configurada (vía `.env`, ver `config('services.sharepoint.carpetas')`)
     * para un `tipo_documento` dado. El mapeo es intencionalmente extensible
     * a los 5 valores de `ConfiguracionDocumentos::TIPOS_DOCUMENTO` aunque
     * hoy solo 2 ("responsiva"/"remision_proveedor") tengan una carpeta real
     * confirmada — si se activa en la pantalla de configuración un tipo sin
     * carpeta configurada todavía, se lanza un error claro en vez de
     * inventar una carpeta genérica de respaldo (una carpeta que no existe
     * en SharePoint fallaría igual al primer intento de subida, así que no
     * hay ganancia en fingir un fallback silencioso).
     */
    public function carpetaParaTipoDocumento(string $tipoDocumento): string
    {
        $carpeta = $this->carpetas[$tipoDocumento] ?? null;

        if (! $carpeta) {
            throw new SharePointException(
                "No hay una carpeta de SharePoint configurada para el tipo de documento \"{$tipoDocumento}\" — ".
                'agrega la variable SHAREPOINT_FOLDER_* correspondiente en .env antes de activarlo en '.
                '"Configuración de Almacenamiento".'
            );
        }

        return $carpeta;
    }

    /**
     * Sube el contenido binario de un archivo a la carpeta configurada para
     * `$tipoDocumento`. Devuelve `['driveItemId' => ..., 'webUrl' => ...]`.
     */
    public function subirArchivoParaTipo(string $tipoDocumento, string $nombreArchivo, string $contenido, string $contentType): array
    {
        return $this->subirArchivo($this->carpetaParaTipoDocumento($tipoDocumento), $nombreArchivo, $contenido, $contentType);
    }

    /**
     * @return array{driveItemId: ?string, webUrl: ?string}
     */
    public function subirArchivo(string $carpeta, string $nombreArchivo, string $contenido, string $contentType): array
    {
        $driveId = $this->resolveDriveId();
        $path = $this->encodePath($carpeta.'/'.$nombreArchivo);

        $response = $this->httpClient()
            ->withToken($this->accessToken())
            ->withBody($contenido, $contentType)
            ->put("https://graph.microsoft.com/v1.0/drives/{$driveId}/root:/{$path}:/content");

        if ($response->failed()) {
            throw new SharePointException(
                "SharePoint rechazó la subida de \"{$nombreArchivo}\" a \"{$carpeta}\" ({$response->status()}): {$response->body()}"
            );
        }

        $item = $response->json() ?? [];

        return [
            'driveItemId' => $item['id'] ?? null,
            'webUrl' => $item['webUrl'] ?? null,
        ];
    }

    /**
     * Lista todos los archivos (no subcarpetas) de la carpeta configurada
     * para `$tipoDocumento` — usado por el modal "Buscar en SharePoint" para
     * vincular un archivo ya existente sin volver a subirlo.
     *
     * @return array<int, array{driveItemId: string, nombre: string, webUrl: string}>
     */
    public function listarArchivosParaTipo(string $tipoDocumento): array
    {
        return $this->listarArchivos($this->carpetaParaTipoDocumento($tipoDocumento));
    }

    /**
     * @return array<int, array{driveItemId: string, nombre: string, webUrl: string}>
     */
    public function listarArchivos(string $carpeta): array
    {
        $driveId = $this->resolveDriveId();
        $path = $this->encodePath($carpeta);

        $archivos = [];
        // $top=200 — carpetas de este tipo de documento no se esperan con
        // más de unas pocas decenas de archivos; la paginación de abajo
        // (@odata.nextLink) sigue cubriendo el caso de que se pase de eso.
        $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/root:/{$path}:/children?\$top=200";

        while ($url) {
            $response = $this->httpClient()->withToken($this->accessToken())->get($url);

            if ($response->failed()) {
                throw new SharePointException(
                    "SharePoint rechazó el listado de \"{$carpeta}\" ({$response->status()}): {$response->body()}"
                );
            }

            $payload = $response->json() ?? [];

            foreach ($payload['value'] ?? [] as $item) {
                // Solo archivos — Graph distingue archivo de carpeta con la
                // presencia de la faceta `file` (las carpetas traen `folder`
                // en su lugar).
                if (! isset($item['file'])) {
                    continue;
                }

                $archivos[] = [
                    'driveItemId' => $item['id'],
                    'nombre' => $item['name'],
                    'webUrl' => $item['webUrl'],
                ];
            }

            $url = $payload['@odata.nextLink'] ?? null;
        }

        return $archivos;
    }

    /**
     * `site-id` de `{hostname}:{sitePath}` — no cambia una vez creado el
     * sitio, cacheado permanentemente en la práctica (24h, se refresca solo
     * si la caché expira).
     */
    private function resolveSiteId(): string
    {
        return Cache::remember(
            "sharepoint-site-id:{$this->siteHostname}:{$this->sitePath}",
            now()->addDay(),
            function () {
                $response = $this->httpClient()
                    ->withToken($this->accessToken())
                    ->get("https://graph.microsoft.com/v1.0/sites/{$this->siteHostname}:{$this->sitePath}");

                if ($response->failed()) {
                    throw new SharePointException(
                        "No se pudo resolver el sitio de SharePoint \"{$this->siteHostname}{$this->sitePath}\" ({$response->status()}): {$response->body()}"
                    );
                }

                $siteId = $response->json('id');

                if (! $siteId) {
                    throw new SharePointException(
                        "Graph no devolvió un id de sitio válido para \"{$this->siteHostname}{$this->sitePath}\"."
                    );
                }

                return $siteId;
            }
        );
    }

    /**
     * `drive-id` de la biblioteca de documentos default del sitio — igual
     * de estable que el site-id, cacheado por site-id.
     */
    private function resolveDriveId(): string
    {
        $siteId = $this->resolveSiteId();

        return Cache::remember(
            "sharepoint-drive-id:{$siteId}",
            now()->addDay(),
            function () use ($siteId) {
                $response = $this->httpClient()
                    ->withToken($this->accessToken())
                    ->get("https://graph.microsoft.com/v1.0/sites/{$siteId}/drive");

                if ($response->failed()) {
                    throw new SharePointException(
                        "No se pudo resolver el drive default del sitio de SharePoint ({$response->status()}): {$response->body()}"
                    );
                }

                $driveId = $response->json('id');

                if (! $driveId) {
                    throw new SharePointException('Graph no devolvió un id de drive válido para el sitio de SharePoint.');
                }

                return $driveId;
            }
        );
    }

    /**
     * Token de aplicación cacheado (~50 min) — mismo patrón exacto que
     * `App\Mail\Transport\MicrosoftGraphTransport::accessToken()`, con su
     * propia clave de caché (aunque tenant/client sean los mismos, un token
     * emitido con scope `.default` es válido para cualquier permiso ya
     * consentido de esa app, así que en la práctica ambas cachés podrían
     * compartirse — se mantienen separadas solo para no acoplar este
     * cliente al nombre de caché de la integración de correo).
     */
    private function accessToken(): string
    {
        return Cache::remember(
            "microsoft-graph-sharepoint-token:{$this->tenantId}:{$this->clientId}",
            now()->addMinutes(50),
            function () {
                $response = $this->httpClient()
                    ->asForm()
                    ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => 'https://graph.microsoft.com/.default',
                    ]);

                if ($response->failed()) {
                    throw new SharePointException("No se pudo obtener el token de Azure AD ({$response->status()}): {$response->body()}");
                }

                return $response->json('access_token');
            }
        );
    }

    /**
     * Cliente HTTP para hablar con Azure AD/Graph — mismo soporte de forward
     * proxy opcional que `MicrosoftGraphTransport::httpClient()`.
     */
    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->proxy
            ? Http::withOptions(['proxy' => $this->proxy])
            : Http::withOptions([]);
    }

    /**
     * Codifica cada segmento de una ruta tipo "Carpeta/Subcarpeta/archivo.pdf"
     * por separado (para no escapar las `/` que separan segmentos) — Graph
     * espera los segmentos del path-based addressing individualmente
     * codificados, no la ruta completa como un solo componente.
     */
    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($path, '/'))));
    }
}
