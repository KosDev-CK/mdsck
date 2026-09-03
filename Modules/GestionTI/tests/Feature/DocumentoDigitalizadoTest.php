<?php

namespace Modules\GestionTI\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\GestionTI\Models\ConfiguracionDocumentos;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Support\SharePoint\SharePointClient;
use Tests\TestCase;

/**
 * `Empresa` se usa como "entidad" cualquiera en estos tests solo porque
 * `storeUploaded()`/`linkExisting()` únicamente necesitan `class_basename()`
 * + `getKey()` de ella — no hay relación real entre `DocumentoDigitalizado`
 * y `Empresa`, mismo criterio ya usado en
 * `AsignacionesTest::test_attach_action_does_not_allow_a_second_file_...`.
 */
class DocumentoDigitalizadoTest extends TestCase
{
    use RefreshDatabase;

    protected function configureSharePoint(array $carpetas = ['sic' => 'Carpeta SIC']): void
    {
        config([
            'services.sharepoint.tenant_id' => 'tenant-1',
            'services.sharepoint.client_id' => 'client-1',
            'services.sharepoint.client_secret' => 'secret-1',
            'services.sharepoint.site_hostname' => 'grupokosmosmexico.sharepoint.com',
            'services.sharepoint.site_path' => '/sites/Landit',
            'services.sharepoint.carpetas' => $carpetas,
        ]);

        // Fuerza al contenedor a reconstruir el singleton con la config de
        // arriba — `GestionTIServiceProvider::register()` ya corrió con la
        // config default (vacía) antes de que este test la sobreescribiera.
        $this->app->forgetInstance(SharePointClient::class);
    }

    protected function fakeGraphSuccess(string $driveItemId = 'item-1', string $webUrl = 'https://grupokosmosmexico.sharepoint.com/sites/Landit/archivo.pdf'): void
    {
        Http::fake(function ($request) use ($driveItemId, $webUrl) {
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

            if ($request->method() === 'PUT') {
                return Http::response(['id' => $driveItemId, 'webUrl' => $webUrl, 'name' => 'archivo.pdf']);
            }

            return Http::response([], 404);
        });
    }

    private function entidad(): Empresa
    {
        return Empresa::create(['razon_social' => 'Grupo Profesional', 'nombre_comercial' => 'Grupo Profesional']);
    }

    public function test_store_uploaded_stays_local_when_the_type_is_not_marked_for_sharepoint(): void
    {
        Storage::fake('public');
        ConfiguracionDocumentos::current()->update(['tipos_sharepoint' => []]);

        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $documento = DocumentoDigitalizado::storeUploaded($file, $this->entidad(), 'sic', null);

        $this->assertSame('local', $documento->proveedor_almacenamiento);
        $this->assertNull($documento->url_externa);
        Storage::disk('public')->assertExists($documento->referencia);
    }

    public function test_store_uploaded_goes_to_sharepoint_when_the_type_is_marked(): void
    {
        ConfiguracionDocumentos::current()->update(['tipos_sharepoint' => ['sic']]);
        $this->configureSharePoint(['sic' => 'Carpeta SIC']);
        $this->fakeGraphSuccess('drive-item-42', 'https://grupokosmosmexico.sharepoint.com/sites/Landit/doc.pdf');

        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $documento = DocumentoDigitalizado::storeUploaded($file, $this->entidad(), 'sic', null);

        $this->assertSame('sharepoint', $documento->proveedor_almacenamiento);
        $this->assertSame('drive-item-42', $documento->referencia);
        $this->assertSame('https://grupokosmosmexico.sharepoint.com/sites/Landit/doc.pdf', $documento->url_externa);
        $this->assertSame('doc.pdf', $documento->nombre_archivo);
    }

    public function test_store_uploaded_does_not_create_a_record_when_graph_fails(): void
    {
        ConfiguracionDocumentos::current()->update(['tipos_sharepoint' => ['sic']]);
        $this->configureSharePoint(['sic' => 'Carpeta SIC']);

        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://login.microsoftonline.com/')) {
                return Http::response(['access_token' => 'fake-token']);
            }

            // Todo lo demás (resolver sitio) falla.
            return Http::response('forbidden', 403);
        });

        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $this->expectException(\Modules\GestionTI\Support\SharePoint\SharePointException::class);

        try {
            DocumentoDigitalizado::storeUploaded($file, $this->entidad(), 'sic', null);
        } finally {
            $this->assertSame(0, DocumentoDigitalizado::count());
        }
    }

    public function test_store_uploaded_throws_when_the_type_is_marked_but_no_folder_is_configured(): void
    {
        ConfiguracionDocumentos::current()->update(['tipos_sharepoint' => ['factura']]);
        // "factura" no está en el mapa de carpetas configuradas.
        $this->configureSharePoint(['sic' => 'Carpeta SIC']);

        $file = UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf');

        $this->expectException(\Modules\GestionTI\Support\SharePoint\SharePointException::class);

        try {
            DocumentoDigitalizado::storeUploaded($file, $this->entidad(), 'factura', null);
        } finally {
            $this->assertSame(0, DocumentoDigitalizado::count());
        }
    }

    public function test_link_existing_creates_a_sharepoint_record_without_uploading_anything(): void
    {
        Http::fake(fn () => Http::response('no debería llamarse a Graph', 500));

        $documento = DocumentoDigitalizado::linkExisting(
            ['driveItemId' => 'existing-1', 'nombre' => 'ya-existente.pdf', 'webUrl' => 'https://example/ya-existente.pdf'],
            $this->entidad(),
            'responsiva',
            null
        );

        $this->assertSame('sharepoint', $documento->proveedor_almacenamiento);
        $this->assertSame('existing-1', $documento->referencia);
        $this->assertSame('ya-existente.pdf', $documento->nombre_archivo);
        $this->assertSame('https://example/ya-existente.pdf', $documento->url_externa);
        Http::assertNothingSent();
    }

    public function test_url_accessor_resolves_correctly_for_local_and_sharepoint(): void
    {
        Storage::fake('public');

        $local = DocumentoDigitalizado::create([
            'entidad_relacionada' => 'Empresa',
            'entidad_id' => 1,
            'tipo_documento' => 'sic',
            'proveedor_almacenamiento' => 'local',
            'referencia' => 'documentos-digitalizados/archivo.pdf',
            'nombre_archivo' => 'archivo.pdf',
            'fecha_subida' => now(),
        ]);

        $this->assertSame(Storage::disk('public')->url('documentos-digitalizados/archivo.pdf'), $local->url());

        $sharepoint = DocumentoDigitalizado::create([
            'entidad_relacionada' => 'Empresa',
            'entidad_id' => 1,
            'tipo_documento' => 'responsiva',
            'proveedor_almacenamiento' => 'sharepoint',
            'referencia' => 'drive-item-1',
            'url_externa' => 'https://example/archivo.pdf',
            'nombre_archivo' => 'archivo.pdf',
            'fecha_subida' => now(),
        ]);

        $this->assertSame('https://example/archivo.pdf', $sharepoint->url());
    }
}
