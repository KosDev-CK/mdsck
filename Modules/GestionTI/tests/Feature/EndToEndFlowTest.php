<?php

namespace Modules\GestionTI\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Compras\Facturas;
use Modules\GestionTI\Livewire\Compras\Recepciones;
use Modules\GestionTI\Livewire\Compras\SolicitudesProveedor;
use Modules\GestionTI\Livewire\Dashboard;
use Modules\GestionTI\Livewire\Inventarios\Asignaciones;
use Modules\GestionTI\Livewire\MesaServicio\SolicitudesSic;
use Modules\GestionTI\Livewire\MesaServicio\Tickets;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Manage as PresupuestoManage;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Show as PresupuestoShow;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\RecepcionLinea;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;
use Modules\GestionTI\Notifications\AvisoNotification;
use Tests\TestCase;

/**
 * Pase de pruebas end-to-end del módulo completo — a diferencia del resto de
 * la suite (que prueba cada pantalla de forma aislada), este test recorre la
 * cadena de negocio real de punta a punta usando los componentes Livewire
 * reales de cada etapa, para detectar problemas de COSTURA entre etapas que
 * los tests aislados no pueden ver (folios que no se propagan, un estatus que
 * no dispara la siguiente etapa, stock que no se descuenta/reserva
 * correctamente). Ver docs/gestionti-progreso.md para el diseño de cada
 * pantalla — no se repite aquí ninguna regla de negocio ya cubierta por su
 * test dedicado.
 *
 * Cubre AMBOS caminos de origen documentados en el spec:
 * 1) Ticket -> Solicitud de SIC -> SIC autorizada (necesario de todos modos:
 *    `Asignaciones` exige una SIC autorizada sin importar de dónde vino el
 *    Asset físicamente — es la pieza que conecta con el empleado solicitante
 *    y su Ticket).
 * 2) Presupuesto por Proyecto -> Solicitud a Proveedor -> Recepción -> Asset
 *    -> Asignación -> Factura (el origen de compras real de este recorrido).
 *
 * Hallazgo real de esta sesión (corregido en producción, no solo en el
 * test): `Recepciones::save()` nunca llenaba `assets.proyecto_presupuesto_id`
 * aunque la FK y la relación `Asset::proyectoPresupuesto()` ya existían desde
 * Fase 3 etapa 5 — el folio del proyecto se perdía silenciosamente al llegar
 * al Asset. Corregido en `Modules/GestionTI/app/Livewire/Compras/Recepciones.php`
 * (propaga `solicitud->proyectoPresupuestoArticulo?->proyecto_id`). Este test
 * cubre la regresión.
 *
 * Nota sobre "AuditLog transversal": se confirmó leyendo `app/Models/` que
 * este módulo NO tiene un modelo `AuditLog` genérico (documentado como
 * "fase futura separada" en docs/gestionti-progreso.md) — lo más cercano a
 * una bitácora transversal de transiciones de estatus es `AvisoEnviado`
 * (historial de notificaciones disparadas por `AvisoDispatcher`), que este
 * test sí usa para verificar que las transiciones clave (SIC autorizada,
 * presupuesto listo para autorizar, proyecto autorizado) quedaron
 * registradas — no hay una pieza más genérica que auditar todavía.
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $user = User::factory()->create(['is_active' => true, 'email' => 'e2e-admin@example.com']);
        $user->assignRole('Administrador');

        return $user;
    }

    public function test_full_business_flow_from_ticket_and_budget_to_invoice(): void
    {
        $admin = $this->actingAdmin();
        $this->actingAs($admin);
        Notification::fake();

        // ==============================================================
        // Catálogos base compartidos por todo el recorrido.
        // ==============================================================
        $empresa = Empresa::create(['razon_social' => 'Kosmos E2E S.A. de C.V.', 'nombre_comercial' => 'Kosmos E2E']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-E2E', 'nombre' => 'Corporativo E2E', 'empresa_id' => $empresa->id]);
        $area = Area::create(['nombre' => 'Operaciones E2E']);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop E2E']);
        $ubicacionAlmacen = Ubicacion::create(['nombre' => 'Almacén Central E2E']);
        $proveedor = Proveedor::create(['razon_social' => 'Distribuidora E2E S.A. de C.V.', 'nombre_comercial' => 'Distribuidora E2E']);
        $validador = Validador::create(['nombre' => 'Ana Torres E2E']);
        $marca = Marca::create(['nombre' => 'Dell E2E']);
        $articuloCatalogo = ArticuloSolicitud::create([
            'codigo' => 'ART-E2E-001',
            'descripcion' => 'Laptop estándar E2E',
            'unidad_medida' => 'Pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
        ]);

        $solicitanteEmpleado = Empleado::create(['numero_empleado' => 'EMP-E2E-SOL', 'nombre' => 'Solicitante E2E', 'correo' => 'solicitante-e2e@example.com']);
        $pmEmpleado = Empleado::create(['numero_empleado' => 'EMP-E2E-PM', 'nombre' => 'PM E2E', 'correo' => 'pm-e2e@example.com']);
        $aprobador1Empleado = Empleado::create(['numero_empleado' => 'EMP-E2E-AP1', 'nombre' => 'Aprobador Nivel 1 E2E']);
        $aprobador2Empleado = Empleado::create(['numero_empleado' => 'EMP-E2E-AP2', 'nombre' => 'Aprobador Nivel 2 E2E']);

        $solicitanteUser = User::factory()->create(['email' => 'solicitante-e2e@example.com']);
        $pmUser = User::factory()->create(['email' => 'pm-e2e@example.com']);

        // Umbral de stock mínimo para el par (Laptop E2E, Almacén Central
        // E2E) — usado para verificar que "Stock bajo mínimo" (Dashboard +
        // StockMinimo::enBreach()) reacciona en vivo a los cambios reales de
        // estatus de Asset en cada etapa del recorrido, no a un valor
        // congelado.
        StockMinimo::create([
            'tipo_equipo_id' => $tipoEquipo->id,
            'ubicacion_id' => $ubicacionAlmacen->id,
            'cantidad_minima' => 2,
            'activo' => true,
        ]);

        // --------------------------------------------------------------
        // CHECKPOINT 0 — estado inicial del Dashboard, antes de tocar nada
        // del recorrido de negocio: sin SICs, sin solicitudes a proveedor,
        // sin facturas, y en breach de stock (0 asset en_stock contra un
        // mínimo de 2 para el único combo tipo/ubicación configurado).
        // --------------------------------------------------------------
        $dashboard = Livewire::test(Dashboard::class);
        $this->assertSame(0, $dashboard->viewData('sicsEnCaptura'));
        $this->assertSame(0, $dashboard->viewData('solicitudesProveedorPendientes'));
        $this->assertSame(0, $dashboard->viewData('facturasPendientes'));
        $this->assertSame(0, $dashboard->viewData('facturasDiferencia'));
        $this->assertCount(1, $dashboard->viewData('stockBajoMinimo'));
        $this->assertCount(1, StockMinimo::enBreach());

        // ==============================================================
        // CAMINO 2 — Ticket -> Solicitud de SIC -> SIC autorizada.
        // Necesario de todos modos: Asignaciones exige una SIC autorizada
        // sin importar de dónde salió físicamente el Asset.
        // ==============================================================
        Livewire::test(Tickets::class)
            ->call('create')
            ->set('form.fecha', '2026-08-01')
            ->set('form.empleado_id', $solicitanteEmpleado->id)
            ->set('form.sdp_display_id', 'SDP-E2E-001')
            ->call('save')
            ->assertHasNoErrors();

        $ticket = Ticket::where('sdp_display_id', 'SDP-E2E-001')->firstOrFail();
        $this->assertSame($solicitanteEmpleado->id, $ticket->empleado_id);

        Livewire::test(SolicitudesSic::class)
            ->call('create')
            ->set('form.ticket_id', $ticket->id)
            ->set('form.empleado_id', $solicitanteEmpleado->id)
            ->set('form.tipo_equipo_id', $tipoEquipo->id)
            ->set('form.motivo', 'Equipo nuevo para ingreso E2E')
            ->set('form.centro_costo_id', $centroCosto->id)
            ->set('form.urgencia', 'media')
            ->set('form.fecha_solicitud', '2026-08-02')
            ->call('save')
            ->assertHasNoErrors();

        $sic = SolicitudSicBorrador::where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame(SolicitudSicBorrador::ESTATUS_CAPTURADO, $sic->estatus);

        // "SICs en captura" cuenta capturado + sic_creada.
        $this->assertSame(1, Livewire::test(Dashboard::class)->viewData('sicsEnCaptura'));

        Livewire::test(SolicitudesSic::class)
            ->call('openAdvance', $sic->id)
            ->set('advanceFolioSic', 'SIC-E2E-001')
            ->call('confirmAdvanceToSicCreada')
            ->assertHasNoErrors();

        $sic->refresh();
        $this->assertSame(SolicitudSicBorrador::ESTATUS_SIC_CREADA, $sic->estatus);
        $this->assertSame('SIC-E2E-001', $sic->folio_sic);

        Livewire::test(SolicitudesSic::class)->call('marcarAutorizada', $sic->id);

        $sic->refresh();
        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $sic->estatus);

        // El aviso SIC_AUTORIZADA se disparó hacia el solicitante real
        // (coincidencia de correo) — confirma que AvisoDispatcher realmente
        // se invoca desde la transición de estatus, no solo que el estatus
        // cambió.
        Notification::assertSentTo($solicitanteUser, AvisoNotification::class);
        $this->assertSame(
            2,
            AvisoEnviado::whereHas('tipoAviso', fn ($q) => $q->where('codigo', 'SIC_AUTORIZADA'))
                ->where('destinatario_user_id', $solicitanteUser->id)
                ->count()
        );

        // Ya autorizada: "SICs en captura" vuelve a 0.
        $this->assertSame(0, Livewire::test(Dashboard::class)->viewData('sicsEnCaptura'));

        // ==============================================================
        // CAMINO 1 — Presupuesto por Proyecto -> autorizado -> disponible
        // para que Compras lo recoja en Solicitud a Proveedores.
        // ==============================================================
        Livewire::test(PresupuestoManage::class)
            ->call('create')
            ->set('form.nombre_proyecto', 'Proyecto E2E')
            ->set('form.empresa_id', $empresa->id)
            ->set('form.centro_costo_id', $centroCosto->id)
            ->set('form.direccion_centro', 'Av. Prueba 123')
            ->set('form.area_operativa_solicitante_id', $area->id)
            ->set('form.pm_responsable_id', $pmEmpleado->id)
            ->set('form.fecha_solicitud', '2026-08-05')
            ->set('form.fecha_limite_captura', '2026-08-20')
            ->call('save')
            ->assertHasNoErrors();

        $proyecto = ProyectoPresupuesto::where('nombre_proyecto', 'Proyecto E2E')->firstOrFail();
        $this->assertSame(ProyectoPresupuesto::ESTATUS_ARMADO, $proyecto->estatus);

        Livewire::test(PresupuestoShow::class, ['proyectoPresupuesto' => $proyecto])
            ->call('openArticuloModal')
            ->set('articuloForm.categoria', 'laptops_desktops')
            ->set('articuloForm.categoria_contable', 'infraestructura')
            ->set('articuloForm.descripcion', 'Laptop para gerente E2E')
            ->set('articuloForm.cantidad', 2)
            ->set('articuloForm.responsable_costo_id', $pmEmpleado->id)
            ->call('saveArticulo')
            ->assertHasNoErrors();

        $articuloPresupuesto = $proyecto->articulos()->firstOrFail();
        $this->assertSame('laptops_desktops', $articuloPresupuesto->categoria);

        Livewire::test(PresupuestoShow::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('enviarACapturaCostos')
            ->assertHasNoErrors();

        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS, $proyecto->fresh()->estatus);

        Livewire::test(PresupuestoShow::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('openCosto', $articuloPresupuesto->id)
            ->set('costoForm.costo_unitario', 15000)
            ->call('guardarCosto')
            ->assertHasNoErrors();

        $articuloPresupuesto->refresh();
        $this->assertSame(ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_CAPTURADO, $articuloPresupuesto->estatus_captura);
        $this->assertEquals(15000, (float) $articuloPresupuesto->costo_unitario);
        // Era el único/último artículo pendiente -> transición automática.
        $this->assertSame(ProyectoPresupuesto::ESTATUS_COMPLETO, $proyecto->fresh()->estatus);

        Notification::assertSentTo($pmUser, AvisoNotification::class);
        $this->assertSame(
            2,
            AvisoEnviado::whereHas('tipoAviso', fn ($q) => $q->where('codigo', 'PRESUPUESTO_LISTO_PARA_AUTORIZAR'))
                ->where('destinatario_user_id', $pmUser->id)
                ->count()
        );

        Livewire::test(PresupuestoShow::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('openAutorizacionModal')
            ->set('niveles.0.aprobador_id', $aprobador1Empleado->id)
            ->call('addNivel')
            ->set('niveles.1.aprobador_id', $aprobador2Empleado->id)
            ->call('enviarAAutorizacion')
            ->assertHasNoErrors();

        $proyecto->refresh();
        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION, $proyecto->estatus);

        $niveles = $proyecto->autorizaciones()->orderBy('nivel')->get();
        $this->assertCount(2, $niveles);

        // Enforcement secuencial real: aprobar el nivel 1 no autoriza el
        // proyecto todavía (falta el nivel 2).
        Livewire::test(PresupuestoShow::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('autorizarNivel', $niveles[0]->id)
            ->assertHasNoErrors();

        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION, $proyecto->fresh()->estatus);
        $this->assertSame(0, AvisoEnviado::whereHas('tipoAviso', fn ($q) => $q->where('codigo', 'PROYECTO_AUTORIZADO'))->count());

        Livewire::test(PresupuestoShow::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('autorizarNivel', $niveles[1]->id)
            ->assertHasNoErrors();

        $this->assertSame(ProyectoPresupuesto::ESTATUS_AUTORIZADO, $proyecto->fresh()->estatus);

        Notification::assertSentTo($pmUser, AvisoNotification::class);
        $this->assertSame(
            2,
            AvisoEnviado::whereHas('tipoAviso', fn ($q) => $q->where('codigo', 'PROYECTO_AUTORIZADO'))
                ->where('destinatario_user_id', $pmUser->id)
                ->count()
        );

        // El artículo autorizado ya aparece disponible para que Compras lo
        // recoja desde Solicitud a Proveedores (decisión de diseño
        // documentada: autorizar el proyecto NO crea la SolicitudProveedor
        // automáticamente).
        $opciones = Livewire::test(SolicitudesProveedor::class)->call('create')->viewData('proyectoArticuloOptions');
        $this->assertTrue($opciones->pluck('id')->contains($articuloPresupuesto->id));

        // ==============================================================
        // Solicitud a Proveedores — originada del artículo de presupuesto
        // ya autorizado (sin SIC, a diferencia del camino 2).
        // ==============================================================
        $this->assertSame(0, Livewire::test(Dashboard::class)->viewData('solicitudesProveedorPendientes'));

        Livewire::test(SolicitudesProveedor::class)
            ->call('create')
            ->set('form.folio', 'SP-E2E-001')
            ->set('form.vendor_id', $proveedor->id)
            ->set('form.fecha_solicitud', '2026-08-25')
            ->set('form.tipo_solicitud', 'regular')
            ->set('form.proyecto_presupuesto_articulo_id', $articuloPresupuesto->id)
            ->set('lineas.0.articulo_id', $articuloCatalogo->id)
            ->set('lineas.0.cantidad_solicitada', 2)
            ->set('lineas.0.precio_unitario_cotizado', 15000)
            ->set('lineas.0.es_activo_inventariable', true)
            ->call('save')
            ->assertHasNoErrors();

        $solicitudProveedor = SolicitudProveedor::where('folio', 'SP-E2E-001')->firstOrFail();

        // Propagación de referencias: la SolicitudProveedor sabe de qué
        // artículo de presupuesto viene, y NO trae SIC (origen distinto al
        // camino 2).
        $this->assertSame($articuloPresupuesto->id, $solicitudProveedor->proyecto_presupuesto_articulo_id);
        $this->assertNull($solicitudProveedor->sic_id);
        $this->assertSame(SolicitudProveedor::ESTATUS_SOLICITADA, $solicitudProveedor->estatus);

        $solicitudLinea = $solicitudProveedor->lineas()->firstOrFail();
        $this->assertSame($articuloCatalogo->id, $solicitudLinea->articulo_id);
        $this->assertSame(2, $solicitudLinea->cantidad_solicitada);

        $this->assertSame(1, Livewire::test(Dashboard::class)->viewData('solicitudesProveedorPendientes'));

        // ==============================================================
        // Recepción de Proveedor — genera los Asset reales.
        // ==============================================================
        Livewire::test(Recepciones::class)
            ->call('create')
            ->set('selectedSolicitudId', $solicitudProveedor->id)
            ->set('form.folio_remision', 'REM-E2E-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacionAlmacen->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-E2E-001')
            ->set('lineas.0.unidades.1.numero_serie', 'SN-E2E-002')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Asset::count());
        $assets = Asset::orderBy('id')->get();

        foreach ($assets as $asset) {
            $this->assertSame('en_stock', $asset->estatus->codigo, 'Sin SIC en la solicitud, el Asset debe quedar libre en_stock, no reservado.');
            $this->assertSame($ubicacionAlmacen->id, $asset->ubicacion_actual_id);
            $this->assertSame($marca->id, $asset->marca_id);
            $this->assertSame($tipoEquipo->id, $asset->tipo_equipo_id);
            $this->assertSame($proveedor->id, $asset->vendor_id);
            $this->assertEquals(15000, (float) $asset->costo_adquisicion);
            $this->assertNull($asset->sic_reservada_id);

            // Propagación de referencias — el Asset sabe de qué
            // RecepcionLinea viene, y esa línea sabe de qué línea de
            // solicitud y de qué recepción viene (bidireccional).
            $this->assertNotNull($asset->recepcion_linea_id);
            $recepcionLinea = RecepcionLinea::findOrFail($asset->recepcion_linea_id);
            $this->assertSame($asset->id, $recepcionLinea->asset_id);
            $this->assertSame($solicitudLinea->id, $recepcionLinea->solicitud_proveedor_linea_id);

            // Fix aplicado en esta sesión: el Asset debe saber de qué
            // Proyecto de Presupuesto viene (vía la SolicitudProveedor que
            // originó su Recepción) — antes del fix esta columna quedaba
            // NULL siempre.
            $this->assertSame(
                $proyecto->id,
                $asset->proyecto_presupuesto_id,
                'Bug de integración: assets.proyecto_presupuesto_id no se propagaba desde Recepciones::save().'
            );
        }

        $recepcion = Recepcion::where('folio_remision', 'REM-E2E-001')->firstOrFail();
        $this->assertSame($solicitudProveedor->id, $recepcion->solicitud_proveedor_id);

        $solicitudProveedor->refresh();
        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $solicitudProveedor->estatus);
        $this->assertSame(2, $solicitudProveedor->lineas->first()->cantidad_recibida);

        // Recibida (no "solicitada"/"parcialmente_recibida") ya no cuenta
        // como pendiente en el Dashboard.
        $this->assertSame(0, Livewire::test(Dashboard::class)->viewData('solicitudesProveedorPendientes'));

        // Stock: 2 en_stock contra un mínimo de 2 -> breach resuelto.
        $this->assertCount(0, StockMinimo::enBreach());
        $this->assertCount(0, Livewire::test(Dashboard::class)->viewData('stockBajoMinimo'));

        // ==============================================================
        // Asignación de Activo — conecta el Asset físico con la SIC
        // autorizada del camino 2 (independiente de que el Asset haya
        // llegado por la vía de Presupuesto de Proyecto, no por una SIC).
        // ==============================================================
        $assetAAsignar = $assets->first();

        Livewire::test(Asignaciones::class)
            ->call('create')
            ->set('form.sic_id', $sic->id)
            ->set('form.asset_id', $assetAAsignar->id)
            ->set('form.fecha_asignacion', '2026-09-05')
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $validador->id)
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::where('asset_id', $assetAAsignar->id)->firstOrFail();

        // La asignación deriva empleado/ticket de la SIC, no de campos de
        // formulario independientes — confirma la propagación de
        // referencias del camino 2 hacia el registro final.
        $this->assertSame($solicitanteEmpleado->id, $assignment->empleado_id);
        $this->assertSame($ticket->id, $assignment->ticket_id);
        $this->assertSame($sic->id, $assignment->sic_id);

        $assetAAsignar->refresh();
        $this->assertSame('asignado', $assetAAsignar->estatus->codigo);

        // exportResponsivaPdf no debe tronar aunque no se haya subido la
        // responsiva firmada todavía (PDF en blanco para imprimir/firmar).
        Livewire::test(Asignaciones::class)
            ->call('exportResponsivaPdf', $assignment->id)
            ->assertFileDownloaded('responsiva-'.$assetAAsignar->codigo.'.pdf');

        // La SIC ya asignada deja de aparecer como pendiente de asignación.
        $sicOptionsRestantes = Livewire::test(Asignaciones::class)->call('create')->viewData('sicOptions');
        $this->assertFalse($sicOptionsRestantes->pluck('id')->contains($sic->id));

        // Stock: al asignar 1 de los 2 Asset en_stock, el stock libre baja a
        // 1 (< mínimo 2) -> el breach reaparece. Confirma que el estatus del
        // Asset (no un contador aparte) es lo que Stock/Dashboard leen en
        // vivo.
        $this->assertCount(1, StockMinimo::enBreach());
        $this->assertCount(1, Livewire::test(Dashboard::class)->viewData('stockBajoMinimo'));

        // ==============================================================
        // Facturación — 1 factura que coincide con el total cotizado de la
        // remisión, y 1 factura que NO coincide (sin remisiones vinculadas).
        // ==============================================================
        $this->assertSame(0, Livewire::test(Dashboard::class)->viewData('facturasPendientes'));
        $this->assertSame(0, Livewire::test(Dashboard::class)->viewData('facturasDiferencia'));

        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-E2E-001')
            ->set('form.vendor_id', $proveedor->id)
            ->set('form.fecha_recepcion', '2026-09-10')
            ->set('form.monto_total', 30000)
            ->set('form.moneda', 'MXN')
            ->set('recepcionIds', [$recepcion->id])
            ->call('save')
            ->assertHasNoErrors();

        $facturaCoincide = Invoice::where('folio_factura', 'FAC-E2E-001')->firstOrFail();
        $this->assertFalse($facturaCoincide->diferencia_a_revisar, 'monto_total (30000) coincide exactamente con 2 x 15000 cotizado.');
        $this->assertSame(Invoice::ESTATUS_RECIBIDA, $facturaCoincide->estatus);

        // Asset.invoice_id se llena para ambas líneas inventariables de la
        // remisión vinculada — propagación de referencia final de la cadena.
        foreach ($assets as $asset) {
            $this->assertSame($facturaCoincide->id, $asset->fresh()->invoice_id);
        }

        // Todas las Recepcion de esta SolicitudProveedor (solo 1) ya tienen
        // factura -> transiciona a "facturada".
        $this->assertSame(SolicitudProveedor::ESTATUS_FACTURADA, $solicitudProveedor->fresh()->estatus);

        $this->assertSame(1, Livewire::test(Dashboard::class)->viewData('facturasPendientes'));
        $this->assertSame(0, Livewire::test(Dashboard::class)->viewData('facturasDiferencia'));

        // Factura sin remisiones vinculadas y con monto distinto de 0 ->
        // "no coincide" (comparación exacta contra un total cotizado de 0).
        Livewire::test(Facturas::class)
            ->call('create')
            ->set('form.folio_factura', 'FAC-E2E-002')
            ->set('form.vendor_id', $proveedor->id)
            ->set('form.fecha_recepcion', '2026-09-11')
            ->set('form.monto_total', 999)
            ->set('form.moneda', 'MXN')
            ->call('save')
            ->assertHasNoErrors();

        $facturaNoCoincide = Invoice::where('folio_factura', 'FAC-E2E-002')->firstOrFail();
        $this->assertTrue($facturaNoCoincide->diferencia_a_revisar);

        $this->assertSame(2, Livewire::test(Dashboard::class)->viewData('facturasPendientes'));
        $this->assertSame(1, Livewire::test(Dashboard::class)->viewData('facturasDiferencia'));

        // Transiciones secuenciales de la factura que sí coincide hasta
        // "pagada" — deja de contar como pendiente en el Dashboard, la otra
        // factura sigue pendiente y sigue con diferencia.
        Livewire::test(Facturas::class)->call('marcarRegistrada', $facturaCoincide->id);
        $this->assertSame(Invoice::ESTATUS_REGISTRADA, $facturaCoincide->fresh()->estatus);

        Livewire::test(Facturas::class)->call('marcarAutorizada', $facturaCoincide->id);
        $this->assertSame(Invoice::ESTATUS_AUTORIZADA, $facturaCoincide->fresh()->estatus);

        Livewire::test(Facturas::class)->call('marcarPagada', $facturaCoincide->id);
        $facturaCoincide->refresh();
        $this->assertSame(Invoice::ESTATUS_PAGADA, $facturaCoincide->estatus);
        $this->assertNotNull($facturaCoincide->fecha_pago);

        $this->assertSame(1, Livewire::test(Dashboard::class)->viewData('facturasPendientes'));
        $this->assertSame(1, Livewire::test(Dashboard::class)->viewData('facturasDiferencia'));

        // ==============================================================
        // Confirmación final: no existe un modelo AuditLog transversal en
        // este módulo (confirmado leyendo Modules/GestionTI/app/Models/ al
        // preparar este test, no asumido) — sigue siendo una fase futura
        // separada, documentada como tal en docs/gestionti-progreso.md. La
        // pieza más cercana a una bitácora transversal de las transiciones
        // de este recorrido es `AvisoEnviado`, ya ejercitada arriba.
        // ==============================================================
        $this->assertFalse(class_exists(\Modules\GestionTI\Models\AuditLog::class));
        $this->assertGreaterThanOrEqual(6, AvisoEnviado::count());
    }
}
