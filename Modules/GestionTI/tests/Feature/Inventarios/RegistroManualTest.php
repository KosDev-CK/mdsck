<?php

namespace Modules\GestionTI\Tests\Feature\Inventarios;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Inventarios\RegistroManual;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistroManualTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Registro Manual de Activo',
            'slug' => 'gestionti-registro-manual',
            'route_name' => 'gestionti.registro-manual.index',
            'permission_name' => 'screens.gestionti-registro-manual.manage',
            'icon' => 'plus-circle',
            'order' => 33,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function estatus(string $codigo, string $nombre): EstatusActivo
    {
        return EstatusActivo::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);
    }

    private function tipoEquipo(): TipoEquipo
    {
        return TipoEquipo::firstOrCreate(['nombre' => 'Laptop']);
    }

    private function ubicacion(): Ubicacion
    {
        return Ubicacion::firstOrCreate(['nombre' => 'CDMX Corporativo']);
    }

    private function validador(string $nombre = 'Ana Torres'): Validador
    {
        return Validador::create(['nombre' => $nombre]);
    }

    private function empleado(string $numero = 'EMP-1', string $nombre = 'Juan Pérez'): Empleado
    {
        return Empleado::create(['numero_empleado' => $numero, 'nombre' => $nombre]);
    }

    /** Estado base común a todos los tests que llenan el formulario completo. */
    private function baseForm(): array
    {
        return [
            'form.tipo_equipo_id' => $this->tipoEquipo()->id,
            'form.ubicacion_actual_id' => $this->ubicacion()->id,
            'form.dado_de_alta_por_id' => $this->validador('Quien da de alta')->id,
            'form.motivo_alta_manual' => 'Equipo encontrado en bodega sin registro previo.',
            'form.fecha_alta_stock' => '2026-09-01',
        ];
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/registro-manual')->assertForbidden();
    }

    public function test_destino_stock_creates_asset_en_stock_without_assignment(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        Livewire::test(RegistroManual::class)
            ->call('create')
            ->set('form.destino', 'stock')
            ->set($this->baseForm())
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame('alta_manual', $asset->origen_tipo);
        $this->assertSame('en_stock', $asset->estatus?->codigo);
        $this->assertStringStartsWith('KOS-LAPTOP-', $asset->codigo);
        $this->assertSame(0, AssetAssignment::count());
    }

    public function test_destino_empleado_creates_asset_asignado_and_assignment_without_sic_or_ticket(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('asignado', 'Asignado');

        $empleado = $this->empleado();
        $responsable = $this->validador('Responsable de entrega');

        Livewire::test(RegistroManual::class)
            ->call('create')
            ->set('form.destino', 'empleado')
            ->set($this->baseForm())
            ->set('form.empleado_id', $empleado->id)
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $responsable->id)
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame('alta_manual', $asset->origen_tipo);
        $this->assertSame('asignado', $asset->estatus?->codigo);

        $assignment = AssetAssignment::firstOrFail();
        $this->assertSame($asset->id, $assignment->asset_id);
        $this->assertSame($empleado->id, $assignment->empleado_id);
        $this->assertNull($assignment->sic_id);
        $this->assertNull($assignment->ticket_id);
        $this->assertSame('nuevo', $assignment->estado_equipo_entrega);
        $this->assertSame($responsable->id, $assignment->responsable_entrega_id);
    }

    /**
     * El `AssetAssignment` que este componente crea cuando `destino =
     * 'empleado'` cae en la misma tabla que ya lista `Asignaciones.php` —
     * confirma que no hace falta duplicar "Generar PDF"/"Adjuntar
     * responsiva firmada" en esta pantalla, ver decisión documentada en
     * docs/gestionti-progreso.md.
     */
    public function test_assignment_created_here_shows_up_in_the_asignaciones_screen_listing(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('asignado', 'Asignado');

        $empleado = $this->empleado();
        $responsable = $this->validador('Responsable de entrega');

        Livewire::test(RegistroManual::class)
            ->call('create')
            ->set('form.destino', 'empleado')
            ->set($this->baseForm())
            ->set('form.empleado_id', $empleado->id)
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $responsable->id)
            ->call('save')
            ->assertHasNoErrors();

        $assignment = AssetAssignment::firstOrFail();

        $records = Livewire::test(\Modules\GestionTI\Livewire\Inventarios\Asignaciones::class)
            ->viewData('records');

        $this->assertTrue($records->contains($assignment));
    }

    public function test_motivo_alta_manual_is_required_for_both_destinos(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');

        Livewire::test(RegistroManual::class)
            ->call('create')
            ->set('form.destino', 'stock')
            ->set($this->baseForm())
            ->set('form.motivo_alta_manual', '')
            ->call('save')
            ->assertHasErrors(['form.motivo_alta_manual' => 'required']);

        $this->assertSame(0, Asset::count());

        $this->estatus('asignado', 'Asignado');
        $empleado = $this->empleado('EMP-2', 'Otro empleado');
        $responsable = $this->validador('Otro responsable');

        Livewire::test(RegistroManual::class)
            ->call('create')
            ->set('form.destino', 'empleado')
            ->set($this->baseForm())
            ->set('form.motivo_alta_manual', '')
            ->set('form.empleado_id', $empleado->id)
            ->set('form.estado_equipo_entrega', 'nuevo')
            ->set('form.responsable_entrega_id', $responsable->id)
            ->call('save')
            ->assertHasErrors(['form.motivo_alta_manual' => 'required']);

        $this->assertSame(0, Asset::count());
    }

    public function test_destino_empleado_requires_empleado_estado_and_responsable(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('asignado', 'Asignado');

        Livewire::test(RegistroManual::class)
            ->call('create')
            ->set('form.destino', 'empleado')
            ->set($this->baseForm())
            ->call('save')
            ->assertHasErrors(['form.empleado_id', 'form.estado_equipo_entrega', 'form.responsable_entrega_id']);

        $this->assertSame(0, Asset::count());
        $this->assertSame(0, AssetAssignment::count());
    }

    public function test_listing_only_shows_assets_with_origen_tipo_alta_manual(): void
    {
        $this->actingAs($this->actingUser());
        $enStock = $this->estatus('en_stock', 'En stock');
        $tipo = $this->tipoEquipo();

        $manual = Asset::create([
            'codigo' => 'KOS-LAPTOP-000001',
            'tipo_equipo_id' => $tipo->id,
            'origen_tipo' => 'alta_manual',
            'estatus_id' => $enStock->id,
            'motivo_alta_manual' => 'Alta manual de prueba',
        ]);

        $otraCompra = Asset::create([
            'codigo' => 'KOS-LAPTOP-000002',
            'tipo_equipo_id' => $tipo->id,
            'origen_tipo' => 'compra',
            'estatus_id' => $enStock->id,
        ]);

        $historico = Asset::create([
            'codigo' => 'KOS-LAPTOP-000003',
            'tipo_equipo_id' => $tipo->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $enStock->id,
        ]);

        $records = Livewire::test(RegistroManual::class)->viewData('records');

        $this->assertTrue($records->contains($manual));
        $this->assertFalse($records->contains($otraCompra));
        $this->assertFalse($records->contains($historico));
    }

    /**
     * Catálogo unificado de Artículos (ver docs/gestionti-progreso.md) —
     * elegir un artículo inventariable precarga tipo/marca/modelo desde su
     * ficha técnica, y el Asset creado persiste `articulo_id`.
     */
    public function test_choosing_an_articulo_prefills_tipo_marca_modelo_and_the_asset_persists_articulo_id(): void
    {
        $this->actingAs($this->actingUser());
        $this->estatus('en_stock', 'En stock');
        $tipoEquipo = $this->tipoEquipo();
        $otroTipoEquipo = TipoEquipo::firstOrCreate(['nombre' => 'Monitor']);
        $marca = Marca::create(['nombre' => 'Dell']);
        $modelo = Modelo::create(['nombre' => 'Latitude 5440', 'marca_id' => $marca->id]);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-INV-001',
            'descripcion' => 'Laptop Core i7 16GB 512GB SSD',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
            'marca_id' => $marca->id,
            'modelo_id' => $modelo->id,
            'es_inventariable' => true,
        ]);

        $component = Livewire::test(RegistroManual::class)
            ->call('create')
            // Precarga con un tipo de equipo distinto al del artículo para
            // confirmar que elegir el artículo sí lo sobreescribe.
            ->set('form.tipo_equipo_id', $otroTipoEquipo->id)
            ->set('form.articulo_id', $articulo->id)
            ->assertSet('form.tipo_equipo_id', $tipoEquipo->id)
            ->assertSet('form.marca_id', $marca->id)
            ->assertSet('form.modelo_id', $modelo->id);

        $component->set($this->baseForm())
            ->set('form.destino', 'stock')
            ->call('save')
            ->assertHasNoErrors();

        $asset = Asset::firstOrFail();
        $this->assertSame($articulo->id, $asset->articulo_id);
    }

    /**
     * El listado de opciones de artículo solo trae activos e inventariables
     * — mismo criterio ya usado en Solicitud de SIC/Recepción de Proveedor.
     */
    public function test_articulo_options_only_include_active_and_inventariable_articulos(): void
    {
        $this->actingAs($this->actingUser());
        $tipoEquipo = $this->tipoEquipo();

        $inventariable = ArticuloSolicitud::create([
            'codigo' => 'ART-OK',
            'descripcion' => 'Inventariable activo',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $tipoEquipo->id,
            'es_inventariable' => true,
        ]);

        $noInventariable = ArticuloSolicitud::create([
            'codigo' => 'ART-NO-INV',
            'descripcion' => 'No inventariable',
            'unidad_medida' => 'pieza',
            'es_inventariable' => false,
        ]);

        $inactivo = ArticuloSolicitud::create([
            'codigo' => 'ART-INACTIVO',
            'descripcion' => 'Inventariable pero inactivo',
            'unidad_medida' => 'pieza',
            'es_inventariable' => true,
            'activo' => false,
        ]);

        $ids = Livewire::test(RegistroManual::class)->viewData('articuloOptions')->pluck('id')->all();

        $this->assertContains($inventariable->id, $ids);
        $this->assertNotContains($noInventariable->id, $ids);
        $this->assertNotContains($inactivo->id, $ids);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-registro-manual',
            'route_name' => 'gestionti.registro-manual.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-registro-manual.manage'));
    }
}
