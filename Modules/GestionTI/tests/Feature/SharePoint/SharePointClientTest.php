<?php

namespace Modules\GestionTI\Tests\Feature\SharePoint;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\GestionTI\Support\SharePoint\SharePointClient;
use Modules\GestionTI\Support\SharePoint\SharePointException;
use Tests\TestCase;

class SharePointClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El driver de caché en pruebas es 'array' (ver phpunit.xml) — no se
        // limpia solo entre tests del mismo proceso, y `resolveSiteId()`/
        // `resolveDriveId()` cachean por hostname/sitePath/siteId. Se limpia
        // explícitamente para que cada test controle sus propias llamadas
        // HTTP sin arrastrar cachés de tests anteriores.
        Cache::flush();
    }

    protected function client(array $carpetas = ['responsiva' => 'Responsivas Asignación de Activos']): SharePointClient
    {
        return new SharePointClient(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            clientSecret: 'secret-1',
            siteHostname: 'grupokosmosmexico.sharepoint.com',
            sitePath: '/sites/Landit',
            carpetas: $carpetas,
        );
    }

    protected function fakeGraph(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://login.microsoftonline.com/')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            if (preg_match('#/v1\.0/sites/[^/]+/drive$#', $url)) {
                return Http::response(['id' => 'drive-1']);
            }

            if (str_contains($url, '/v1.0/sites/') && $request->method() === 'GET') {
                return Http::response(['id' => 'site-1']);
            }

            if (str_ends_with(explode('?', $url)[0], ':/content') && $request->method() === 'PUT') {
                return Http::response([
                    'id' => 'item-uploaded',
                    'webUrl' => 'https://grupokosmosmexico.sharepoint.com/sites/Landit/archivo.pdf',
                    'name' => 'archivo.pdf',
                ]);
            }

            if (str_contains($url, ':/children') && $request->method() === 'GET') {
                return Http::response([
                    'value' => [
                        ['id' => 'item-1', 'name' => 'responsiva-uno.pdf', 'webUrl' => 'https://example/uno.pdf', 'file' => []],
                        ['id' => 'item-2', 'name' => 'responsiva-dos.pdf', 'webUrl' => 'https://example/dos.pdf', 'file' => []],
                        // Una "carpeta" (sin faceta `file`) — debe excluirse del resultado.
                        ['id' => 'folder-1', 'name' => 'Subcarpeta', 'folder' => []],
                    ],
                ]);
            }

            return Http::response(['error' => 'unexpected url in test: '.$url], 404);
        });
    }

    public function test_subir_archivo_resolves_site_and_drive_then_uploads_content(): void
    {
        $this->fakeGraph();

        $resultado = $this->client()->subirArchivo('Responsivas Asignación de Activos', 'firmada.pdf', 'contenido-binario', 'application/pdf');

        $this->assertSame('item-uploaded', $resultado['driveItemId']);
        $this->assertSame('https://grupokosmosmexico.sharepoint.com/sites/Landit/archivo.pdf', $resultado['webUrl']);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/root:/Responsivas%20Asignaci')
                && str_contains($request->url(), 'firmada.pdf:/content')
                && $request->body() === 'contenido-binario';
        });
    }

    public function test_subir_archivo_para_tipo_uses_the_configured_folder(): void
    {
        $this->fakeGraph();

        $this->client()->subirArchivoParaTipo('responsiva', 'firmada.pdf', 'x', 'application/pdf');

        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), 'Responsivas'));
    }

    public function test_subir_archivo_para_tipo_throws_when_the_folder_is_not_configured(): void
    {
        $this->fakeGraph();

        $this->expectException(SharePointException::class);
        $this->expectExceptionMessage('No hay una carpeta de SharePoint configurada');

        $this->client(carpetas: [])->subirArchivoParaTipo('factura', 'factura.pdf', 'x', 'application/pdf');
    }

    public function test_listar_archivos_returns_only_files_and_skips_folders(): void
    {
        $this->fakeGraph();

        $archivos = $this->client()->listarArchivosParaTipo('responsiva');

        $this->assertCount(2, $archivos);
        $this->assertSame(['item-1', 'item-2'], array_column($archivos, 'driveItemId'));
        $this->assertSame(['responsiva-uno.pdf', 'responsiva-dos.pdf'], array_column($archivos, 'nombre'));
    }

    public function test_listar_archivos_follows_pagination(): void
    {
        $paginaUno = [
            'value' => [
                ['id' => 'p1', 'name' => 'pagina1.pdf', 'webUrl' => 'https://example/p1.pdf', 'file' => []],
            ],
            '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/drives/drive-1/root:/Carpeta:/children?%24top=200&%24skip=1',
        ];
        $paginaDos = [
            'value' => [
                ['id' => 'p2', 'name' => 'pagina2.pdf', 'webUrl' => 'https://example/p2.pdf', 'file' => []],
            ],
        ];

        $calls = 0;

        Http::fake(function ($request) use (&$calls, $paginaUno, $paginaDos) {
            $url = $request->url();

            if (str_starts_with($url, 'https://login.microsoftonline.com/')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            if (preg_match('#/v1\.0/sites/[^/]+/drive$#', $url)) {
                return Http::response(['id' => 'drive-1']);
            }

            if (str_contains($url, '/v1.0/sites/') && $request->method() === 'GET') {
                return Http::response(['id' => 'site-1']);
            }

            if (str_contains($url, ':/children')) {
                $calls++;

                return Http::response($calls === 1 ? $paginaUno : $paginaDos);
            }

            return Http::response([], 404);
        });

        $archivos = $this->client()->listarArchivosParaTipo('responsiva');

        $this->assertSame(2, $calls);
        $this->assertSame(['p1', 'p2'], array_column($archivos, 'driveItemId'));
    }

    public function test_throws_when_graph_rejects_the_upload(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://login.microsoftonline.com/')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            if (preg_match('#/v1\.0/sites/[^/]+/drive$#', $url)) {
                return Http::response(['id' => 'drive-1']);
            }

            if (str_contains($url, '/v1.0/sites/') && $request->method() === 'GET') {
                return Http::response(['id' => 'site-1']);
            }

            return Http::response('forbidden', 403);
        });

        $this->expectException(SharePointException::class);

        $this->client()->subirArchivo('Responsivas Asignación de Activos', 'firmada.pdf', 'x', 'application/pdf');
    }

    public function test_throws_when_the_site_cannot_be_resolved(): void
    {
        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://login.microsoftonline.com/')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            return Http::response('not found', 404);
        });

        $this->expectException(SharePointException::class);

        $this->client()->subirArchivo('Responsivas Asignación de Activos', 'firmada.pdf', 'x', 'application/pdf');
    }

    public function test_site_and_drive_ids_are_resolved_only_once_thanks_to_caching(): void
    {
        $this->fakeGraph();

        $client = $this->client();
        $client->subirArchivo('Responsivas Asignación de Activos', 'uno.pdf', 'x', 'application/pdf');
        $client->subirArchivo('Responsivas Asignación de Activos', 'dos.pdf', 'x', 'application/pdf');

        // 1 token (cacheado ~50 min, la 2da subida lo reutiliza) + 1
        // resolución de sitio + 1 de drive (ambas cacheadas ~1 día, la 2da
        // subida las reutiliza) + 2 PUT de contenido (uno por archivo,
        // nunca se cachean) = 5, no 8.
        Http::assertSentCount(5);
    }
}
