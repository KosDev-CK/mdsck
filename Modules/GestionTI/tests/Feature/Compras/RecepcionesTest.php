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
use Modules\GestionTI\Models\Almacenamiento;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\EbsArticulo;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\LugarEntrega;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Procesador;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Ram;
use Modules\GestionTI\Models\Recepcion;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudProveedorLinea;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Las líneas de prueba se entregan en Zurich salvo que el test indique
        // otro lugar (o `null` explícito).
        SolicitudProveedorLinea::creating(function (SolicitudProveedorLinea $linea) {
            if (! array_key_exists('lugar_entrega_id', $linea->getAttributes())) {
                $linea->lugar_entrega_id = LugarEntrega::where('nombre', 'Zurich')->value('id');
            }
        });
    }

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

        // El técnico receptor es quien tiene la sesión: un Validador ligado a
        // su usuario, sin sitio asignado (puede recibir en cualquiera). Zurich
        // llega a "Almacén Central".
        Validador::create(['nombre' => 'Ana Torres', 'user_id' => $user->id]);
        $this->ubicacion();

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
        return Validador::where('user_id', auth()->id())->firstOrFail();
    }

    private function ubicacion(): Ubicacion
    {
        $ubicacion = Ubicacion::firstOrCreate(['nombre' => 'Almacén Central']);
        $ubicacion->update(['lugar_entrega_id' => LugarEntrega::where('nombre', 'Zurich')->value('id')]);

        return $ubicacion;
    }

    private function lugar(string $nombre): LugarEntrega
    {
        return LugarEntrega::where('nombre', $nombre)->firstOrFail();
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

    public function test_grid_lists_every_solicitud_with_the_pending_ones_first(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();

        $recibida = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_RECIBIDA]);
        $cancelada = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_CANCELADA]);
        $solicitada = $this->solicitudConLineaInventariable();
        $parcial = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA]);

        $ids = Livewire::test(Recepciones::class)->viewData('solicitudes')->pluck('id')->all();

        foreach ([$recibida, $cancelada, $solicitada, $parcial] as $solicitud) {
            $this->assertContains($solicitud->id, $ids);
        }

        $posicion = array_flip($ids);
        $this->assertLessThan($posicion[$recibida->id], $posicion[$solicitada->id]);
        $this->assertLessThan($posicion[$recibida->id], $posicion[$parcial->id]);
        $this->assertLessThan($posicion[$cancelada->id], $posicion[$solicitada->id]);
    }

    public function test_grid_can_be_filtered_to_pending_solicitudes(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();

        $recibida = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_RECIBIDA]);
        $solicitada = $this->solicitudConLineaInventariable();

        $ids = Livewire::test(Recepciones::class)
            ->set('estatusFiltro', 'pendientes')
            ->viewData('solicitudes')->pluck('id')->all();

        $this->assertContains($solicitada->id, $ids);
        $this->assertNotContains($recibida->id, $ids);
    }

    public function test_opening_a_received_solicitud_shows_its_history_but_does_not_allow_a_new_reception(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();

        $solicitud = $this->solicitudConLineaInventariable(['estatus' => SolicitudProveedor::ESTATUS_RECIBIDA]);
        $solicitud->recepciones()->create([
            'folio_remision' => 'REM-HISTORIAL-1',
            'fecha_recepcion' => '2026-09-01',
            'recibido_por_id' => $validador->id,
            'ubicacion_id' => $ubicacion->id,
        ]);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->assertSet('showModal', true)
            ->assertSet('lineas', [])
            ->assertSee('REM-HISTORIAL-1')
            ->assertSee('ya no admite recepciones nuevas');

        $this->assertFalse($component->viewData('puedeRecibir'));

        $component
            ->set('form.folio_remision', 'REM-NO-DEBE-GUARDAR')
            ->set('form.fecha_recepcion', '2026-09-02')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->call('save');

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-NO-DEBE-GUARDAR']);
    }

    public function test_scanning_an_exact_folio_opens_that_solicitud(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();

        $solicitud = $this->solicitudConLineaInventariable(['folio' => 'SP-261002-001']);
        $this->solicitudConLineaInventariable(['folio' => 'SP-261002-002']);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirPorCodigo', ' SP-261002-001 ')
            ->assertSet('showModal', true)
            ->assertSet('selectedSolicitudId', $solicitud->id)
            ->assertSet('search', '');

        $this->assertTrue($component->viewData('puedeRecibir'));
    }

    public function test_scanning_an_unknown_folio_shows_an_error_and_keeps_the_text_in_the_search(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->solicitudConLineaInventariable(['folio' => 'SP-261002-001']);

        Livewire::test(Recepciones::class)
            ->call('abrirPorCodigo', 'SP-NO-EXISTE')
            ->assertSet('showModal', false)
            ->assertSet('search', 'SP-NO-EXISTE')
            ->assertSee('No se encontró ninguna solicitud');
    }

    public function test_the_same_serial_number_cannot_be_captured_twice_in_one_reception(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'Lenovo']);
        $solicitud = $this->solicitudConLineaInventariable();

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-DUP-1')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'PW0GX3BT')
            ->set('lineas.0.unidades.1.numero_serie', ' pw0gx3bt ')
            ->call('save')
            ->assertHasErrors(['lineas.0.unidades.1.numero_serie']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-DUP-1']);
    }

    public function test_a_line_saved_as_non_inventariable_is_inventariable_in_reception_if_its_articulo_is_now_inventariable(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();

        $solicitud = $this->solicitudConLineaInventariable([], ['es_activo_inventariable' => false]);
        $solicitud->lineas->first()->articulo->update(['es_inventariable' => true]);

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id)->call('recibirTodoPendiente');

        $this->assertTrue($component->get('lineas.0.es_activo_inventariable'));
        $this->assertCount(2, $component->get('lineas.0.unidades'));
    }

    /**
     * Solicitud hecha con un artículo GENÉRICO no inventariable (el estándar al
     * que se mapea un ítem de EBS) — lo que realmente llega se elige en la
     * recepción.
     *
     * @return array{0: SolicitudProveedor, 1: ArticuloSolicitud, 2: ArticuloSolicitud}
     */
    private function solicitudConArticuloGenerico(): array
    {
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);

        $generico = ArticuloSolicitud::create([
            'codigo' => 'ART-LAPTOP-EJECUTIVA-ESTANDAR',
            'descripcion' => 'Laptop Ejecutiva',
            'unidad_medida' => 'pieza',
            'es_inventariable' => false,
        ]);
        EbsArticulo::create(['ebs_item_id' => 6962, 'articulo_id' => $generico->id]);

        $real = ArticuloSolicitud::create([
            'codigo' => 'ART-HUAWEI-B3-420',
            'descripcion' => 'Huawei MateBook B3-420',
            'unidad_medida' => 'pieza',
            'es_inventariable' => true,
            'tipo_equipo_id' => $tipoEquipo->id,
            'marca_id' => Marca::create(['nombre' => 'Huawei'])->id,
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-GENERICO-1',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-10-02',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'articulo_id' => $generico->id,
            'cantidad_solicitada' => 2,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => false,
        ]);

        return [$solicitud, $generico, $real];
    }

    public function test_a_generic_line_must_be_changed_to_the_real_articulo_before_receiving(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        [$solicitud, $generico] = $this->solicitudConArticuloGenerico();

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);

        $this->assertTrue($component->get('lineas.0.articulo_generico'));
        $this->assertFalse($component->get('lineas.0.es_activo_inventariable'));

        $component
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-GEN-1')
            ->set('form.fecha_recepcion', '2026-10-03')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->call('save')
            ->assertHasErrors(['lineas.0.articulo_id']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-GEN-1']);
        $this->assertSame(0, Asset::count());
    }

    public function test_choosing_the_real_inventariable_articulo_asks_for_serials_and_creates_the_assets_with_that_articulo(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        [$solicitud, $generico, $real] = $this->solicitudConArticuloGenerico();

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('lineas.0.articulo_id', $real->id);

        $this->assertTrue($component->get('lineas.0.es_activo_inventariable'));
        $this->assertCount(2, $component->get('lineas.0.unidades'));
        $this->assertSame($real->marca_id, $component->get('lineas.0.articulo_marca_id'));

        $component
            ->set('form.folio_remision', 'REM-GEN-2')
            ->set('form.fecha_recepcion', '2026-10-03')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.unidades.0.numero_serie', '4PHPM21B23000056')
            ->set('lineas.0.unidades.1.numero_serie', '4PHPM21B23000057')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Asset::where('articulo_id', $real->id)->count());
        $this->assertSame(0, Asset::where('articulo_id', $generico->id)->count());
        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $solicitud->fresh()->estatus);
        // La solicitud conserva el artículo genérico con el que se pidió.
        $this->assertSame($generico->id, $solicitud->lineas()->first()->articulo_id);
    }

    public function test_switching_back_to_the_generic_articulo_keeps_the_pieces_but_they_stop_being_inventariable(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, $generico, $real] = $this->solicitudConArticuloGenerico();

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('lineas.0.articulo_id', $real->id);
        $this->assertCount(2, $component->get('lineas.0.unidades'));
        $this->assertTrue($component->get('lineas.0.es_activo_inventariable'));

        // Vuelve al genérico: las piezas siguen (cada una necesita su artículo real) pero ninguna es inventariable.
        $component->set('lineas.0.articulo_id', $generico->id);
        $this->assertFalse($component->get('lineas.0.es_activo_inventariable'));
        $this->assertCount(2, $component->get('lineas.0.unidades'));
        $this->assertFalse(collect($component->viewData('infoUnidades')[0])->contains(fn ($pieza) => $pieza['inventariable']));
    }

    /**
     * Genérico de laptop y de PC, ambos mapeados desde EBS, con una laptop real
     * y una PC real (las dos inventariables).
     */
    private function escenarioTipos(): array
    {
        $laptop = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
        $pc = TipoEquipo::firstOrCreate(['nombre' => 'Desktop']);

        $nuevo = fn (string $codigo, string $descripcion, TipoEquipo $tipo, bool $inventariable) => ArticuloSolicitud::create([
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $tipo->id,
            'es_inventariable' => $inventariable,
        ]);

        $genericoLaptop = $nuevo('GEN-LAP', 'Laptop Ejecutiva', $laptop, false);
        $genericoPc = $nuevo('GEN-PC', 'PC de Escritorio Gerencial', $pc, false);
        EbsArticulo::create(['ebs_item_id' => 6962, 'articulo_id' => $genericoLaptop->id]);
        EbsArticulo::create(['ebs_item_id' => 1608, 'articulo_id' => $genericoPc->id]);

        $realLaptop = $nuevo('LAP-HUAWEI', 'Huawei MateBook B3-420', $laptop, true);
        $realPc = $nuevo('PC-DELL', 'Dell OptiPlex 7010', $pc, true);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-TIPOS-1',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-10-02',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create(['articulo_id' => $genericoLaptop->id, 'cantidad_solicitada' => 1, 'cantidad_recibida' => 0, 'es_activo_inventariable' => false]);
        $solicitud->lineas()->create(['articulo_id' => $genericoPc->id, 'cantidad_solicitada' => 1, 'cantidad_recibida' => 0, 'es_activo_inventariable' => false]);

        return [$solicitud, $realLaptop, $realPc, $genericoLaptop, $genericoPc];
    }

    public function test_the_article_search_only_offers_the_same_equipment_type_as_the_requested_article(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, $realLaptop, $realPc, $genericoLaptop, $genericoPc] = $this->escenarioTipos();

        $laptops = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->call('abrirBuscadorArticulo', 0)
            ->viewData('resultadosArticulos')->pluck('id')->all();

        $this->assertSame([$realLaptop->id], $laptops);

        $pcs = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->call('abrirBuscadorArticulo', 1)
            ->viewData('resultadosArticulos')->pluck('id')->all();

        $this->assertSame([$realPc->id], $pcs);
    }

    public function test_choosing_an_article_of_another_equipment_type_is_ignored_and_a_forced_one_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        [$solicitud, $realLaptop, $realPc] = $this->escenarioTipos();

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->call('abrirBuscadorArticulo', 0)
            ->call('elegirArticulo', $realPc->id);

        $this->assertNotSame($realPc->id, $component->get('lineas.0.articulo_id'));

        // Forzado directo en el estado del cliente: el servidor lo rechaza al guardar.
        $component
            ->set('lineas.0.articulo_id', $realPc->id)
            ->set('lineas.1.articulo_id', $realPc->id)
            ->set('form.folio_remision', 'REM-TIPOS-1')
            ->set('form.fecha_recepcion', '2026-10-03')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->call('save')
            ->assertHasErrors(['lineas.0.articulo_id']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-TIPOS-1']);
    }

    public function test_choosing_from_the_search_sets_the_article_and_closes_the_modal(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, $realLaptop] = $this->escenarioTipos();

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->call('abrirBuscadorArticulo', 0)
            ->assertSet('showArticuloModal', true)
            ->call('elegirArticulo', $realLaptop->id)
            ->assertSet('showArticuloModal', false)
            ->assertSet('lineas.0.articulo_id', $realLaptop->id);

        $this->assertTrue($component->get('lineas.0.es_activo_inventariable'));
    }

    public function test_the_article_search_shows_at_most_10_results_and_filters_by_the_typed_text(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, , , $genericoLaptop] = $this->escenarioTipos();

        for ($n = 1; $n <= 14; $n++) {
            ArticuloSolicitud::create([
                'codigo' => sprintf('LAP-EXTRA-%02d', $n),
                'descripcion' => "Laptop extra {$n}",
                'unidad_medida' => 'pieza',
                'tipo_equipo_id' => $genericoLaptop->tipo_equipo_id,
                'es_inventariable' => true,
            ]);
        }

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->call('abrirBuscadorArticulo', 0);

        $this->assertGreaterThan(10, $component->viewData('resultadosArticulos')->count());
        $component->assertSee('Se muestran 10 artículos');

        $filtrados = $component->set('articuloSearch', 'extra 14')->viewData('resultadosArticulos');
        $this->assertSame(['LAP-EXTRA-14'], $filtrados->pluck('codigo')->all());
    }

    public function test_the_article_search_matches_every_word_in_any_order_ignoring_case_accents_and_separators(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, , , $genericoLaptop] = $this->escenarioTipos();

        $lenovo = Marca::firstOrCreate(['nombre' => 'Lenovo']);
        $modelo = \Modules\GestionTI\Models\Modelo::create(['nombre' => 'Lenovo 20 RV', 'marca_id' => $lenovo->id]);
        $objetivo = ArticuloSolicitud::create([
            'codigo' => 'ART-LAPTOP-LENOVO-LENOVO-20-RV',
            'descripcion' => 'Laptop Lenovo Lenovo 20 RV',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $genericoLaptop->tipo_equipo_id,
            'marca_id' => $lenovo->id,
            'modelo_id' => $modelo->id,
            'es_inventariable' => true,
        ]);
        ArticuloSolicitud::create([
            'codigo' => 'ART-LAPTOP-LENOVO-20RS',
            'descripcion' => 'Laptop Lenovo 20RS',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $genericoLaptop->tipo_equipo_id,
            'marca_id' => $lenovo->id,
            'es_inventariable' => true,
        ]);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->call('abrirBuscadorArticulo', 0);

        foreach (['lenovo 20rv', '20rv lenovo', 'LENOVO 20-RV', 'lénovo   20 rv', 'laptop rv 20'] as $consulta) {
            $ids = $component->set('articuloSearch', $consulta)->viewData('resultadosArticulos')->pluck('id')->all();
            $this->assertSame([$objetivo->id], $ids, "La búsqueda \"{$consulta}\" debe encontrar solo el Lenovo 20 RV.");
        }

        // Una palabra que no existe en ningún campo no devuelve nada.
        $this->assertCount(0, $component->set('articuloSearch', 'lenovo inexistente')->viewData('resultadosArticulos'));
    }

    // --- Técnico = usuario en sesión, y recepción por sitio de entrega --------

    public function test_a_user_without_a_validador_cannot_receive(): void
    {
        $user = $this->actingUser();
        Validador::where('user_id', $user->id)->delete();
        $this->actingAs($user);
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable();

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);

        $this->assertFalse($component->viewData('puedeRecibir'));
        $component->assertSee('no está dado de alta como técnico receptor');

        $component
            ->set('form.folio_remision', 'REM-SIN-TEC')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save');

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-SIN-TEC']);
    }

    public function test_the_reception_is_registered_by_the_logged_in_technician_at_the_lines_site(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user);
        $this->estatusEnStock();
        $marca = Marca::create(['nombre' => 'Dell']);
        $solicitud = $this->solicitudConLineaInventariable();

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-SITIO-1')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-1')
            ->set('lineas.0.unidades.1.numero_serie', 'SN-2')
            ->call('save')
            ->assertHasNoErrors();

        $recepcion = Recepcion::where('folio_remision', 'REM-SITIO-1')->firstOrFail();
        $this->assertSame($this->validador()->id, $recepcion->recibido_por_id);
        $this->assertSame($user->id, $recepcion->registrado_por_user_id);
        $this->assertSame($this->lugar('Zurich')->id, $recepcion->lugar_entrega_id);
        $this->assertSame($this->ubicacion()->id, $recepcion->ubicacion_id);
        $this->assertSame(2, Asset::where('ubicacion_actual_id', $this->ubicacion()->id)->count());
    }

    public function test_a_technician_with_an_assigned_site_only_receives_the_lines_of_that_site(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user);
        $this->estatusEnStock();
        $ceda = $this->lugar('CEDA');
        Ubicacion::create(['nombre' => 'CEDA bodega', 'lugar_entrega_id' => $ceda->id]);
        $this->validador()->update(['lugar_entrega_id' => $ceda->id]);

        // 2 laptops a Zurich y 1 cable a CEDA en la misma solicitud.
        $solicitud = $this->solicitudConLineaInventariable(); // Zurich
        $solicitud->lineas()->create([
            'descripcion_libre' => 'Cable HDMI',
            'cantidad_solicitada' => 3,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => false,
            'lugar_entrega_id' => $ceda->id,
        ]);

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);

        $component->call('recibirTodoPendiente');
        $this->assertSame($ceda->id, $component->get('lugarRecepcionId'));
        $this->assertFalse($component->get('lineas.0.recibible'));
        $this->assertSame(0, $component->get('lineas.0.cantidad_a_recibir'));
        $this->assertTrue($component->get('lineas.1.recibible'));
        $this->assertSame(3, $component->get('lineas.1.cantidad_a_recibir'));

        // Intenta recibir también la línea de Zurich (forzando el estado del cliente): se rechaza.
        $component
            ->set('form.folio_remision', 'REM-CEDA-1')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->set('lineas.0.cantidad_a_recibir', 1)
            ->call('save')
            ->assertHasErrors(['lineas.0.cantidad_a_recibir']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-CEDA-1']);

        // Solo lo de CEDA: pasa, y queda ligado a CEDA.
        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-CEDA-2')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $recepcion = Recepcion::where('folio_remision', 'REM-CEDA-2')->firstOrFail();
        $this->assertSame($ceda->id, $recepcion->lugar_entrega_id);
        $this->assertSame(SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA, $solicitud->fresh()->estatus);
    }

    public function test_a_technician_without_an_assigned_site_must_pick_one_when_the_solicitud_has_several(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $ceda = $this->lugar('CEDA');
        Ubicacion::create(['nombre' => 'CEDA bodega', 'lugar_entrega_id' => $ceda->id]);

        $solicitud = $this->solicitudConLineaInventariable();
        $solicitud->lineas()->create([
            'descripcion_libre' => 'Cable HDMI',
            'cantidad_solicitada' => 3,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => false,
            'lugar_entrega_id' => $ceda->id,
        ]);

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);

        $this->assertNull($component->get('lugarRecepcionId'));
        $this->assertFalse($component->get('lineas.0.recibible'));

        $component
            ->set('form.folio_remision', 'REM-ELIGE-1')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['lugarRecepcionId']);

        $component->set('lugarRecepcionId', $ceda->id);
        $this->assertTrue($component->get('lineas.1.recibible'));
        $this->assertFalse($component->get('lineas.0.recibible'));
    }

    public function test_a_site_without_an_inventory_location_blocks_the_reception(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        Ubicacion::query()->update(['lugar_entrega_id' => null]);
        $solicitud = $this->solicitudConLineaInventariable([], ['es_activo_inventariable' => false, 'articulo_id' => null, 'descripcion_libre' => 'Cable']);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-SIN-UBI')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['lugarRecepcionId']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-SIN-UBI']);
    }

    public function test_a_site_with_several_ubicaciones_requires_choosing_one_of_its_own(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $zurich = $this->lugar('Zurich');
        $nave = Ubicacion::create(['nombre' => 'Zurich nave 2', 'lugar_entrega_id' => $zurich->id]);
        $ajena = Ubicacion::create(['nombre' => 'CEDA bodega', 'lugar_entrega_id' => $this->lugar('CEDA')->id]);
        $solicitud = $this->solicitudConLineaInventariable([], ['es_activo_inventariable' => false, 'articulo_id' => null, 'descripcion_libre' => 'Cable']);

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);

        // Zurich tiene 2 ubicaciones ("Almacén Central" del helper y la nave): no se elige sola, y solo se ofrecen las suyas.
        $this->assertNull($component->get('ubicacionDestinoId'));
        $this->assertEqualsCanonicalizing(
            [$this->ubicacion()->id, $nave->id],
            $component->viewData('ubicacionesDestino')->pluck('id')->all(),
        );

        $component
            ->set('form.folio_remision', 'REM-UBI-1')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['ubicacionDestinoId']);

        // Una ubicación de otro sitio forzada desde el cliente se rechaza.
        $component->set('ubicacionDestinoId', $ajena->id)->call('save')->assertHasErrors(['ubicacionDestinoId']);

        $component->set('ubicacionDestinoId', $nave->id)->call('recibirTodoPendiente')->call('save')->assertHasNoErrors();

        $this->assertSame($nave->id, Recepcion::where('folio_remision', 'REM-UBI-1')->firstOrFail()->ubicacion_id);
    }

    public function test_a_line_without_a_lugar_de_entrega_cannot_be_received(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable([], ['lugar_entrega_id' => null]);

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);

        $this->assertFalse($component->get('lineas.0.recibible'));
        $component->assertSee('no tiene lugar de entrega');
    }

    public function test_the_reception_date_cannot_be_in_the_future_nor_before_the_solicitud_date(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable(['fecha_solicitud' => '2026-09-10'], ['es_activo_inventariable' => false, 'articulo_id' => null, 'descripcion_libre' => 'Cable']);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-FECHA');

        $component->set('form.fecha_recepcion', now()->addDay()->format('Y-m-d'))->call('save')
            ->assertHasErrors(['form.fecha_recepcion']);
        $component->set('form.fecha_recepcion', '2026-09-09')->call('save')
            ->assertHasErrors(['form.fecha_recepcion']);
        $component->set('form.fecha_recepcion', '2026-09-10')->call('save')
            ->assertHasNoErrors();
    }

    public function test_each_line_shows_its_sic_folio_and_can_open_the_detail(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable([], ['folio_sic_manual' => 'SIC-MANUAL-77']);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->assertSee('SIC SIC-MANUAL-77');

        $this->assertSame('SIC-MANUAL-77', $component->get('lineas.0.sic_display'));

        $component->call('openSicDetalle', 0, 0)->assertSet('showDetalleModal', false);
    }

    // --- Administrador captura por otro técnico ------------------------------

    private function administradorSinValidador(): User
    {
        $user = $this->actingUser();
        $user->assignRole(Role::findOrCreate('Administrador', 'web'));
        Validador::where('user_id', $user->id)->delete();

        return $user;
    }

    public function test_an_administrator_can_register_the_reception_on_behalf_of_a_technician(): void
    {
        $admin = $this->administradorSinValidador();
        $this->actingAs($admin);
        $this->estatusEnStock();

        $ceda = $this->lugar('CEDA');
        Ubicacion::create(['nombre' => 'CEDA bodega', 'lugar_entrega_id' => $ceda->id]);
        $tecnico = Validador::create(['nombre' => 'Técnico CEDA', 'lugar_entrega_id' => $ceda->id]);

        $solicitud = $this->solicitudConLineaInventariable([], ['lugar_entrega_id' => $ceda->id, 'es_activo_inventariable' => false, 'articulo_id' => null, 'descripcion_libre' => 'Cable', 'cantidad_solicitada' => 3]);

        $component = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);

        // Sin técnico elegido (el admin no es técnico) todavía no puede guardar.
        $this->assertFalse($component->viewData('puedeRecibir'));
        $component->assertSee('Selecciona al técnico que recibió');

        $component->set('tecnicoRecibeId', $tecnico->id);
        $this->assertTrue($component->viewData('puedeRecibir'));
        $this->assertSame($ceda->id, $component->get('lugarRecepcionId'));

        $component
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-ADMIN-1')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $recepcion = Recepcion::where('folio_remision', 'REM-ADMIN-1')->firstOrFail();
        $this->assertSame($tecnico->id, $recepcion->recibido_por_id);
        $this->assertSame($admin->id, $recepcion->registrado_por_user_id);
        $this->assertSame($ceda->id, $recepcion->lugar_entrega_id);
    }

    public function test_an_administrator_must_pick_the_technician_before_saving(): void
    {
        $this->actingAs($this->administradorSinValidador());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable([], ['es_activo_inventariable' => false, 'articulo_id' => null, 'descripcion_libre' => 'Cable']);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-ADMIN-2')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['recibido_por']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-ADMIN-2']);
    }

    public function test_a_non_administrator_cannot_receive_on_behalf_of_another_technician(): void
    {
        $user = $this->actingUser();
        $this->actingAs($user);
        $this->estatusEnStock();
        $otro = Validador::create(['nombre' => 'Otro técnico']);
        $solicitud = $this->solicitudConLineaInventariable([], ['es_activo_inventariable' => false, 'articulo_id' => null, 'descripcion_libre' => 'Cable']);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('tecnicoRecibeId', $otro->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-NOADMIN-1')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $recepcion = Recepcion::where('folio_remision', 'REM-NOADMIN-1')->firstOrFail();
        $this->assertSame($this->validador()->id, $recepcion->recibido_por_id);
        $this->assertNotSame($otro->id, $recepcion->recibido_por_id);
    }

    public function test_the_grid_flags_overdue_pending_solicitudes_and_the_history_flags_late_deliveries(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();

        $vencida = $this->solicitudConLineaInventariable(['fecha_solicitud' => '2026-09-01', 'fecha_entrega_prometida' => now()->subDays(2)->toDateString()]);
        $vigente = $this->solicitudConLineaInventariable(['fecha_entrega_prometida' => now()->addDays(2)->toDateString()]);

        // La fila de cada solicitud (la ayuda de la pantalla también menciona la palabra).
        $html = Livewire::test(Recepciones::class)->html();
        $fila = fn (SolicitudProveedor $solicitud) => (string) (preg_match('/wire:key="solicitud-'.$solicitud->id.'".*?<\/tr>/s', $html, $m) ? $m[0] : '');

        $this->assertStringContainsString('Vencida', $fila($vencida));
        $this->assertStringNotContainsString('Vencida', $fila($vigente));

        // Entrega con retraso: llegó 3 días después de la fecha prometida.
        $entregada = $this->solicitudConLineaInventariable(['fecha_solicitud' => '2026-09-01', 'fecha_entrega_prometida' => '2026-09-04', 'estatus' => SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA]);
        $entregada->recepciones()->create([
            'folio_remision' => 'REM-TARDE',
            'fecha_recepcion' => '2026-09-07',
            'recibido_por_id' => $validador->id,
            'ubicacion_id' => $ubicacion->id,
        ]);
        $entregada->recepciones()->create([
            'folio_remision' => 'REM-A-TIEMPO',
            'fecha_recepcion' => '2026-09-03',
            'recibido_por_id' => $validador->id,
            'ubicacion_id' => $ubicacion->id,
        ]);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $entregada->id)
            ->call('recibirTodoPendiente')
            ->assertSee('Con retraso de 3 d')
            ->assertSee('A tiempo');
    }

    // --- Flujo de escaneo: QR de la solicitud → código de la línea → números de serie ---

    public function test_scanning_the_qr_then_a_line_code_then_serials_fills_the_units_in_order(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable(['folio' => 'SP-ESC-001']); // 1 línea, 2 laptops

        $component = Livewire::test(Recepciones::class)
            ->call('escanear', 'SP-ESC-001')                // QR de la cabecera
            ->assertSet('showModal', true)
            ->assertSet('selectedSolicitudId', $solicitud->id)
            ->assertSet('lineaActiva', null)
            ->call('escanear', 'SP-ESC-001-L1')             // código de la línea
            ->assertSet('lineaActiva', 0)
            ->call('escanear', '4PHPM21B23000056')          // serie del equipo 1
            ->call('escanear', '4PHPM21B23000057');         // serie del equipo 2

        $this->assertSame('4PHPM21B23000056', $component->get('lineas.0.unidades.0.numero_serie'));
        $this->assertSame('4PHPM21B23000057', $component->get('lineas.0.unidades.1.numero_serie'));

        // Cada serie escaneado sumó una pieza a la recepción; con las 2 pendientes completas ya no cabe otra.
        $this->assertSame(2, $component->get('lineas.0.cantidad_a_recibir'));
        $component->call('escanear', '4PHPM21B23000058')->assertSee('ya no tiene piezas pendientes');
        $this->assertCount(2, $component->get('lineas.0.unidades'));
    }

    public function test_scanning_a_line_code_from_the_grid_opens_the_solicitud_and_activates_the_line(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable(['folio' => 'SP-ESC-002']);

        Livewire::test(Recepciones::class)
            ->call('escanear', 'SP-ESC-002-L1')
            ->assertSet('showModal', true)
            ->assertSet('selectedSolicitudId', $solicitud->id)
            ->assertSet('lineaActiva', 0);
    }

    public function test_a_serial_scanned_without_an_active_line_is_rejected_and_duplicates_are_caught(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable(['folio' => 'SP-ESC-003']);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->call('escanear', 'SN-SUELTO')
            ->assertSee('Escanea primero el código de la línea');

        $this->assertSame('', $component->get('lineas.0.unidades.0.numero_serie'));

        $component
            ->call('escanear', 'SP-ESC-003-L1')
            ->call('escanear', 'SN-REPETIDO')
            ->call('escanear', 'sn-repetido')
            ->assertSee('ya se escaneó en esta recepción');

        $this->assertSame('', $component->get('lineas.0.unidades.1.numero_serie'));
    }

    public function test_scanning_a_serial_of_an_existing_asset_warns_but_captures_it(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable(['folio' => 'SP-ESC-004']);
        Asset::create([
            'codigo' => 'KOS-LAPTOP-000001',
            'tipo_equipo_id' => TipoEquipo::firstOrCreate(['nombre' => 'Laptop'])->id,
            'numero_serie' => 'SN-YA-EXISTE',
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $this->estatusEnStock()->id,
        ]);

        $component = Livewire::test(Recepciones::class)
            ->call('escanear', 'SP-ESC-004-L1')
            ->call('escanear', 'SN-YA-EXISTE')
            ->assertSee('ya existe un activo con el número de serie');

        $this->assertSame('SN-YA-EXISTE', $component->get('lineas.0.unidades.0.numero_serie'));
    }

    public function test_a_line_that_cannot_be_received_here_cannot_be_activated_by_scanning_it(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $ceda = $this->lugar('CEDA');
        $this->validador()->update(['lugar_entrega_id' => $this->lugar('Zurich')->id]);
        $solicitud = $this->solicitudConLineaInventariable(['folio' => 'SP-ESC-005'], ['lugar_entrega_id' => $ceda->id]);

        Livewire::test(Recepciones::class)
            ->call('escanear', 'SP-ESC-005-L1')
            ->assertSet('lineaActiva', null)
            ->assertSee('se entrega en CEDA');
    }

    public function test_scanning_an_unknown_line_number_shows_an_error(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->solicitudConLineaInventariable(['folio' => 'SP-ESC-006']);

        Livewire::test(Recepciones::class)
            ->call('escanear', 'SP-ESC-006-L9')
            ->assertSet('lineaActiva', null)
            ->assertSee('no tiene la línea 9');
    }

    public function test_a_solicitud_can_be_received_in_several_deliveries_each_with_its_own_remision(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $marca = Marca::create(['nombre' => 'Dell']);
        $solicitud = $this->solicitudConLineaInventariable([], ['cantidad_solicitada' => 3]);

        // Las líneas arrancan en 0: se captura solo lo que llegó en la remisión.
        $primera = Livewire::test(Recepciones::class)->call('abrirSolicitud', $solicitud->id);
        $this->assertSame(0, $primera->get('lineas.0.cantidad_a_recibir'));
        $this->assertSame([], $primera->get('lineas.0.unidades'));

        $primera
            ->set('form.folio_remision', 'REM-ENTREGA-1')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->set('lineas.0.cantidad_a_recibir', 1)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-A')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA, $solicitud->fresh()->estatus);

        // Segunda entrega (otra remisión) por escáner: cada serie suma una pieza.
        $segunda = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('escanear', $solicitud->folio.'-L1')
            ->call('escanear', 'SN-B')
            ->call('escanear', 'SN-C')
            ->set('form.folio_remision', 'REM-ENTREGA-2')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->set('lineas.0.marca_id', $marca->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(SolicitudProveedor::ESTATUS_RECIBIDA, $solicitud->fresh()->estatus);
        $this->assertSame(['REM-ENTREGA-1', 'REM-ENTREGA-2'], Recepcion::where('solicitud_proveedor_id', $solicitud->id)->orderBy('id')->pluck('folio_remision')->all());
        $this->assertSame(1, Recepcion::where('folio_remision', 'REM-ENTREGA-1')->firstOrFail()->lineas()->count());
        $this->assertSame(2, Recepcion::where('folio_remision', 'REM-ENTREGA-2')->firstOrFail()->lineas()->count());
        $this->assertSame(3, Asset::count());
    }

    // --- Un artículo distinto por pieza ------------------------------------------

    public function test_each_piece_of_a_line_can_be_a_different_real_articulo(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        [$solicitud, $generico, $realA] = $this->solicitudConArticuloGenerico();

        $realB = ArticuloSolicitud::create([
            'codigo' => 'ART-LENOVO-THINKBOOK',
            'descripcion' => 'Lenovo ThinkBook 14',
            'unidad_medida' => 'pieza',
            'es_inventariable' => true,
            'tipo_equipo_id' => $realA->tipo_equipo_id,
            'marca_id' => Marca::create(['nombre' => 'Lenovo'])->id,
        ]);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente');

        // Línea de 1 laptop genérica + otra (PC) del escenario: usamos la primera línea ampliada a 2 piezas.
        $solicitud->lineas()->orderBy('id')->first()->update(['cantidad_solicitada' => 2]);
        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->set('lineas.0.cantidad_a_recibir', 2)
            ->call('abrirBuscadorArticulo', 0, 0)
            ->call('elegirArticulo', $realA->id)
            ->call('abrirBuscadorArticulo', 0, 1)
            ->call('elegirArticulo', $realB->id);

        $this->assertSame($realA->id, $component->get('lineas.0.unidades.0.articulo_id'));
        $this->assertSame($realB->id, $component->get('lineas.0.unidades.1.articulo_id'));

        $component
            ->set('form.folio_remision', 'REM-DISTINTAS')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->set('lineas.0.unidades.0.numero_serie', 'SN-HUAWEI-1')
            ->set('lineas.0.unidades.1.numero_serie', 'SN-LENOVO-1')
            ->call('save')
            ->assertHasNoErrors();

        $huawei = Asset::where('numero_serie', 'SN-HUAWEI-1')->firstOrFail();
        $lenovo = Asset::where('numero_serie', 'SN-LENOVO-1')->firstOrFail();

        $this->assertSame($realA->id, $huawei->articulo_id);
        $this->assertSame($realA->marca_id, $huawei->marca_id);
        $this->assertSame($realB->id, $lenovo->articulo_id);
        $this->assertSame($realB->marca_id, $lenovo->marca_id);
        $this->assertSame($generico->id, $solicitud->lineas()->orderBy('id')->first()->articulo_id);
    }

    public function test_a_generic_line_needs_the_real_articulo_in_every_piece(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, , $realA] = $this->solicitudConArticuloGenerico();
        $solicitud->lineas()->orderBy('id')->first()->update(['cantidad_solicitada' => 2]);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->set('lineas.0.cantidad_a_recibir', 2)
            ->call('abrirBuscadorArticulo', 0, 0)
            ->call('elegirArticulo', $realA->id)
            ->set('form.folio_remision', 'REM-FALTA-UNA')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->set('lineas.0.unidades.0.numero_serie', 'SN-1')
            ->call('save')
            ->assertHasErrors(['lineas.0.unidades.1.articulo_id']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-FALTA-UNA']);
    }

    public function test_a_piece_with_a_non_inventariable_articulo_is_received_without_an_asset(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, , $realA] = $this->solicitudConArticuloGenerico();
        $solicitud->lineas()->orderBy('id')->first()->update(['cantidad_solicitada' => 2]);

        $consumible = ArticuloSolicitud::create([
            'codigo' => 'ART-LAPTOP-CONSUMIBLE',
            'descripcion' => 'Laptop sin alta de activo',
            'unidad_medida' => 'pieza',
            'es_inventariable' => false,
            'tipo_equipo_id' => $realA->tipo_equipo_id,
        ]);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->set('lineas.0.cantidad_a_recibir', 2)
            ->call('abrirBuscadorArticulo', 0, 0)
            ->call('elegirArticulo', $realA->id)
            ->call('abrirBuscadorArticulo', 0, 1)
            ->call('elegirArticulo', $consumible->id)
            ->set('form.folio_remision', 'REM-MIXTA')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->set('lineas.0.unidades.0.numero_serie', 'SN-ACTIVO')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Asset::count());
        $recepcion = Recepcion::where('folio_remision', 'REM-MIXTA')->firstOrFail();
        $this->assertSame(2, $recepcion->lineas()->count());
        $this->assertSame(1, $recepcion->lineas()->whereNull('asset_id')->where('articulo_id', $consumible->id)->count());
        $this->assertSame(2, $solicitud->lineas()->orderBy('id')->first()->fresh()->cantidad_recibida);
    }

    public function test_choosing_an_article_of_another_type_for_a_piece_is_ignored(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        [$solicitud, $generico, $real] = $this->solicitudConArticuloGenerico();
        $generico->update(['tipo_equipo_id' => $real->tipo_equipo_id]);
        $pc = ArticuloSolicitud::create([
            'codigo' => 'PC-AJENA',
            'descripcion' => 'PC de otro tipo',
            'unidad_medida' => 'pieza',
            'es_inventariable' => true,
            'tipo_equipo_id' => TipoEquipo::firstOrCreate(['nombre' => 'Desktop'])->id,
        ]);
        $solicitud->lineas()->orderBy('id')->first()->update(['cantidad_solicitada' => 2]);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->set('lineas.0.cantidad_a_recibir', 2)
            ->call('abrirBuscadorArticulo', 0, 1)
            ->call('elegirArticulo', $pc->id);

        $this->assertNull($component->get('lineas.0.unidades.1.articulo_id'));
    }

    public function test_a_reception_with_nothing_captured_is_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $solicitud = $this->solicitudConLineaInventariable();

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->set('form.folio_remision', 'REM-VACIA')
            ->set('form.fecha_recepcion', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['lineas']);

        $this->assertDatabaseMissing('recepciones', ['folio_remision' => 'REM-VACIA']);
    }

    public function test_opening_a_pending_solicitud_loads_its_lines_for_reception(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();

        $solicitud = $this->solicitudConLineaInventariable();

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->assertSet('showModal', true)
            ->assertSet('selectedSolicitudId', $solicitud->id);

        $this->assertTrue($component->viewData('puedeRecibir'));
        $this->assertNotEmpty($component->get('lineas'));
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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

        $solicitud = $this->solicitudConLineaInventariable([], ['sic_id' => $sic->id, 'cantidad_solicitada' => 1]);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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

    /**
     * Rediseño de Solicitud a Proveedores (de 1 a N SICs, `sic_id` movido de
     * la cabecera a la línea): 2 líneas inventariables de la MISMA
     * solicitud, una con SIC y otra sin ella, deben producir Assets con
     * reservación independiente — antes de este cambio la decisión era una
     * sola por recepción completa a partir de la cabecera.
     */
    public function test_lines_with_different_sic_reserve_independently_within_the_same_solicitud(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $this->estatusReservado();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'Lenovo']);
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);

        $empresa = Empresa::create(['razon_social' => 'Kosmos Mix', 'nombre_comercial' => 'Kosmos Mix']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-MIX', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $empleado = Empleado::create(['numero_empleado' => 'EMP-MIX', 'nombre' => 'Solicitante Mix']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);

        $sic = SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $tipoEquipo->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
            'estatus' => 'autorizada',
            'folio_sic' => 'SIC-MIX',
        ]);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-MIX',
            'descripcion' => 'Laptop mixta',
            'unidad_medida' => 'Pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REC-MIX',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'articulo_id' => $articulo->id,
            'sic_id' => $sic->id,
            'cantidad_solicitada' => 1,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => true,
        ]);
        $solicitud->lineas()->create([
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 1,
            'cantidad_recibida' => 0,
            'es_activo_inventariable' => true,
        ]);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-MIX-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-MIX-CON-SIC')
            ->set('lineas.1.marca_id', $marca->id)
            ->set('lineas.1.unidades.0.numero_serie', 'SN-MIX-SIN-SIC')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('assets', 2);

        $assetConSic = Asset::where('numero_serie', 'SN-MIX-CON-SIC')->firstOrFail();
        $this->assertSame($this->estatusReservado()->id, $assetConSic->estatus_id);
        $this->assertSame($sic->id, $assetConSic->sic_reservada_id);

        $assetSinSic = Asset::where('numero_serie', 'SN-MIX-SIN-SIC')->firstOrFail();
        $this->assertSame($this->estatusEnStock()->id, $assetSinSic->estatus_id);
        $this->assertNull($assetSinSic->sic_reservada_id);
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
        $ids = Livewire::test(Recepciones::class)->viewData('solicitudes')->pluck('id')->all();
        $this->assertContains($solicitud->id, $ids);

        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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

    /**
     * Catálogo unificado de Artículos (ver docs/gestionti-progreso.md) —
     * cuando el artículo de la línea SÍ trae marca/modelo/specs, el Asset se
     * crea con esos datos heredados sin pedir los selects manuales (que ni
     * siquiera se renderizan, ver la vista).
     */
    public function test_line_whose_articulo_has_marca_modelo_and_specs_inherits_them_without_manual_selects(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
        $marca = Marca::create(['nombre' => 'Dell']);
        $modelo = Modelo::create(['nombre' => 'Latitude 5440', 'marca_id' => $marca->id]);
        $procesador = Procesador::create(['nombre' => 'Core i7']);
        $ram = Ram::create(['nombre' => '16GB']);
        $almacenamiento = Almacenamiento::create(['nombre' => '512GB SSD']);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-FICHA-COMPLETA',
            'descripcion' => 'Laptop Core i7 16GB 512GB SSD',
            'unidad_medida' => 'Pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
            'marca_id' => $marca->id,
            'modelo_id' => $modelo->id,
            'procesador_id' => $procesador->id,
            'ram_id' => $ram->id,
            'almacenamiento_id' => $almacenamiento->id,
            'es_inventariable' => true,
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REC-FICHA',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'articulo_id' => $articulo->id,
            'cantidad_solicitada' => 1,
            'cantidad_recibida' => 0,
            'precio_unitario_cotizado' => 20000,
            'es_activo_inventariable' => true,
        ]);

        // Sin capturar marca_id/modelo_id/tipo_equipo_id manuales — el
        // artículo ya los trae todos.
        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-FICHA-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-FICHA-001')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame($articulo->id, $asset->articulo_id);
        $this->assertSame($marca->id, $asset->marca_id);
        $this->assertSame($modelo->id, $asset->modelo_id);
        $this->assertSame($tipoEquipo->id, $asset->tipo_equipo_id);
        $this->assertSame([
            'procesador' => 'Core i7',
            'ram' => '16GB',
            'almacenamiento' => '512GB SSD',
        ], $asset->especificaciones);

        $this->assertDatabaseHas('recepcion_lineas', [
            'asset_id' => $asset->id,
            'articulo_id' => $articulo->id,
        ]);
    }

    /**
     * Caso hermano: el artículo no trae marca/modelo — el fallback manual
     * (ya probado también por `test_line_whose_articulo_has_no_tipo_equipo_requires_the_extra_select`
     * para tipo de equipo) sigue funcionando para marca/modelo.
     */
    public function test_line_whose_articulo_has_no_marca_falls_back_to_the_manual_select(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $marca = Marca::create(['nombre' => 'Genérica']);
        $solicitud = $this->solicitudConLineaInventariable();

        // Sin capturar marca_id manual — debe fallar la validación.
        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-SIN-MARCA')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-SIN-MARCA-1')
            ->set('lineas.0.unidades.1.numero_serie', 'SN-SIN-MARCA-2')
            ->call('save')
            ->assertHasErrors(['lineas.0.marca_id']);

        $this->assertDatabaseCount('assets', 0);

        // Capturando la marca manualmente, ahora sí guarda.
        Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->set('form.folio_remision', 'REM-SIN-MARCA')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.marca_id', $marca->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-SIN-MARCA-1')
            ->set('lineas.0.unidades.1.numero_serie', 'SN-SIN-MARCA-2')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame($marca->id, $asset->marca_id);
    }

    /**
     * Cambiar el artículo recibido (distinto al de la solicitud) deja el
     * Asset/RecepcionLinea con el artículo REALMENTE recibido, no el
     * solicitado — sustitución del proveedor, etc.
     */
    public function test_changing_the_articulo_on_reception_keeps_the_actually_received_one(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatusEnStock();
        $validador = $this->validador();
        $ubicacion = $this->ubicacion();
        $tipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
        $marcaSolicitada = Marca::create(['nombre' => 'Dell']);
        $marcaRecibida = Marca::create(['nombre' => 'HP']);

        $articuloSolicitado = ArticuloSolicitud::create([
            'codigo' => 'ART-SOLICITADO',
            'descripcion' => 'Laptop Dell solicitada',
            'unidad_medida' => 'Pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
            'marca_id' => $marcaSolicitada->id,
            'es_inventariable' => true,
        ]);

        $articuloRecibido = ArticuloSolicitud::create([
            'codigo' => 'ART-RECIBIDO',
            'descripcion' => 'Laptop HP realmente entregada',
            'unidad_medida' => 'Pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
            'marca_id' => $marcaRecibida->id,
            'es_inventariable' => true,
        ]);

        $solicitud = SolicitudProveedor::create([
            'folio' => 'SP-REC-SUSTITUCION',
            'vendor_id' => $this->proveedor()->id,
            'fecha_solicitud' => '2026-08-01',
            'tipo_solicitud' => 'regular',
        ]);
        $solicitud->lineas()->create([
            'articulo_id' => $articuloSolicitado->id,
            'cantidad_solicitada' => 1,
            'cantidad_recibida' => 0,
            'precio_unitario_cotizado' => 18000,
            'es_activo_inventariable' => true,
        ]);

        $component = Livewire::test(Recepciones::class)
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
            ->assertSet('lineas.0.articulo_id', $articuloSolicitado->id)
            ->set('lineas.0.articulo_id', $articuloRecibido->id)
            ->assertSet('lineas.0.articulo_marca_id', $marcaRecibida->id);

        $component->set('form.folio_remision', 'REM-SUSTITUCION-001')
            ->set('form.fecha_recepcion', '2026-09-01')
            ->set('form.recibido_por_id', $validador->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('lineas.0.unidades.0.numero_serie', 'SN-SUSTITUCION-001')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame($articuloRecibido->id, $asset->articulo_id);
        $this->assertSame($marcaRecibida->id, $asset->marca_id);

        $this->assertDatabaseHas('recepcion_lineas', [
            'asset_id' => $asset->id,
            'articulo_id' => $articuloRecibido->id,
        ]);
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
            ->call('abrirSolicitud', $solicitud->id)
            ->call('recibirTodoPendiente')
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
