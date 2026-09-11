<?php

use Illuminate\Support\Facades\Route;
use Modules\MesaServicio\Livewire\Catalogos\Destinatarios;
use Modules\MesaServicio\Livewire\Catalogos\Tecnicos;
use Modules\MesaServicio\Livewire\Dashboard;
use Modules\MesaServicio\Livewire\Reportes\Index as ReportesIndex;
use Modules\MesaServicio\Livewire\Tecnicos\Show as TecnicoShow;

// Las rutas de cada pantalla se agregan aquí conforme se construyen
// (ver docs/agregar-pantallas.md). Cada grupo va protegido por su
// propio permiso `screens.<slug>.<verbo>`.

Route::middleware(['auth', 'permission:screens.mesaservicio-tecnicos.manage'])
    ->get('/mesa-servicio/tecnicos', Tecnicos::class)
    ->name('mesaservicio.tecnicos.index');

Route::middleware(['auth', 'permission:screens.mesaservicio-destinatarios.manage'])
    ->get('/mesa-servicio/destinatarios', Destinatarios::class)
    ->name('mesaservicio.destinatarios.index');

Route::middleware(['auth', 'permission:screens.mesaservicio-reportes.manage'])
    ->get('/mesa-servicio/reportes', ReportesIndex::class)
    ->name('mesaservicio.reportes.index');

// Dashboard y ficha de técnico comparten el mismo permiso (Fase 2) — la
// ficha es un detalle del dashboard (se llega a ella desde ahí), no una
// pantalla de catálogo, así que NO reutiliza el permiso de
// mesaservicio-tecnicos (ese es de administración del catálogo). Ver
// "Ajustes de criterio" en docs/mesaservicio-progreso.md.
Route::middleware(['auth', 'permission:screens.mesaservicio-dashboard.manage'])->group(function () {
    Route::get('/mesa-servicio', Dashboard::class)->name('mesaservicio.dashboard.index');
    Route::get('/mesa-servicio/tecnicos/{tecnico}', TecnicoShow::class)->name('mesaservicio.tecnicos.show');
});
