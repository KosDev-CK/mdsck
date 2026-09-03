<?php

namespace Modules\GestionTI\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\ComponentRegistry;
use Modules\GestionTI\Livewire\Avisos\Historial as AvisosHistorial;
use Modules\GestionTI\Livewire\Avisos\TiposAviso;
use Modules\GestionTI\Livewire\BusquedaGlobal;
use Modules\GestionTI\Livewire\Catalogos\Compras;
use Modules\GestionTI\Livewire\Catalogos\Empleados;
use Modules\GestionTI\Livewire\Catalogos\Inventario;
use Modules\GestionTI\Livewire\Catalogos\Nucleo;
use Modules\GestionTI\Livewire\Configuracion\AlmacenamientoDocumentos;
use Modules\GestionTI\Livewire\Compras\Facturas;
use Modules\GestionTI\Livewire\Compras\Recepciones;
use Modules\GestionTI\Livewire\Compras\SolicitudesProveedor;
use Modules\GestionTI\Livewire\Dashboard;
use Modules\GestionTI\Livewire\Inventarios\Asignaciones;
use Modules\GestionTI\Livewire\Inventarios\FichaActivo\Buscar as FichaActivoBuscar;
use Modules\GestionTI\Livewire\Inventarios\FichaActivo\Show as FichaActivoShow;
use Modules\GestionTI\Livewire\Inventarios\Mantenimientos;
use Modules\GestionTI\Livewire\Inventarios\RegistroManual;
use Modules\GestionTI\Livewire\Inventarios\Stock;
use Modules\GestionTI\Livewire\MesaServicio\EbsRequisiciones;
use Modules\GestionTI\Livewire\MesaServicio\SolicitudesSic;
use Modules\GestionTI\Livewire\MesaServicio\Tickets;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Manage as PresupuestoProyectosManage;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Show as PresupuestoProyectosShow;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ver Modules/FormBuilder/tests/Feature/ComponentRegistrationTest.php para el
 * porqué de este test: Livewire::test() no detecta el bug de resolución de
 * componentes fuera de App\Livewire, así que este test ejercita el registro
 * directamente en vez de confiar en Livewire::test().
 */
class ComponentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public static function componentProvider(): array
    {
        return [
            ['gestionti.dashboard', Dashboard::class],
            ['gestionti.busqueda-global', BusquedaGlobal::class],
            ['gestionti.catalogos.nucleo', Nucleo::class],
            ['gestionti.catalogos.empleados', Empleados::class],
            ['gestionti.catalogos.compras', Compras::class],
            ['gestionti.catalogos.inventario', Inventario::class],
            ['gestionti.tickets', Tickets::class],
            ['gestionti.solicitudes-sic', SolicitudesSic::class],
            ['gestionti.ebs-requisiciones', EbsRequisiciones::class],
            ['gestionti.solicitudes-proveedor', SolicitudesProveedor::class],
            ['gestionti.recepciones', Recepciones::class],
            ['gestionti.facturas', Facturas::class],
            ['gestionti.asignaciones', Asignaciones::class],
            ['gestionti.stock', Stock::class],
            ['gestionti.registro-manual', RegistroManual::class],
            ['gestionti.mantenimientos', Mantenimientos::class],
            ['gestionti.ficha-activo.buscar', FichaActivoBuscar::class],
            ['gestionti.ficha-activo.show', FichaActivoShow::class],
            ['gestionti.presupuesto-proyectos.manage', PresupuestoProyectosManage::class],
            ['gestionti.presupuesto-proyectos.show', PresupuestoProyectosShow::class],
            ['gestionti.avisos.tipos-aviso', TiposAviso::class],
            ['gestionti.avisos.historial', AvisosHistorial::class],
            ['gestionti.configuracion.almacenamiento-documentos', AlmacenamientoDocumentos::class],
        ];
    }

    #[DataProvider('componentProvider')]
    public function test_component_name_resolves_back_to_its_class(string $name, string $expectedClass): void
    {
        $resolved = app(ComponentRegistry::class)->getClass($name);

        $this->assertSame($expectedClass, $resolved);
    }
}
