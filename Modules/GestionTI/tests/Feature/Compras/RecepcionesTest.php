<?php

namespace Modules\GestionTI\Tests\Feature\Compras;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Compras\Recepciones;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecepcionesTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Compras',
            'name' => 'Recepción de Proveedor',
            'slug' => 'gestionti-recepciones',
            'route_name' => 'gestionti.recepciones.index',
            'permission_name' => 'screens.gestionti-recepciones.manage',
            'icon' => 'inbox-arrow-down',
            'order' => 22,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function estatusEnStock(): EstatusActivo
    {
        return EstatusActivo::firstOrCreate(['codigo' => 'en_stock'], ['nombre' => 'En stock']);
    }

    private function estatusReservado(): EstatusActivo
    {
        return EstatusActivo::firstOrCreate(['codigo' => 'reservado'], ['nombre' => 'Reservado']);
    }

    private function validador(): Validador
    {
        return Validador::create(['nombre' => 'Ana Torres']);
    }

    private function ubicacion(): Ubicacion
    {
        return Ubicacion::create(['nombre' => 'Almacén Central']);
    }

    private function proveedor(): Proveedor
    {
        return Proveedor::create([
            'razon_social' => 'Distribuidora Kosmos S.A. de C.V.',
            'nombre_comercial' => 'Distribuidora Kosmos',
        ]);
    }

    /**
     * SolicitudProveedor con 1 línea inventariable (articulo con tipo de
     * equipo ya resuelto) por default; `$overrides` permite personalizar la
     * línea o el encabezado para los demás escenarios de prueba.
     */
    private function solicitudConLineaInventariable(array $solicitudOverrides = [], array $lineaOverrides = []): SolicitudProveedor
    {
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-LAPTOP',
            'descripcion' => 'Laptop estándar',
            'unidad_medida' => 'Pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
        ]);

        $solicitud = SolicitudProveedor::create(array_merge([
            'folio' => 'SP-REC-'.uniqid(),
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ], $solicitudOverrides));

        $solicitud->lineas()->create(array_merge([
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 2,
            'cantidad_recibida' => 0,
            'precio_unitario_cotizado' => 15000,
            'es_activo_inventariable' => true,
        ], $lineaOverrides));

        return $solicitud->load('lineas');
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/recepciones')->assertForbidden();
    }

    public function test_solicitud_options_are_filtered_to_receivable_statuses_only(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();

        $solicitada = $this->solicitudConLineaInventariable();
        $parcial = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA]);
        $recibida = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_RECIBIDA]);
        $cancelada = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_CANCELADA]);

        $ids = Livewire::test(Recepciones::class)->viewData('solicitudOptions')->pluck('id')->all();

        $this->assertContains($solicitada->id, $ids);
        $this->assertContains($parcial->id, $ids);
        $this->assertNotContains($recibida->id, $ids);
        $this->assertNotContains($cancelada->id, $ids);
    }

    public function test_receiving_an_inventariable_line_creates_assets_with_sequential_codigo_and_no_collision_with_historical_import(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'Dell']);
        $solicitud = $this->solicitudConLineaInventariable();

        // Simula un Asset ya creado por ImportarHistoricoCommand para el
        // mismo tipo de equipo — la secuencia de esta recepción debe
        // continuar desde aquí, nunca colisionar.
        Asset::create([
            'codigo' => 'KOS-LAPTOP-000001',
            'tipo_equipo_id' => TipoEquipo::where('nombre', 'Laptop')->value('id'),
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $this->estatusEnStock()->id,
        ]);

        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-001')
            ->set('lineas.0.unidades.1.numero_serie', 'SN-002')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('assets', 3);
        $codigos = Asset::orderBy('id')->pluck('codigo')->all();
        $this->assertSame(['KOS-LAPTOP-000001', 'KOS-LAPTOP-000002', 'KOS-LAPTOP-000003'], $codigos);

        $nuevos = Asset::where('codigo', '!=', 'KOS-LAPTOP-000001')->get();
        $this->assertCount(2, $nuevos);

        foreach ($nuevos as $asset) {
            $this->assertSame($marca->id, $asset->marca_id);
            $this->assertSame('compra', $asset->origen_tipo);
            $this->assertSame($ubicacion->id, $asset->ubicacion_actual_id);
            $this->assertSame($solicitud->vendor_id, $asset->vendor_id);
            $this->assertEquals(15000, (float) $asset->costo_adquisicion);
            $this->assertNotNull($asset->recepcion_linea_id);
        }

        $solicitud->refresh();
        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $solicitud->estatus);
        $this->assertSame(2, $solicitud->lineas->first()->cantidad_recibida);
    }

    public function test_receiving_reserves_the_asset_when_solicitud_has_a_sic(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusReservado();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'HP']);

        $empresa = Empresa::create(['razon_social' => 'Kosmos', 'nombre_comercial' => 'Kosmos']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $empleado = Empleado::create(['numero_empleado' => 'EMP-1', 'nombre' => 'Solicitante']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);

        $sic = SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $tipoEquipo->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => 'autorizada',
            'folio_sic' => 'SIC-1',
        ]);

        $solicitud = $this->solicitudConLineaInventariable(['sic_id' => $sic->id], ['cantidad_solicitada' => 1]);

        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-SIC-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-SIC-001')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame($this->estatusReservado()->id, $asset->estatus_id);
        $this->assertSame($sic->id, $asset->sic_reservada_id);
    }

    public function test_receiving_without_a_sic_leaves_the_asset_free_in_stock(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusReservado();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'HP']);

        $solicitud = $this->solicitudConLineaInventariable([], ['cantidad_solicitada' => 1]);

        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-NOSIC-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-NOSIC-001')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame($this->estatusEnStock()->id, $asset->estatus_id);
        $this->assertNull($asset->sic_reservada_id);
    }

    public function test_non_inventariable_line_does_not_create_assets(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REC-NOINV',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $linea = $solicitud->lineas()->create([
            'descripcion_libre' => 'Cable HDMI',
            'cantidad_solicitada' => 5,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => false,
        ]);

        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-002')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.cantidad_a_recibir', 5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseHas('recepcion_lineas', [
            'solicitud_proveedor_linea_id' => $linea->id,
            'cantidad_recibida' => 5,
            'asset_id' => null,
        ]);

        $solicitud->refresh();
        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $solicitud->estatus);
    }

    public function test_partial_reception_then_completing_reception_advances_estatus_correctly(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REC-PARCIAL',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $linea = $solicitud->lineas()->create([
            'descripcion_libre' => 'Toner',
            'cantidad_solicitada' => 10,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => false,
        ]);

        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-PARCIAL-1')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.cantidad_a_recibir', 4)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame(SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA, $solicitud->estatus);
        $this->assertSame(4, $linea->fresh()->cantidad_recibida);

        // La solicitud sigue siendo elegible (parcialmente_recibida) para una
        // segunda recepción que complete el resto.
        $ids = Livewire::test(Recepciones::class)->viewData('solicitudOptions')->pluck('id')->all();
        $this->assertContains($solicitud->id, $ids);

        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-PARCIAL-2')
            ->set('form.fecha_recepcion', '2026-09-02')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.cantidad_a_recibir', 6)
            ->call('save')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $solicitud->estatus);
        $this->assertSame(10, $linea->fresh()->cantidad_recibida);
    }

    public function test_line_whose_articulo_has_no_tipo_equipo_requires_the_extra_select(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'Genérica']);
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Monitor']);

        $articuloSinTipo = ArticuloSolicitud::create([
            'codigo' => 'ART-SIN-TIPO',
            'descripcion' => 'Monitor genérico',
            'unidad_medida' => 'Pieza',
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REC-SINTIPO',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'articulo_id' => $articuloSinTipo->id,
            'cantidad_solicitada' => 1,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => true,
        ]);

        // Sin capturar tipo_equipo_id — debe fallar la validación.
        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-003')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-004')
            ->call('save')
            ->assertHasErrors(['lineas.0.tipo_equipo_id']);

        $this->assertDatabaseCount('assets', 0);

        // Capturando el tipo de equipo faltante, ahora sí guarda.
        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-003')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.tipo_equipo_id', $tipoEquipo->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-004')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame($tipoEquipo->id, $asset->tipo_equipo_id);
    }

    public function test_export_acta_pdf_generates_without_exception_with_mixed_inventariable_and_non_inventariable_lines(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'Dell']);

        $solicitud = $this->solicitudConLineaInventariable([], ['cantidad_solicitada' => 1]);

        $noInventariable = $solicitud->lineas()->create([
            'descripcion_libre' => 'Cable HDMI',
            'cantidad_solicitada' => 3,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => false,
        ]);

        $recepciones = Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-ACTA-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-ACTA-001')
            ->set('lineas.1.cantidad_a_recibir', 3);

        $recepciones->call('save')->assertHasNoErrors();

        $recepcion = Recepcion::where('folio_remision', 'REM-ACTA-001')->firstOrFail();

        // Confirma que efectivamente quedaron ambos tipos de línea (una con
        // Asset generado, otra sin él) antes de generar el PDF.
        $this->assertDatabaseHas('recepcion_lineas', ['recepcion_id' => $recepcion->id, 'asset_id' => null]);
        $this->assertDatabaseCount('assets', 1);

        Livewire::test(Recepciones::class)
            ->call('exportActaPdf', $recepcion->id)
            ->assertFileDownloaded('acta-recepcion-REM-ACTA-001.pdf');
    }

    public function test_export_acta_pdf_generates_without_exception_when_observaciones_is_null(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REC-ACTA-NULL',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'descripcion_libre' => 'Toner',
            'cantidad_solicitada' => 2,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => false,
        ]);

        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->set('form.folio_remision', 'REM-ACTA-NULL')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.cantidad_a_recibir', 2)
            ->call('save')
            ->assertHasNoErrors();

        $recepcion = Recepcion::where('folio_remision', 'REM-ACTA-NULL')->firstOrFail();
        $this->assertNull($recepcion->observaciones);

        Livewire::test(Recepciones::class)
            ->call('exportActaPdf', $recepcion->id)
            ->assertFileDownloaded('acta-recepcion-REM-ACTA-NULL.pdf');
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-recepciones',
            'route_name' => 'gestionti.recepciones.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-recepciones.manage'));
    }

    /**
     * Fase 5, punto 5 (SharePoint) — mismo modal "Buscar en SharePoint" que
     * `AsignacionesTest`, aquí sobre la carpeta de "remisión de proveedor":
     * vincula un archivo ya existente sin subir nada.
     */
    public function test_vincular_archivo_existente_links_the_remision_without_uploading(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'Dell']);
        $solicitud = $this->solicitudConLineaInventariable();

        config([
            'services.sharepoint.tenant_id' => 'tenant-1',
            'services.sharepoint.client_id' => 'client-1',
            'services.sharepoint.client_secret' => 'secret-1',
            'services.sharepoint.site_hostname' => 'grupokosmosmexico.sharepoint.com',
            'services.sharepoint.site_path' => '/sites/Landit',
            'services.sharepoint.carpetas' => ['remision_proveedor' => 'Remisiones de Proveedor'],
        ]);
        $this->app->forgetInstance(\Modules\GestionTI\Support\SharePoint\SharePointClient::class);

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

            if (str_contains($url, ':/children')) {
                return Http::response(['value' => [
                    ['id' => 'sp-rem-1', 'name' => 'remision-001.pdf', 'webUrl' => 'https://example/remision-001.pdf', 'file' => []],
                ]]);
            }

            return Http::response([], 404);
        });

        $component = Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->call('openSharePointBuscar')
            ->assertSet('showSharePointModal', true);

        $this->assertCount(1, $component->viewData('sharePointArchivosFiltrados'));

        $component->call('elegirArchivoSharePoint', 'sp-rem-1')
            ->assertSet('showSharePointModal', false)
            ->assertSet('documentoRemisionVinculado.nombre', 'remision-001.pdf');

        $component->set('form.folio_remision', 'REM-SP-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-SP-001')
            ->set('lineas.0.unidades.1.numero_serie', 'SN-SP-002')
            ->call('save')
            ->assertHasNoErrors();

        $recepcion = Recepcion::where('folio_remision', 'REM-SP-001')->firstOrFail();
        $this->assertNotNull($recepcion->documento_remision_id);

        $documento = $recepcion->documentoRemision;
        $this->assertSame('sharepoint', $documento->proveedor_almacenamiento);
        $this->assertSame('sp-rem-1', $documento->referencia);
        $this->assertSame('remision-001.pdf', $documento->nombre_archivo);

        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    }

    /**
     * Mismo criterio que `AsignacionesTest` — un archivo ya vinculado a otro
     * registro no debe volver a ofrecerse en el modal "Buscar en SharePoint".
     */
    public function test_buscar_en_sharepoint_excluye_archivos_ya_vinculados(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable();

        config([
            'services.sharepoint.tenant_id' => 'tenant-1',
            'services.sharepoint.client_id' => 'client-1',
            'services.sharepoint.client_secret' => 'secret-1',
            'services.sharepoint.site_hostname' => 'grupokosmosmexico.sharepoint.com',
            'services.sharepoint.site_path' => '/sites/Landit',
            'services.sharepoint.carpetas' => ['remision_proveedor' => 'Remisiones de Proveedor'],
        ]);
        $this->app->forgetInstance(\Modules\GestionTI\Support\SharePoint\SharePointClient::class);

        DocumentoDigitalizado::create([
            'entidad_relacionada' => 'Recepcion',
            'entidad_id' => 0,
            'tipo_documento' => 'remision_proveedor',
            'proveedor_almacenamiento' => 'sharepoint',
            'referencia' => 'sp-rem-1',
            'url_externa' => 'https://example/remision-001.pdf',
            'nombre_archivo' => 'remision-001.pdf',
            'fecha_subida' => now(),
            'subido_por_id' => null,
        ]);

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

            if (str_contains($url, ':/children')) {
                return Http::response(['value' => [
                    ['id' => 'sp-rem-1', 'name' => 'remision-001.pdf', 'webUrl' => 'https://example/remision-001.pdf', 'file' => []],
                    ['id' => 'sp-rem-2', 'name' => 'remision-002.pdf', 'webUrl' => 'https://example/remision-002.pdf', 'file' => []],
                ]]);
            }

            return Http::response([], 404);
        });

        $component = Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitud->id)
            ->call('openSharePointBuscar')
            ->assertSet('showSharePointModal', true);

        $filtrados = $component->viewData('sharePointArchivosFiltrados');
        $this->assertCount(1, $filtrados);
        $this->assertSame('remision-002.pdf', $filtrados[0]['nombre']);
    }

    /**
     * Única mutación permitida sobre una recepción ya guardada (ver el
     * comentario de clase de `Recepciones` sobre por qué no hay `edit()`):
     * adjuntar la remisión que faltó/se equivocó al capturar, vía un modal
     * separado — no reabre ni permite tocar cantidades/folio/fechas.
     */
    public function test_attach_remision_action_works_on_an_existing_recepcion_without_a_document(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $solicitud = $this->solicitudConLineaInventariable();

        $recepcion = Recepcion::create([
            'solicitud_proveedor_id' => $solicitud->id,
            'folio_remision' => 'REM-SIN-DOC',
            'fecha_recepcion' => '2026-09-01',
            'recibido_por_id' => $validador->id,
            'ubicacion_id' => $ubicacion->id,
        ]);

        $file = UploadedFile::fake()->create('remision-tardia.pdf', 100, 'application/pdf');

        Livewire::test(Recepciones::class)
            ->call('openAttach', $recepcion->id)
            ->assertSet('showAttachModal', true)
            ->set('attachDocumentoRemision', $file)
            ->call('confirmAttach')
            ->assertHasNoErrors();

        $recepcion->refresh();
        $this->assertNotNull($recepcion->documento_remision_id);
        Storage::disk('public')->assertExists($recepcion->documentoRemision->referencia);
    }

    /**
     * Corrige un técnico que subió/vinculó la remisión equivocada: quitar
     * borra el `DocumentoDigitalizado` y libera la FK, pero el archivo real
     * sigue existiendo — solo se rompe la relación.
     */
    public function test_quitar_remision_unlinks_without_deleting_the_physical_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $solicitud = $this->solicitudConLineaInventariable();

        $documento = DocumentoDigitalizado::storeUploaded(
            UploadedFile::fake()->create('equivocada.pdf', 50, 'application/pdf'),
            $solicitud,
            'remision_proveedor',
            null
        );

        $recepcion = Recepcion::create([
            'solicitud_proveedor_id' => $solicitud->id,
            'folio_remision' => 'REM-A-CORREGIR',
            'fecha_recepcion' => '2026-09-01',
            'recibido_por_id' => $validador->id,
            'ubicacion_id' => $ubicacion->id,
            'documento_remision_id' => $documento->id,
        ]);

        Livewire::test(Recepciones::class)
            ->call('quitarRemision', $recepcion->id)
            ->assertSet('showAttachModal', false);

        $recepcion->refresh();
        $this->assertNull($recepcion->documento_remision_id);
        $this->assertDatabaseMissing('documentos_digitalizados', ['id' => $documento->id]);
        Storage::disk('public')->assertExists($documento->referencia);

        Livewire::test(Recepciones::class)
            ->call('openAttach', $recepcion->id)
            ->assertSet('showAttachModal', true);
    }
}
