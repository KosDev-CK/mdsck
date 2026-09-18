<?php

use Illuminate\Support\Facades\Route;
use Modules\MesaServicio\Http\Controllers\Ayuda\AyudaPdfController;
use Modules\MesaServicio\Livewire\Catalogos\CatalogosSdp;
use Modules\MesaServicio\Livewire\Catalogos\Destinatarios;
use Modules\MesaServicio\Livewire\Catalogos\GruposAnaliticos;
use Modules\MesaServicio\Livewire\Catalogos\Slas;
use Modules\MesaServicio\Livewire\Catalogos\Tecnicos;
use Modules\MesaServicio\Livewire\Dashboard;
use Modules\MesaServicio\Livewire\Dashboards\Analitico;
use Modules\MesaServicio\Livewire\Dashboards\Ejecutivo;
use Modules\MesaServicio\Livewire\Dashboards\Operacion;
use Modules\MesaServicio\Livewire\Reportes\Index as ReportesIndex;
use Modules\MesaServicio\Livewire\Tecnicos\Show as TecnicoShow;

// Las rutas de cada pantalla se agregan aquí conforme se construyen
// (ver docs/agregar-pantallas.md). Cada grupo va protegido por su
// propio permiso `screens.<slug>.<verbo>`.

// PDF de ayuda de una pantalla (ver Modules\MesaServicio\Support\Ayuda\AyudaCatalog)
// — solo `auth`, no un permiso de pantalla específico: es contenido
// instructivo genérico, no datos de negocio.
Route::middleware(['auth'])->group(function () {
    Route::get('/mesa-servicio/ayuda/{slug}/pdf', AyudaPdfController::class)
        ->name('mesaservicio.ayuda.pdf')
        ->where('slug', '[a-z0-9-]+');
});

Route::middleware(['auth', 'permission:screens.mesaservicio-tecnicos.manage'])
    ->get('/mesa-servicio/tecnicos', Tecnicos::class)
    ->name('mesaservicio.tecnicos.index');

Route::middleware(['auth', 'permission:screens.mesaservicio-destinatarios.manage'])
    ->get('/mesa-servicio/destinatarios', Destinatarios::class)
    ->name('mesaservicio.destinatarios.index');

Route::middleware(['auth', 'permission:screens.mesaservicio-reportes.manage'])
    ->get('/mesa-servicio/reportes', ReportesIndex::class)
    ->name('mesaservicio.reportes.index');

Route::middleware(['auth', 'permission:screens.mesaservicio-slas.manage'])
    ->get('/mesa-servicio/slas', Slas::class)
    ->name('mesaservicio.slas.index');

Route::middleware(['auth', 'permission:screens.mesaservicio-catalogos-sdp.manage'])
    ->get('/mesa-servicio/catalogos-sdp', CatalogosSdp::class)
    ->name('mesaservicio.catalogos-sdp.index');

Route::middleware(['auth', 'permission:screens.mesaservicio-grupos-analiticos.manage'])
    ->get('/mesa-servicio/grupos-analiticos', GruposAnaliticos::class)
    ->name('mesaservicio.grupos-analiticos.index');

// Dashboard y ficha de técnico comparten el mismo permiso (Fase 2) — la
// ficha es un detalle del dashboard (se llega a ella desde ahí), no una
// pantalla de catálogo, así que NO reutiliza el permiso de
// mesaservicio-tecnicos (ese es de administración del catálogo). Ver
// "Ajustes de criterio" en docs/mesaservicio-progreso.md.
Route::middleware(['auth', 'permission:screens.mesaservicio-dashboard.manage'])->group(function () {
    Route::get('/mesa-servicio', Dashboard::class)->name('mesaservicio.dashboard.index');
    Route::get('/mesa-servicio/tecnicos/{tecnico}', TecnicoShow::class)->name('mesaservicio.tecnicos.show');
});

// Los 3 dashboards de abajo son pantallas nuevas e independientes del
// dashboard operativo de arriba (que se queda tal cual) — viven en su
// propio submenú "Dashboards" del sidebar (ver group_label en
// MesaServicioDatabaseSeeder). Por ahora son solo scaffolding con vista
// placeholder; el contenido real de cada uno se construye en iteraciones
// futuras, pantalla por pantalla.
Route::middleware(['auth', 'permission:screens.mesaservicio-dashboard-ejecutivo.manage'])
    ->get('/mesa-servicio/dashboards/ejecutivo', Ejecutivo::class)
    ->name('mesaservicio.dashboards.ejecutivo');

Route::middleware(['auth', 'permission:screens.mesaservicio-dashboard-analitico.manage'])
    ->get('/mesa-servicio/dashboards/analitico', Analitico::class)
    ->name('mesaservicio.dashboards.analitico');

Route::middleware(['auth', 'permission:screens.mesaservicio-dashboard-operacion.manage'])
    ->get('/mesa-servicio/dashboards/operacion', Operacion::class)
    ->name('mesaservicio.dashboards.operacion');
