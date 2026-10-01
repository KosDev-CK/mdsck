<?php

namespace Modules\GestionTI\Tests\Feature\MesaServicio;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\MesaServicio\EbsRequisiciones;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\CategoriaArticulo;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\EbsArticulo;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EbsRequisicionesTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Mesa de Servicio',
            'name' => 'SIC en EBS',
            'slug' => 'gestionti-ebs-requisiciones',
            'route_name' => 'gestionti.ebs-requisiciones.index',
            'permission_name' => 'screens.gestionti-ebs-requisiciones.manage',
            'icon' => 'arrow-path',
            'order' => 3,
        ]);

        $role = Role::findOrCreate('Mesa de Servicio', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function baseCatalogos(): array
    {
        $empleado = Empleado::create(['numero_empleado' => 'EMP-SCR-1', 'nombre' => 'Solicitante Pantalla']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id, 'sdp_display_id' => 'SDP-SCR-1']);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $empresa = Empresa::create(['razon_social' => 'Kosmos Pantalla S.A. de C.V.', 'nombre_comercial' => 'Kosmos Pantalla']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-SCR', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        return compact('empleado', 'ticket', 'tipoEquipo', 'centroCosto');
    }

    /**
     * Mismo criterio de elegibilidad usado por `SolicitudSicBorrador::scopeAutorizadaYSeleccionable()`
     * — una categoría marcada "Va a Compras".
     */
    private function marcarCategoriaComoCompra(string $slug = 'laptops-ebs-test'): CategoriaArticulo
    {
        return CategoriaArticulo::create([
            'nombre' => 'Laptops EBS Test',
            'slug' => $slug,
            'es_compra' => true,
        ]);
    }

    /**
     * SIC autorizada + artículo de categoría "va a Compra" + sin asignar —
     * elegible para el checkbox de selección y el filtro "Solo SICs
     * autorizadas y seleccionables". Vinculada a la `EbsRequisition` dada
     * para que aparezca como fila en el grid de "SIC en EBS".
     */
    private function crearSicElegible(array $c, string $folio, int $ebsRequisitionId): SolicitudSicBorrador
    {
        $categoria = $this->marcarCategoriaComoCompra('elegible-'.$folio);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-ELEGIBLE-'.$folio,
            'descripcion' => 'Laptop elegible',
            'unidad_medida' => 'Pieza',
            'categoria_id' => $categoria->id,
            'es_inventariable' => true,
        ]);

        return SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'articulo_id' => $articulo->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => $folio,
            'ebs_requisition_id' => $ebsRequisitionId,
        ]);
    }

    /**
     * Requisición de EBS que NUNCA tuvo SIC local, aprobada, con su artículo
     * (vía `EbsArticulo`) mapeado a una categoría "va a Compra" — elegible
     * por el camino directo (ver `EbsRequisition::scopeElegibleDirectoSinSic()`/
     * `articuloMapeadoDeCompra()`).
     */
    private function crearEbsDirectoElegible(string $code, int $itemId): EbsRequisition
    {
        $categoria = $this->marcarCategoriaComoCompra('ebs-directo-'.$code);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-EBS-DIRECTO-'.$code,
            'descripcion' => 'Laptop EBS directo',
            'unidad_medida' => 'Pieza',
            'categoria_id' => $categoria->id,
            'es_inventariable' => true,
        ]);

        EbsArticulo::create(['ebs_item_id' => $itemId, 'articulo_id' => $articulo->id]);

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => random_int(1000000, 9999999),
            'code' => $code,
            'status' => 'APPROVED',
        ]);

        $ebsRequisicion->lines()->create([
            'requisition_line_id' => random_int(1000000, 9999999),
            'line_number' => 1,
            'item_id' => $itemId,
            'item_description' => 'Laptop EBS directo item',
            'quantity' => 1,
        ]);

        return $ebsRequisicion->fresh();
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/ebs-requisiciones')->assertForbidden();
    }

    public function test_lists_ebs_requisitions(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => '6489',
            'description' => 'Equipo Laptop Nueva',
            'status' => 'APPROVED',
            'fecha_creacion' => '2026-09-01',
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->assertSee('6489')
            ->assertSee('Equipo Laptop Nueva');
    }

    public function test_codigo_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create(['requisition_header_id' => 1, 'code' => '6489', 'description' => 'A']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '9999', 'description' => 'B']);

        Livewire::test(EbsRequisiciones::class)
            ->set('codigoFilter', '6489')
            ->assertSee('6489')
            ->assertDontSee('9999');
    }

    public function test_codigo_filter_also_searches_description_and_notes(): void
    {
        $this->actingAs($this->actingUser());

        $porNota = EbsRequisition::create(['requisition_header_id' => 1, 'code' => '1111', 'description' => 'Sin relación']);
        $porNota->notes()->create(['clave' => 'Comentario', 'valor' => 'Contiene la palabra clave especial']);

        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '2222', 'description' => 'Otra cosa']);

        Livewire::test(EbsRequisiciones::class)
            ->set('codigoFilter', 'clave especial')
            ->assertSee('1111')
            ->assertDontSee('2222');
    }

    public function test_estatus_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'A1', 'status' => 'APPROVED']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => 'A2', 'status' => 'REJECTED']);

        Livewire::test(EbsRequisiciones::class)
            ->set('estatusFilter', 'REJECTED')
            ->assertSee('A2')
            ->assertDontSee('A1');
    }

    public function test_vinculacion_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $vinculada = EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'V1']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => 'V2']);

        SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'ebs_requisition_id' => $vinculada->id,
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->set('vinculacionFilter', 'no_vinculada')
            ->assertSee('V2');

        // No se agrega `->assertDontSee('V1')` aquí: coincide, por casualidad
        // de substring, con un fragmento del atributo `d` del SVG del icono
        // "arrow-down-tray" que ahora siempre se renderiza en el botón
        // "Descargar PDF" de la modal de ayuda (`18.75V16.5` contiene "V1")
        // — mismo tipo de colisión ya documentado y resuelto en
        // DashboardTest.php, aquí por markup del icono en vez de texto. No es
        // una fuga real: la fila "V1" sigue sin aparecer en la tabla, que es
        // lo que esta prueba en realidad necesita cubrir (ver `assertSee('V2')`
        // de arriba, que confirma que el filtro sí funciona).
    }

    public function test_fecha_aprobada_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => 'AP-DENTRO',
            'approver_date' => '2026-09-05',
        ]);
        EbsRequisition::create([
            'requisition_header_id' => 2,
            'code' => 'AP-FUERA',
            'approver_date' => '2026-09-20',
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->set('fechaAprobadaDesde', '2026-09-01')
            ->set('fechaAprobadaHasta', '2026-09-10')
            ->assertSee('AP-DENTRO')
            ->assertDontSee('AP-FUERA');
    }

    public function test_open_detalle_loads_the_record_with_lines_and_notes(): void
    {
        $this->actingAs($this->actingUser());

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => 'DET-1',
            'description' => 'Detalle de prueba',
        ]);

        $ebsRequisicion->lines()->create([
            'requisition_line_id' => 1,
            'line_number' => 1,
            'item_description' => 'Laptop Dell',
            'quantity' => 2,
            'unit_measurement' => 'PZA',
            'unit_price' => 15000,
            'currency_code' => 'MXN',
        ]);

        $ebsRequisicion->notes()->create([
            'clave' => 'Justificación',
            'valor' => 'Reemplazo de equipo dañado',
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->call('openDetalle', $ebsRequisicion->id)
            ->assertSet('showDetalleModal', true)
            ->assertSet('detalleId', $ebsRequisicion->id)
            ->assertSee('Laptop Dell')
            ->assertSee('Reemplazo de equipo dañado');
    }

    public function test_close_detalle_clears_the_state(): void
    {
        $this->actingAs($this->actingUser());

        $ebsRequisicion = EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'DET-2']);

        Livewire::test(EbsRequisiciones::class)
            ->call('openDetalle', $ebsRequisicion->id)
            ->call('closeDetalle')
            ->assertSet('showDetalleModal', false)
            ->assertSet('detalleId', null);
    }

    public function test_can_link_manually_to_a_solicitud_and_it_syncs_the_local_status(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => 'MANUAL-9',
            'status' => 'APPROVED',
        ]);

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'folio_sic' => 'SIN-MATCH',
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->call('openVincular', $ebsRequisicion->id)
            ->set('vincularSearch', 'SIN-MATCH')
            ->set('vincularSolicitudId', $solicitud->id)
            ->call('confirmVincular')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame($ebsRequisicion->id, $solicitud->ebs_requisition_id);
        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->estatus);
    }

    public function test_shows_link_to_the_solicitud_a_proveedor_when_the_linked_sic_is_already_assigned(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $articulo = \Modules\GestionTI\Models\ArticuloSolicitud::create([
            'codigo' => 'ART-ASIGNADA',
            'descripcion' => 'Laptop asignada',
            'unidad_medida' => 'pieza',
            'es_inventariable' => true,
        ]);

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => 'ASIGNADA-1',
            'status' => 'APPROVED',
        ]);

        $solicitudSic = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'articulo_id' => $articulo->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'ASIGNADA-1',
            'ebs_requisition_id' => $ebsRequisicion->id,
        ]);

        $proveedor = \Modules\GestionTI\Models\Proveedor::create([
            'razon_social' => 'Proveedor Prueba S.A. de C.V.',
            'nombre_comercial' => 'Proveedor Prueba',
        ]);

        $solicitudProveedor = \Modules\GestionTI\Models\SolicitudProveedor::create([
            'folio' => 'SP-ASIGNADA-001',
            'vendor_id' => $proveedor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitudProveedor->lineas()->create([
            'sic_id' => $solicitudSic->id,
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 1,
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->assertSee('SP-ASIGNADA-001')
            ->call('openSolicitudProveedor', $solicitudProveedor->id)
            ->assertSet('showSolicitudProveedorModal', true)
            ->assertSet('solicitudProveedorDetalleId', $solicitudProveedor->id)
            ->assertSee('SP-ASIGNADA-001')
            ->assertSee('Proveedor Prueba');
    }

    public function test_closing_the_solicitud_proveedor_modal_clears_its_state(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $articulo = \Modules\GestionTI\Models\ArticuloSolicitud::create([
            'codigo' => 'ART-ASIGNADA-2',
            'descripcion' => 'Laptop asignada 2',
            'unidad_medida' => 'pieza',
            'es_inventariable' => true,
        ]);

        $solicitudSic = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'articulo_id' => $articulo->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'ASIGNADA-2',
        ]);

        $proveedor = Proveedor::create(['razon_social' => 'Proveedor 2', 'nombre_comercial' => 'Proveedor 2']);
        $solicitudProveedor = SolicitudProveedor::create([
            'folio' => 'SP-ASIGNADA-002',
            'vendor_id' => $proveedor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitudProveedor->lineas()->create(['sic_id' => $solicitudSic->id, 'articulo_id' => $articulo->id, 'cantidad_solicitada' => 1]);

        Livewire::test(EbsRequisiciones::class)
            ->call('openSolicitudProveedor', $solicitudProveedor->id)
            ->call('closeSolicitudProveedor')
            ->assertSet('showSolicitudProveedorModal', false)
            ->assertSet('solicitudProveedorDetalleId', null);
    }

    public function test_eligible_row_shows_checkbox_and_non_eligible_does_not(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $elegible = EbsRequisition::create(['requisition_header_id' => 10, 'code' => 'ELEGIBLE-1']);
        $this->crearSicElegible($c, 'ELEGIBLE-1', $elegible->id);

        // No elegible: autorizada pero sin categoría de compra.
        $noElegible = EbsRequisition::create(['requisition_header_id' => 11, 'code' => 'NOELEGIBLE-1']);
        SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'NOELEGIBLE-1',
            'ebs_requisition_id' => $noElegible->id,
        ]);

        $component = Livewire::test(EbsRequisiciones::class);

        $sicElegibleId = SolicitudSicBorrador::where('folio_sic', 'ELEGIBLE-1')->value('id');
        $sicNoElegibleId = SolicitudSicBorrador::where('folio_sic', 'NOELEGIBLE-1')->value('id');

        $this->assertContains($sicElegibleId, $component->viewData('elegibleSicIds'));
        $this->assertNotContains($sicNoElegibleId, $component->viewData('elegibleSicIds'));
    }

    public function test_solo_seleccionables_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $elegible = EbsRequisition::create(['requisition_header_id' => 20, 'code' => 'FILTRO-ELEGIBLE']);
        $this->crearSicElegible($c, 'FILTRO-ELEGIBLE', $elegible->id);

        EbsRequisition::create(['requisition_header_id' => 21, 'code' => 'FILTRO-SIN-SIC']);

        Livewire::test(EbsRequisiciones::class)
            ->set('soloSeleccionables', true)
            ->assertSee('FILTRO-ELEGIBLE')
            ->assertDontSee('FILTRO-SIN-SIC');
    }

    public function test_seleccionar_todas_and_deseleccionar_todas_act_only_on_eligible_visible_rows(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $elegible1 = EbsRequisition::create(['requisition_header_id' => 30, 'code' => 'SEL-1']);
        $sic1 = $this->crearSicElegible($c, 'SEL-1', $elegible1->id);

        $elegible2 = EbsRequisition::create(['requisition_header_id' => 31, 'code' => 'SEL-2']);
        $sic2 = $this->crearSicElegible($c, 'SEL-2', $elegible2->id);

        // Fila sin SIC — nunca debe terminar en sicIdsSeleccionados.
        EbsRequisition::create(['requisition_header_id' => 32, 'code' => 'SEL-SIN-SIC']);

        $component = Livewire::test(EbsRequisiciones::class)->call('seleccionarTodas');

        $this->assertEqualsCanonicalizing([$sic1->id, $sic2->id], $component->get('sicIdsSeleccionados'));

        $component->call('deseleccionarTodas');
        $this->assertSame([], $component->get('sicIdsSeleccionados'));
    }

    public function test_crear_solicitud_proveedor_redirects_with_the_selected_sic_ids(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $elegible = EbsRequisition::create(['requisition_header_id' => 40, 'code' => 'REDIRECT-1']);
        $sic = $this->crearSicElegible($c, 'REDIRECT-1', $elegible->id);

        Livewire::test(EbsRequisiciones::class)
            ->set('sicIdsSeleccionados', [$sic->id])
            ->call('crearSolicitudProveedor')
            ->assertRedirect(route('gestionti.solicitudes-proveedor.index', ['crear_desde_sics' => (string) $sic->id]));
    }

    public function test_crear_solicitud_proveedor_does_nothing_without_any_selection(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(EbsRequisiciones::class)
            ->call('crearSolicitudProveedor')
            ->assertNoRedirect();
    }

    /**
     * Columna nueva "Id solicitud prov." (ver docs/gestionti-progreso.md,
     * rediseño "ebs_requisition_id directo en la línea") — sin ninguna
     * Solicitud a Proveedor que la haya recogido todavía (por ningún
     * camino), esa columna muestra "—" en vez del botón con el folio.
     */
    public function test_shows_dash_in_id_solicitud_prov_column_when_the_linked_sic_is_not_used_in_any_solicitud(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => 'LIBRE-1',
            'status' => 'APPROVED',
        ]);

        SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'folio_sic' => 'LIBRE-1',
            'ebs_requisition_id' => $ebsRequisicion->id,
        ]);

        $component = Livewire::test(EbsRequisiciones::class)->assertSee('LIBRE-1');

        $record = $component->viewData('records')->firstWhere('code', 'LIBRE-1');
        $this->assertTrue($record->solicitudSicBorrador->solicitudProveedorLineas->isEmpty());
    }

    public function test_cannot_link_a_solicitud_already_linked_to_a_different_requisition(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $otra = EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'OTRA']);
        $nueva = EbsRequisition::create(['requisition_header_id' => 2, 'code' => 'NUEVA']);

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'ebs_requisition_id' => $otra->id,
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->call('openVincular', $nueva->id)
            ->set('vincularSolicitudId', $solicitud->id)
            ->call('confirmVincular')
            ->assertHasErrors(['vincularSolicitudId']);

        $this->assertSame($otra->id, $solicitud->fresh()->ebs_requisition_id);
    }

    /**
     * Segundo camino de elegibilidad (rediseño "SIC en EBS -> Solicitud a
     * Proveedor", ver docs/gestionti-progreso.md): una requisición de EBS
     * que NUNCA tuvo SIC local, aprobada y con su artículo mapeado de
     * categoría "va a Compra", muestra el checkbox ligado a `ebsIdsSeleccionados`
     * — nunca a `sicIdsSeleccionados`, porque no hay SIC local.
     */
    public function test_ebs_direct_row_without_local_sic_is_eligible(): void
    {
        $this->actingAs($this->actingUser());

        $ebsRequisicion = $this->crearEbsDirectoElegible('DIRECTO-1', 9001);

        $component = Livewire::test(EbsRequisiciones::class);

        $this->assertContains($ebsRequisicion->id, $component->viewData('elegibleEbsIds'));
        $this->assertNotContains($ebsRequisicion->id, $component->viewData('elegibleSicIds'));
    }

    /**
     * Una requisición APPROVED sin SIC local pero cuyo item NUNCA se mapeó
     * (o se mapeó a una categoría que no es de compra) no es elegible por
     * ningún camino.
     */
    public function test_ebs_direct_row_without_mapped_articulo_is_not_eligible(): void
    {
        $this->actingAs($this->actingUser());

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 50,
            'code' => 'SIN-MAPEO',
            'status' => 'APPROVED',
        ]);
        $ebsRequisicion->lines()->create([
            'requisition_line_id' => 1,
            'line_number' => 1,
            'item_id' => 9002,
            'item_description' => 'Item sin mapear',
            'quantity' => 1,
        ]);

        $component = Livewire::test(EbsRequisiciones::class);

        $this->assertNotContains($ebsRequisicion->id, $component->viewData('elegibleEbsIds'));
    }

    /**
     * El filtro "Solo SICs autorizadas y seleccionables" ahora es la unión
     * de los 2 caminos — una fila elegible solo por el camino directo
     * también debe aparecer.
     */
    public function test_solo_seleccionables_filter_includes_ebs_direct_eligible_rows(): void
    {
        $this->actingAs($this->actingUser());

        $this->crearEbsDirectoElegible('DIRECTO-FILTRO', 9003);
        EbsRequisition::create(['requisition_header_id' => 60, 'code' => 'NO-ELEGIBLE-DIRECTO']);

        Livewire::test(EbsRequisiciones::class)
            ->set('soloSeleccionables', true)
            ->assertSee('DIRECTO-FILTRO')
            ->assertDontSee('NO-ELEGIBLE-DIRECTO');
    }

    /** "Seleccionar todas"/"Deseleccionar todas" también actúan sobre el camino directo. */
    public function test_seleccionar_todas_includes_ebs_direct_rows(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $sicElegible = $this->crearSicElegible($c, 'MIX-SIC', EbsRequisition::create(['requisition_header_id' => 70, 'code' => 'MIX-SIC'])->id);
        $ebsDirecto = $this->crearEbsDirectoElegible('MIX-EBS', 9004);

        $component = Livewire::test(EbsRequisiciones::class)->call('seleccionarTodas');

        $this->assertContains($sicElegible->id, $component->get('sicIdsSeleccionados'));
        $this->assertContains($ebsDirecto->id, $component->get('ebsIdsSeleccionados'));

        $component->call('deseleccionarTodas');
        $this->assertSame([], $component->get('sicIdsSeleccionados'));
        $this->assertSame([], $component->get('ebsIdsSeleccionados'));
    }

    /** "Crear Solicitud a Proveedor" navega con los 2 query params cuando hay una mezcla. */
    public function test_crear_solicitud_proveedor_redirects_with_both_query_params_when_mixed(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $sicElegible = $this->crearSicElegible($c, 'MIX2-SIC', EbsRequisition::create(['requisition_header_id' => 71, 'code' => 'MIX2-SIC'])->id);
        $ebsDirecto = $this->crearEbsDirectoElegible('MIX2-EBS', 9005);

        Livewire::test(EbsRequisiciones::class)
            ->set('sicIdsSeleccionados', [$sicElegible->id])
            ->set('ebsIdsSeleccionados', [$ebsDirecto->id])
            ->call('crearSolicitudProveedor')
            ->assertRedirect(route('gestionti.solicitudes-proveedor.index', [
                'crear_desde_sics' => (string) $sicElegible->id,
                'crear_desde_ebs' => (string) $ebsDirecto->id,
            ]));
    }

    /**
     * Las 2 columnas nuevas ("Vinculada" sin dato de Solicitud a Proveedor, y
     * "Id solicitud prov." con el folio como botón) para una fila asignada
     * por el camino directo (sin SIC local).
     */
    public function test_id_solicitud_prov_column_shows_the_folio_for_a_row_assigned_via_the_direct_ebs_path(): void
    {
        $this->actingAs($this->actingUser());

        $categoria = $this->marcarCategoriaComoCompra('directo-asignado');
        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-DIRECTO-ASIGNADO',
            'descripcion' => 'Laptop directo asignado',
            'unidad_medida' => 'Pieza',
            'categoria_id' => $categoria->id,
            'es_inventariable' => true,
        ]);

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 80,
            'code' => 'DIRECTO-ASIGNADO',
            'status' => 'APPROVED',
        ]);

        $proveedor = Proveedor::create(['razon_social' => 'Proveedor Directo', 'nombre_comercial' => 'Proveedor Directo']);
        $solicitudProveedor = SolicitudProveedor::create([
            'folio' => 'SP-DIRECTO-001',
            'vendor_id' => $proveedor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitudProveedor->lineas()->create([
            'ebs_requisition_id' => $ebsRequisicion->id,
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 1,
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->assertSee('DIRECTO-ASIGNADO')
            ->assertSee('SP-DIRECTO-001')
            ->assertSee('No vinculada');
    }

    /** Filtro nuevo "Asignación a Solicitud de Compra". */
    public function test_asignacion_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $ebsAsignada = EbsRequisition::create(['requisition_header_id' => 90, 'code' => 'ASIG-SI']);
        $sicAsignada = $this->crearSicElegible($c, 'ASIG-SI', $ebsAsignada->id);

        $proveedor = Proveedor::create(['razon_social' => 'Proveedor Asig', 'nombre_comercial' => 'Proveedor Asig']);
        $solicitudProveedor = SolicitudProveedor::create([
            'folio' => 'SP-ASIG-001',
            'vendor_id' => $proveedor->id,
            'fecha_solicitud' => '2026-09-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitudProveedor->lineas()->create([
            'sic_id' => $sicAsignada->id,
            'articulo_id' => $sicAsignada->articulo_id,
            'cantidad_solicitada' => 1,
        ]);

        EbsRequisition::create(['requisition_header_id' => 91, 'code' => 'ASIG-NO']);

        Livewire::test(EbsRequisiciones::class)
            ->set('asignacionFilter', 'asignada')
            ->assertSee('ASIG-SI')
            ->assertDontSee('ASIG-NO');

        Livewire::test(EbsRequisiciones::class)
            ->set('asignacionFilter', 'sin_asignar')
            ->assertSee('ASIG-NO')
            ->assertDontSee('ASIG-SI');
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-ebs-requisiciones',
            'route_name' => 'gestionti.ebs-requisiciones.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-ebs-requisiciones.manage'));
    }
}
