<?php

use Illuminate\Support\Facades\Route;
use Modules\GestionTI\Http\Controllers\Ayuda\AyudaPdfController;
use Modules\GestionTI\Http\Controllers\Catalogos\ComprasExportController;
use Modules\GestionTI\Http\Controllers\Catalogos\EmpleadosExportController;
use Modules\GestionTI\Http\Controllers\Catalogos\InventarioExportController;
use Modules\GestionTI\Http\Controllers\Catalogos\NucleoExportController;
use Modules\GestionTI\Http\Controllers\PresupuestoProyectos\ExportController as PresupuestoProyectosExportController;
use Modules\GestionTI\Livewire\Avisos\Historial as AvisosHistorial;
use Modules\GestionTI\Livewire\Avisos\TiposAviso;
use Modules\GestionTI\Livewire\BusquedaGlobal;
use Modules\GestionTI\Livewire\Catalogos\Compras as CatalogosCompras;
use Modules\GestionTI\Livewire\Catalogos\Empleados as CatalogosEmpleados;
use Modules\GestionTI\Livewire\Catalogos\Inventario as CatalogosInventario;
use Modules\GestionTI\Livewire\Catalogos\Nucleo as CatalogosNucleo;
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
use Modules\GestionTI\Livewire\Inventarios\Stock as StockScreen;
use Modules\GestionTI\Livewire\MesaServicio\EbsRequisiciones;
use Modules\GestionTI\Livewire\MesaServicio\SolicitudesSic;
use Modules\GestionTI\Livewire\MesaServicio\Tickets;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Manage as PresupuestoProyectosManage;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Show as PresupuestoProyectosShow;

// Las rutas de cada pantalla se agregan aquí conforme se construyen
// (ver docs/agregar-pantallas.md). Cada grupo va protegido por su
// propio permiso `screens.<slug>.<verbo>`.

// PDF de ayuda de una pantalla (ver Modules\GestionTI\Support\Ayuda\AyudaCatalog)
// — solo `auth`, no un permiso de pantalla específico: es contenido
// instructivo genérico, no datos de negocio.
Route::middleware(['auth'])->group(function () {
    Route::get('/gestionti/ayuda/{slug}/pdf', AyudaPdfController::class)
        ->name('gestionti.ayuda.pdf')
        ->where('slug', '[a-z0-9-]+');
});

// Ruta deliberadamente NO "/dashboard" — esa ya la usa el core
// (App\Livewire\Dashboard, ruta nombrada "dashboard") para el dashboard
// genérico de la plantilla. Este es el dashboard propio del módulo.
Route::middleware(['auth', 'permission:screens.gestionti-dashboard.manage'])->group(function () {
    Route::get('/gestionti-dashboard', Dashboard::class)->name('gestionti.dashboard.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-busqueda-global.manage'])->group(function () {
    Route::get('/busqueda-global', BusquedaGlobal::class)->name('gestionti.busqueda-global.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-catalogos-nucleo.manage'])->group(function () {
    Route::get('/catalogos/nucleo', CatalogosNucleo::class)->name('gestionti.catalogos.nucleo');
    Route::get('/catalogos/nucleo/exportar', NucleoExportController::class)->name('gestionti.catalogos.nucleo.export');
});

Route::middleware(['auth', 'permission:screens.gestionti-catalogos-empleados.manage'])->group(function () {
    Route::get('/catalogos/empleados', CatalogosEmpleados::class)->name('gestionti.catalogos.empleados');
    Route::get('/catalogos/empleados/exportar', EmpleadosExportController::class)->name('gestionti.catalogos.empleados.export');
});

Route::middleware(['auth', 'permission:screens.gestionti-catalogos-compras.manage'])->group(function () {
    Route::get('/catalogos/compras', CatalogosCompras::class)->name('gestionti.catalogos.compras');
    Route::get('/catalogos/compras/exportar', ComprasExportController::class)->name('gestionti.catalogos.compras.export');
});

Route::middleware(['auth', 'permission:screens.gestionti-catalogos-inventario.manage'])->group(function () {
    Route::get('/catalogos/inventario', CatalogosInventario::class)->name('gestionti.catalogos.inventario');
    Route::get('/catalogos/inventario/exportar', InventarioExportController::class)->name('gestionti.catalogos.inventario.export');
});

Route::middleware(['auth', 'permission:screens.gestionti-tickets.manage'])->group(function () {
    Route::get('/tickets', Tickets::class)->name('gestionti.tickets.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-solicitudes-sic.manage'])->group(function () {
    Route::get('/solicitudes-sic', SolicitudesSic::class)->name('gestionti.solicitudes-sic.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-ebs-requisiciones.manage'])->group(function () {
    Route::get('/ebs-requisiciones', EbsRequisiciones::class)->name('gestionti.ebs-requisiciones.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-solicitudes-proveedor.manage'])->group(function () {
    Route::get('/solicitudes-proveedor', SolicitudesProveedor::class)->name('gestionti.solicitudes-proveedor.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-recepciones.manage'])->group(function () {
    Route::get('/recepciones', Recepciones::class)->name('gestionti.recepciones.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-facturas.manage'])->group(function () {
    Route::get('/facturas', Facturas::class)->name('gestionti.facturas.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-asignaciones.manage'])->group(function () {
    Route::get('/asignaciones', Asignaciones::class)->name('gestionti.asignaciones.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-stock.manage'])->group(function () {
    Route::get('/stock', StockScreen::class)->name('gestionti.stock.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-registro-manual.manage'])->group(function () {
    Route::get('/registro-manual', RegistroManual::class)->name('gestionti.registro-manual.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-mantenimientos.manage'])->group(function () {
    Route::get('/mantenimientos', Mantenimientos::class)->name('gestionti.mantenimientos.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-ficha-activo.manage'])->group(function () {
    Route::get('/ficha-activo', FichaActivoBuscar::class)->name('gestionti.ficha-activo.index');
    Route::get('/ficha-activo/{asset}', FichaActivoShow::class)->name('gestionti.ficha-activo.show');
});

Route::middleware(['auth', 'permission:screens.gestionti-presupuestos-proyecto.manage'])->group(function () {
    Route::get('/presupuestos-proyecto', PresupuestoProyectosManage::class)->name('gestionti.presupuestos-proyecto.index');
    Route::get('/presupuestos-proyecto/{proyectoPresupuesto}', PresupuestoProyectosShow::class)->name('gestionti.presupuestos-proyecto.show');
    Route::get('/presupuestos-proyecto/{proyectoPresupuesto}/exportar', PresupuestoProyectosExportController::class)->name('gestionti.presupuestos-proyecto.export');
});

Route::middleware(['auth', 'permission:screens.gestionti-tipos-aviso.manage'])->group(function () {
    Route::get('/tipos-aviso', TiposAviso::class)->name('gestionti.tipos-aviso.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-avisos-historial.manage'])->group(function () {
    Route::get('/avisos-historial', AvisosHistorial::class)->name('gestionti.avisos-historial.index');
});

Route::middleware(['auth', 'permission:screens.gestionti-almacenamiento-documentos.manage'])->group(function () {
    Route::get('/almacenamiento-documentos', AlmacenamientoDocumentos::class)->name('gestionti.almacenamiento-documentos.index');
});
