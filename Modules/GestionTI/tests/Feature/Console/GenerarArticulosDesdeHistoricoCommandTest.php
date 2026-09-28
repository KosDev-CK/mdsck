<?php

namespace Modules\GestionTI\Tests\Feature\Console;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\GestionTI\Models\Almacenamiento;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\Modelo;
use Modules\GestionTI\Models\Procesador;
use Modules\GestionTI\Models\Ram;
use Modules\GestionTI\Models\TipoEquipo;
use Tests\TestCase;

/**
 * Cubre `gestionti:generar-articulos-desde-historico` — ver
 * docs/gestionti-progreso.md, entrada "Backfill de Artículos desde
 * histórico". No usa los ~3799 Asset reales de
 * `ImportarHistoricoCommandTest`; arma un fixture sintético pequeño
 * suficiente para probar el agrupamiento, la elección de specs
 * "representativas" por mayoría y la idempotencia.
 */
class GenerarArticulosDesdeHistoricoCommandTest extends TestCase
{
    use RefreshDatabase;

    private TipoEquipo $tipoEquipo;

    private Marca $marca;

    private Modelo $modelo;

    private EstatusActivo $estatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop', 'en_alcance' => true]);
        $this->marca = Marca::create(['nombre' => 'HP']);
        $this->modelo = Modelo::create(['nombre' => 'EliteBook 840', 'marca_id' => $this->marca->id]);
        $this->estatus = EstatusActivo::create(['codigo' => 'en_stock', 'nombre' => 'En stock']);
    }

    private function crearAsset(string $codigo, ?array $especificaciones, ?int $articuloId = null): Asset
    {
        return Asset::create([
            'codigo' => $codigo,
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'marca_id' => $this->marca->id,
            'modelo_id' => $this->modelo->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $this->estatus->id,
            'especificaciones' => $especificaciones,
            'articulo_id' => $articuloId,
        ]);
    }

    public function test_creates_one_articulo_per_group_using_the_most_frequent_specs(): void
    {
        // 2 Asset con la misma combinación (mayoría) + 1 con otra distinta —
        // debe ganar la mayoritaria (i5/8GB/1TB), no la del Asset con mayor
        // id ni la minoritaria.
        $this->crearAsset('KOS-LAPTOP-000001', [
            'procesador' => 'i5', 'ram' => '8GB', 'disco_duro' => '1TB',
        ]);
        $this->crearAsset('KOS-LAPTOP-000002', [
            'procesador' => 'i5', 'ram' => '8GB', 'disco_duro' => '1TB',
        ]);
        $this->crearAsset('KOS-LAPTOP-000003', [
            'procesador' => 'i7', 'ram' => '16GB', 'disco_duro' => '512GB SSD',
        ]);

        $exitCode = Artisan::call('gestionti:generar-articulos-desde-historico');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(1, ArticuloSolicitud::count());

        $articulo = ArticuloSolicitud::first();

        $this->assertSame($this->tipoEquipo->id, $articulo->tipo_equipo_id);
        $this->assertSame($this->marca->id, $articulo->marca_id);
        $this->assertSame($this->modelo->id, $articulo->modelo_id);
        $this->assertSame('i5', $articulo->procesador?->nombre);
        $this->assertSame('8GB', $articulo->ram?->nombre);
        $this->assertSame('1TB', $articulo->almacenamiento?->nombre);
        $this->assertSame('laptops_desktops', $articulo->categoria);
        $this->assertTrue($articulo->es_inventariable);
        $this->assertTrue($articulo->activo);
        $this->assertSame('pieza', $articulo->unidad_medida);
        $this->assertSame('Laptop HP EliteBook 840', $articulo->descripcion);
        $this->assertNotEmpty($articulo->codigo);

        // Los 3 Assets del grupo quedan vinculados al mismo Artículo, sin
        // importar si calzan exacto con la combinación "representativa".
        $this->assertSame(3, Asset::where('articulo_id', $articulo->id)->count());

        // El valor representativo se reutiliza vía firstOrCreate — no se
        // crea un duplicado de catálogo por cada corrida.
        $this->assertSame(1, Procesador::count());
        $this->assertSame(1, Ram::count());
        $this->assertSame(1, Almacenamiento::count());
    }

    public function test_creates_a_separate_articulo_per_distinct_tipo_marca_modelo_combination(): void
    {
        $this->crearAsset('KOS-LAPTOP-000001', ['procesador' => 'i5', 'ram' => '8GB', 'disco_duro' => '1TB']);

        $otraMarca = Marca::create(['nombre' => 'Dell']);
        $otroModelo = Modelo::create(['nombre' => 'Latitude 5420', 'marca_id' => $otraMarca->id]);

        Asset::create([
            'codigo' => 'KOS-LAPTOP-000002',
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'marca_id' => $otraMarca->id,
            'modelo_id' => $otroModelo->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $this->estatus->id,
            'especificaciones' => ['procesador' => 'i7', 'ram' => '16GB', 'disco_duro' => '512GB'],
        ]);

        Artisan::call('gestionti:generar-articulos-desde-historico');

        $this->assertSame(2, ArticuloSolicitud::count());
        $this->assertSame(2, Asset::whereNotNull('articulo_id')->count());
    }

    public function test_ignores_assets_without_tipo_marca_modelo_or_already_linked(): void
    {
        // Ya vinculado de antes — no debe volver a contarse ni generar un
        // segundo Artículo para su grupo.
        $articuloExistente = ArticuloSolicitud::create([
            'codigo' => 'ART-PRECREADO',
            'descripcion' => 'Preexistente',
            'unidad_medida' => 'pieza',
            'categoria' => 'laptops_desktops',
            'tipo_equipo_id' => $this->tipoEquipo->id,
            'marca_id' => $this->marca->id,
            'modelo_id' => $this->modelo->id,
            'es_inventariable' => true,
            'activo' => true,
        ]);
        $this->crearAsset('KOS-LAPTOP-000001', ['procesador' => 'i5', 'ram' => '8GB', 'disco_duro' => '1TB'], $articuloExistente->id);

        // Sin marca/modelo — fuera de alcance del backfill, se ignora.
        Asset::create([
            'codigo' => 'KOS-MONITOR-000001',
            'tipo_equipo_id' => TipoEquipo::create(['nombre' => 'Monitor'])->id,
            'origen_tipo' => 'migracion_historica',
            'estatus_id' => $this->estatus->id,
        ]);

        $exitCode = Artisan::call('gestionti:generar-articulos-desde-historico');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(1, ArticuloSolicitud::count());
        $this->assertStringContainsString('No hay Assets pendientes', Artisan::output());
    }

    public function test_running_twice_does_not_duplicate_articulos_or_break_links(): void
    {
        $this->crearAsset('KOS-LAPTOP-000001', ['procesador' => 'i5', 'ram' => '8GB', 'disco_duro' => '1TB']);
        $this->crearAsset('KOS-LAPTOP-000002', ['procesador' => 'i5', 'ram' => '8GB', 'disco_duro' => '1TB']);

        Artisan::call('gestionti:generar-articulos-desde-historico');

        $this->assertSame(1, ArticuloSolicitud::count());
        $articuloId = ArticuloSolicitud::first()->id;

        $exitCode = Artisan::call('gestionti:generar-articulos-desde-historico');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(1, ArticuloSolicitud::count());
        $this->assertSame($articuloId, ArticuloSolicitud::first()->id);
        $this->assertSame(2, Asset::where('articulo_id', $articuloId)->count());
    }
}
