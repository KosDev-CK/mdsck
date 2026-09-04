<?php

namespace Modules\GestionTI\Tests\Feature\Compras;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Compras\Facturas;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\RecepcionLinea;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacturasTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Compras',
            'name' => 'Facturación',
            'slug' => 'gestionti-facturas',
            'route_name' => 'gestionti.facturas.index',
            'permission_name' => 'screens.gestionti-facturas.manage',
            'icon' => 'document-currency-dollar',
            'order' => 23,
        ]);

        $role = Role::findOrCreate('Contabilidad/Finanzas', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function proveedor(array $overrides = []): Proveedor
    {
        return Proveedor::create(array_merge([
            'razon_social' => 'Distribuidora Kosmos S.A. de C.V.',
            'nombre_comercial' => 'Distribuidora Kosmos',
        ], $overrides));
    }

    private function validador(): Validador
    {
        return Validador::create(['nombre' => 'Ana Torres']);
    }

    private function ubicacion(): Ubicacion
    {
        return Ubicacion::create(['nombre' => 'Almacén Central']);
    }

    /**
     * Arma 1 línea + 1 Recepcion + 1 RecepcionLinea para una SolicitudProveedor
     * ya existente. `cantidad_recibida * precio_unitario_cotizado` define el
     * "total cotizado" real de esa remisión, que el componente usa para
     * calcular `diferencia_a_revisar`. `con_asset` crea además un Asset
     * inventariable vinculado a la línea (para probar Asset.invoice_id).
     */
    private function recepcionParaSolicitud(SolicitudProveedor $solicitud, array $opts = []): Recepcion
    {
        $cantidadRecibida = $opts['cantidad_recibida'] ?? 2;
        $precioUnitario = $opts['precio_unitario_cotizado'] ?? 100.0;
        $conAsset = $opts['con_asset'] ?? false;

        $linea = $solicitud->lineas()->create([
            'descripcion_libre' => $opts['descripcion'] ?? 'Artículo de prueba',
            'cantidad_solicitada' => $cantidadRecibida,
            'cantidad_recibida' => $cantidadRecibida,
            'precio_unitario_cotizado' => $precioUnitario,
            'es_activo_inventariable' => $conAsset,
        ]);

        $recepcion = Recepcion::create([
            'solicitud_proveedor_id' => $solicitud->id,
            'folio_remision' => $opts['folio_remision'] ?? ('REM-'.uniqid()),
            'fecha_recepcion' => '2026-08-15',
            'recibido_por_id' => $this->validador()->id,
            'ubicacion_id' => $this->ubicacion()->id,
        ]);

        $assetId = null;

        if ($conAsset) {
            Asset::resetCodigoSequenceCache();
            $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
            $estatus = EstatusActivo::firstOrCreate(['codigo' => 'en_stock'], ['nombre' => 'En stock']);

            $asset = Asset::create([
                'codigo' => Asset::generateCodigo($tipoEquipo),
                'tipo_equipo_id' => $tipoEquipo->id,
                'origen_tipo' => 'compra',
                'estatus_id' => $estatus->id,
            ]);
            $assetId = $asset->id;
        }

        RecepcionLinea::create([
            'recepcion_id' => $recepcion->id,
            'solicitud_proveedor_linea_id' => $linea->id,
            'cantidad_recibida' => $cantidadRecibida,
            'asset_id' => $assetId,
        ]);

        return $recepcion->load('solicitudProveedor');
    }

    private function recepcionFacturable(Proveedor $vendor, array $opts = []): Recepcion
    {
        $solicitud = SolicitudProveedor::create([
            'folio' => $opts['solicitud_folio'] ?? ('SP-FAC-'.uniqid()),
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
            'estatus' => $opts['solicitud_estatus'] ?? SolicitudProveedor::ESTATUS_RECIBIDA,
        ]);

        return $this->recepcionParaSolicitud($solicitud, $opts);
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/facturas')->assertForbidden();
    }

    public function test_can_create_invoice_without_recepciones_flags_diferencia_a_revisar(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-001')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 100)
            ->set('form.moneda', 'MXN')
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::where('folio_factura', 'FAC-001')->firstOrFail();
        $this->assertTrue($invoice->diferencia_a_revisar);
        $this->assertSame(Invoice::ESTATUS_RECIBIDA, $invoice->estatus);
    }

    public function test_linking_a_recepcion_whose_total_matches_monto_clears_diferencia(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $recepcion = $this->recepcionFacturable($vendor, ['cantidad_recibida' => 2, 'precio_unitario_cotizado' => 50]);

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-002')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 100)
            ->set('recepcionIds', [$recepcion->id])
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::where('folio_factura', 'FAC-002')->firstOrFail();
        $this->assertFalse($invoice->diferencia_a_revisar);
        $this->assertTrue($invoice->recepciones->pluck('id')->contains($recepcion->id));
    }

    public function test_linking_a_recepcion_whose_total_does_not_match_monto_flags_diferencia(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $recepcion = $this->recepcionFacturable($vendor, ['cantidad_recibida' => 2, 'precio_unitario_cotizado' => 50]);

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-003')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 150)
            ->set('recepcionIds', [$recepcion->id])
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::where('folio_factura', 'FAC-003')->firstOrFail();
        $this->assertTrue($invoice->diferencia_a_revisar);
    }

    public function test_solicitud_transitions_to_facturada_only_when_all_its_recepciones_are_invoiced(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-FAC-MULTI',
            'vendor_id' => $vendor->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
            'estatus' => SolicitudProveedor::ESTATUS_RECIBIDA,
        ]);

        $recepcionA = $this->recepcionParaSolicitud($solicitud, [
            'cantidad_recibida' => 1, 'precio_unitario_cotizado' => 100, 'folio_remision' => 'REM-A',
        ]);
        $recepcionB = $this->recepcionParaSolicitud($solicitud, [
            'cantidad_recibida' => 1, 'precio_unitario_cotizado' => 100, 'folio_remision' => 'REM-B',
        ]);

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-PARCIAL')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 100)
            ->set('recepcionIds', [$recepcionA->id])
            ->call('save')
            ->assertHasNoErrors();

        // Solo 1 de las 2 remisiones de la solicitud tiene factura — no
        // transiciona todavía.
        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $solicitud->fresh()->estatus);

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-COMPLETA')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-02')
            ->set('form.monto_total', 100)
            ->set('recepcionIds', [$recepcionB->id])
            ->call('save')
            ->assertHasNoErrors();

        // Ahora ambas remisiones tienen factura — transiciona.
        $this->assertSame(SolicitudProveedor::ESTATUS_FACTURADA, $solicitud->fresh()->estatus);
    }

    public function test_asset_invoice_id_is_filled_for_inventariable_lines_of_linked_recepciones(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();
        $recepcion = $this->recepcionFacturable($vendor, [
            'cantidad_recibida' => 1, 'precio_unitario_cotizado' => 100, 'con_asset' => true,
        ]);

        $lineaRecepcion = RecepcionLinea::where('recepcion_id', $recepcion->id)->firstOrFail();
        $asset = Asset::findOrFail($lineaRecepcion->asset_id);
        $this->assertNull($asset->invoice_id);

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-ASSET')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 100)
            ->set('recepcionIds', [$recepcion->id])
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::where('folio_factura', 'FAC-ASSET')->firstOrFail();
        $this->assertSame($invoice->id, $asset->fresh()->invoice_id);
    }

    public function test_status_transitions_are_sequential_and_cannot_be_skipped(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $invoice = Invoice::create([
            'folio_factura' => 'FAC-SEQ',
            'vendor_id' => $vendor->id,
            'fecha_recepcion' => '2026-09-01',
            'monto_total' => 100,
            'moneda' => 'MXN',
        ]);

        // No se puede saltar directo de recibida a autorizada ni a pagada.
        Livewire::test(Facturas::class)->call('marcarAutorizada', $invoice->id);
        $this->assertSame(Invoice::ESTATUS_RECIBIDA, $invoice->fresh()->estatus);

        Livewire::test(Facturas::class)->call('marcarPagada', $invoice->id);
        $this->assertSame(Invoice::ESTATUS_RECIBIDA, $invoice->fresh()->estatus);

        Livewire::test(Facturas::class)->call('marcarRegistrada', $invoice->id);
        $this->assertSame(Invoice::ESTATUS_REGISTRADA, $invoice->fresh()->estatus);

        // Todavía no puede saltar de registrada a pagada.
        Livewire::test(Facturas::class)->call('marcarPagada', $invoice->id);
        $this->assertSame(Invoice::ESTATUS_REGISTRADA, $invoice->fresh()->estatus);

        Livewire::test(Facturas::class)->call('marcarAutorizada', $invoice->id);
        $invoice->refresh();
        $this->assertSame(Invoice::ESTATUS_AUTORIZADA, $invoice->estatus);
        $this->assertNotNull($invoice->fecha_autorizacion);

        Livewire::test(Facturas::class)->call('marcarPagada', $invoice->id);
        $invoice->refresh();
        $this->assertSame(Invoice::ESTATUS_PAGADA, $invoice->estatus);
        $this->assertNotNull($invoice->fecha_pago);
    }

    public function test_folio_is_unique_per_vendor_but_shared_across_vendors(): void
    {
        $this->actingAs($this->actingUser());
        $vendorA = $this->proveedor();
        $vendorB = $this->proveedor(['razon_social' => 'Otro Proveedor', 'nombre_comercial' => 'Otro Proveedor']);

        Invoice::create([
            'folio_factura' => 'FAC-DUP',
            'vendor_id' => $vendorA->id,
            'fecha_recepcion' => '2026-09-01',
            'monto_total' => 100,
            'moneda' => 'MXN',
        ]);

        // Mismo proveedor, mismo folio -> rechazado.
        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-DUP')
            ->set('form.vendor_id', $vendorA->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 200)
            ->call('save')
            ->assertHasErrors(['form.folio_factura']);

        // Proveedor distinto, mismo folio -> permitido.
        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-DUP')
            ->set('form.vendor_id', $vendorB->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 200)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('invoices', ['folio_factura' => 'FAC-DUP', 'vendor_id' => $vendorB->id]);
    }

    public function test_solo_diferencia_filter(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        Invoice::create([
            'folio_factura' => 'FAC-DIF', 'vendor_id' => $vendor->id, 'fecha_recepcion' => '2026-09-01',
            'monto_total' => 100, 'moneda' => 'MXN', 'diferencia_a_revisar' => true,
        ]);
        Invoice::create([
            'folio_factura' => 'FAC-OK', 'vendor_id' => $vendor->id, 'fecha_recepcion' => '2026-09-01',
            'monto_total' => 100, 'moneda' => 'MXN', 'diferencia_a_revisar' => false,
        ]);

        $component = Livewire::test(Facturas::class)->set('soloDiferencia', true);
        $folios = $component->viewData('records')->pluck('folio_factura')->all();
        $this->assertContains('FAC-DIF', $folios);
        $this->assertNotContains('FAC-OK', $folios);
    }

    public function test_search_by_folio_and_vendor(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        Invoice::create([
            'folio_factura' => 'FAC-SEARCH-001', 'vendor_id' => $vendor->id, 'fecha_recepcion' => '2026-09-01',
            'monto_total' => 100, 'moneda' => 'MXN',
        ]);
        Invoice::create([
            'folio_factura' => 'FAC-OTRO-002', 'vendor_id' => $vendor->id, 'fecha_recepcion' => '2026-09-02',
            'monto_total' => 100, 'moneda' => 'MXN',
        ]);

        $component = Livewire::test(Facturas::class)->set('search', 'SEARCH');
        $folios = $component->viewData('records')->pluck('folio_factura')->all();
        $this->assertContains('FAC-SEARCH-001', $folios);
        $this->assertNotContains('FAC-OTRO-002', $folios);

        $component = Livewire::test(Facturas::class)->set('search', 'Kosmos');
        $folios = $component->viewData('records')->pluck('folio_factura')->all();
        $this->assertContains('FAC-SEARCH-001', $folios);
        $this->assertContains('FAC-OTRO-002', $folios);
    }

    public function test_can_edit_an_existing_invoice_to_add_a_recepcion_found_later(): void
    {
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $invoice = Invoice::create([
            'folio_factura' => 'FAC-EDIT',
            'vendor_id' => $vendor->id,
            'fecha_recepcion' => '2026-09-01',
            'monto_total' => 100,
            'moneda' => 'MXN',
            'diferencia_a_revisar' => true,
        ]);

        $recepcion = $this->recepcionFacturable($vendor, ['cantidad_recibida' => 2, 'precio_unitario_cotizado' => 50]);

        Livewire::test(Facturas::class)
            ->call('edit', $invoice->id)
            ->assertSet('form.folio_factura', 'FAC-EDIT')
            ->set('recepcionIds', [$recepcion->id])
            ->call('save')
            ->assertHasNoErrors();

        $invoice->refresh();
        $this->assertFalse($invoice->diferencia_a_revisar);
        $this->assertTrue($invoice->recepciones->pluck('id')->contains($recepcion->id));
    }

    public function test_uploading_an_attachment_creates_a_documento_digitalizado(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $file = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-ADJ')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 100)
            ->set('adjunto', $file)
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::where('folio_factura', 'FAC-ADJ')->firstOrFail();

        $this->assertDatabaseHas('documentos_digitalizados', [
            'entidad_relacionada' => 'Invoice',
            'entidad_id' => $invoice->id,
            'tipo_documento' => 'factura',
            'nombre_archivo' => 'factura.pdf',
        ]);

        $documento = $invoice->documentoAdjunto();
        $this->assertNotNull($documento);
        Storage::disk('public')->assertExists($documento->referencia);
    }

    /**
     * El PDF y el XML/CFDI son adjuntos independientes (`tipo_documento`
     * distinto) — pueden subirse juntos o por separado, y `documentoAdjunto()`
     * ya no los confunde entre sí (antes de este cambio no filtraba por tipo).
     */
    public function test_uploading_pdf_and_xml_together_creates_two_independent_documentos(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $pdf = UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf');
        $xml = UploadedFile::fake()->create('factura.xml', 10, 'text/xml');

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-PDF-XML')
            ->set('form.vendor_id', $vendor->id)
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.monto_total', 100)
            ->set('adjunto', $pdf)
            ->set('adjuntoXml', $xml)
            ->call('save')
            ->assertHasNoErrors();

        $invoice = Invoice::where('folio_factura', 'FAC-PDF-XML')->firstOrFail();

        $documentoPdf = $invoice->documentoAdjunto('factura');
        $documentoXml = $invoice->documentoAdjunto('factura_xml');

        $this->assertNotNull($documentoPdf);
        $this->assertNotNull($documentoXml);
        $this->assertNotSame($documentoPdf->id, $documentoXml->id);
        $this->assertSame('factura.pdf', $documentoPdf->nombre_archivo);
        $this->assertSame('factura.xml', $documentoXml->nombre_archivo);
    }

    /**
     * "Quitar" borra únicamente el `DocumentoDigitalizado` del tipo indicado
     * — el otro adjunto (y el archivo físico de ambos) no se toca.
     */
    public function test_quitar_adjunto_unlinks_only_the_given_type_without_deleting_the_physical_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $invoice = Invoice::create([
            'folio_factura' => 'FAC-QUITAR',
            'vendor_id' => $vendor->id,
            'fecha_recepcion' => '2026-09-01',
            'monto_total' => 100,
            'moneda' => 'MXN',
            'estatus' => Invoice::ESTATUS_RECIBIDA,
        ]);

        $documentoPdf = \Modules\GestionTI\Models\DocumentoDigitalizado::storeUploaded(
            UploadedFile::fake()->create('equivocado.pdf', 50, 'application/pdf'),
            $invoice,
            'factura',
            null
        );
        $documentoXml = \Modules\GestionTI\Models\DocumentoDigitalizado::storeUploaded(
            UploadedFile::fake()->create('correcto.xml', 10, 'text/xml'),
            $invoice,
            'factura_xml',
            null
        );

        Livewire::test(Facturas::class)
            ->call('quitarAdjunto', $invoice->id, 'factura');

        $this->assertDatabaseMissing('documentos_digitalizados', ['id' => $documentoPdf->id]);
        $this->assertDatabaseHas('documentos_digitalizados', ['id' => $documentoXml->id]);
        Storage::disk('public')->assertExists($documentoPdf->referencia);
        $this->assertNull($invoice->documentoAdjunto('factura'));
        $this->assertNotNull($invoice->documentoAdjunto('factura_xml'));
    }

    /**
     * "Ver XML" interpreta el CFDI real (vía `CfdiReader`) y expone sus
     * campos clave a la vista — verificado aquí a nivel de propiedad del
     * componente, la vista solo los imprime.
     */
    public function test_ver_xml_parses_a_real_cfdi_and_exposes_it_to_the_view(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $vendor = $this->proveedor();

        $invoice = Invoice::create([
            'folio_factura' => 'FAC-VER-XML',
            'vendor_id' => $vendor->id,
            'fecha_recepcion' => '2026-09-01',
            'monto_total' => 500,
            'moneda' => 'MXN',
            'estatus' => Invoice::ESTATUS_RECIBIDA,
        ]);

        $cfdi = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
                Version="4.0" Fecha="2026-09-01T09:00:00" Total="500.00" Moneda="MXN">
                <cfdi:Emisor Rfc="PRV010101AB1" Nombre="Proveedor de Prueba" />
                <cfdi:Receptor Rfc="KOS020202XY2" Nombre="Kosmos" />
                <cfdi:Complemento>
                    <tfd:TimbreFiscalDigital UUID="AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE" />
                </cfdi:Complemento>
            </cfdi:Comprobante>
            XML;

        \Modules\GestionTI\Models\DocumentoDigitalizado::storeUploaded(
            UploadedFile::fake()->createWithContent('cfdi.xml', $cfdi),
            $invoice,
            'factura_xml',
            null
        );

        $component = Livewire::test(Facturas::class)
            ->call('verXml', $invoice->id)
            ->assertSet('showXmlModal', true);

        $preview = $component->get('xmlPreview');
        $this->assertSame('cfdi.xml', $preview['nombre']);
        $this->assertSame('AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE', $preview['parsed']['uuid']);
        $this->assertSame('PRV010101AB1', $preview['parsed']['emisor_rfc']);
        $this->assertStringContainsString('<cfdi:Comprobante', $preview['raw']);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-facturas',
            'route_name' => 'gestionti.facturas.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-facturas.manage'));
    }
}
