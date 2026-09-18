<?php

namespace Modules\MesaServicio\Providers;

use Livewire\Livewire;
use Modules\MesaServicio\Console\Commands\DailyCloseCommand;
use Modules\MesaServicio\Console\Commands\MonthlyCloseCommand;
use Modules\MesaServicio\Console\Commands\SyncCatalogosCommand;
use Modules\MesaServicio\Console\Commands\SyncSitesCommand;
use Modules\MesaServicio\Console\Commands\SyncTechniciansCommand;
use Modules\MesaServicio\Console\Commands\SyncTicketsCommand;
use Modules\MesaServicio\Console\Commands\SyncTicketStatusesCommand;
use Modules\MesaServicio\Console\Commands\TestConnectionCommand;
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
use Modules\MesaServicio\Services\SdpClient;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class MesaServicioServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'MesaServicio';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'mesaservicio';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        TestConnectionCommand::class,
        SyncTicketStatusesCommand::class,
        SyncTechniciansCommand::class,
        SyncTicketsCommand::class,
        SyncSitesCommand::class,
        SyncCatalogosCommand::class,
        DailyCloseCommand::class,
        MonthlyCloseCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(SdpClient::class, fn () => new SdpClient(
            clientId: config('services.servicedesk_plus.client_id'),
            clientSecret: config('services.servicedesk_plus.client_secret'),
            refreshToken: config('services.servicedesk_plus.refresh_token'),
            portal: config('services.servicedesk_plus.portal'),
            apiDomain: config('services.servicedesk_plus.api_domain'),
            accountsDomain: config('services.servicedesk_plus.accounts_domain'),
            proxy: config('services.servicedesk_plus.proxy'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        // Sin esto, cualquier wire:click/wire:submit de estos componentes
        // da "This page has expired" — Livewire resuelve el nombre hacia la
        // clase asumiendo App\Livewire si no se registra explícito aquí.
        Livewire::component('mesaservicio.catalogos.tecnicos', Tecnicos::class);
        Livewire::component('mesaservicio.catalogos.destinatarios', Destinatarios::class);
        Livewire::component('mesaservicio.dashboard', Dashboard::class);
        Livewire::component('mesaservicio.tecnicos.show', TecnicoShow::class);
        Livewire::component('mesaservicio.reportes.index', ReportesIndex::class);
        Livewire::component('mesaservicio.catalogos.slas', Slas::class);
        Livewire::component('mesaservicio.catalogos.catalogos-sdp', CatalogosSdp::class);
        Livewire::component('mesaservicio.catalogos.grupos-analiticos', GruposAnaliticos::class);
        Livewire::component('mesaservicio.dashboards.ejecutivo', Ejecutivo::class);
        Livewire::component('mesaservicio.dashboards.analitico', Analitico::class);
        Livewire::component('mesaservicio.dashboards.operacion', Operacion::class);
    }

    /**
     * Define module schedules.
     *
     * `Nwidart\Modules\Support\ModuleServiceProvider::registerCommandSchedules()`
     * (ver clase base) ya llama a este método automáticamente desde un
     * callback `$this->app->booted(...)` si existe, resolviendo
     * `Schedule::class` del contenedor — es el MISMO singleton que usa
     * `schedule:run` (`Illuminate\Foundation\Providers\FoundationServiceProvider::registerConsoleSchedule()`
     * lo registra como singleton siempre, sin depender de `withSchedule()`
     * en bootstrap/app.php). Confirmado leyendo ambas clases del framework
     * antes de escribir esto: NO hizo falta tocar bootstrap/app.php (que
     * hoy no tiene `withSchedule(...)`, ver docs/mesaservicio-progreso.md)
     * para que cualquiera de los schedules de abajo quede activo — basta con
     * sobreescribir este método aquí, en el service provider del módulo.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // `sdp:sync-tickets` NO corrió automático entre el 2026-09-11 y el
        // 2026-09-17: en un servidor con disco limitado, un log que crece sin
        // rotar (canal "single" por defecto) llegado a un volumen de tráfico
        // o de errores suficiente puede llenar el disco entero corriendo
        // cada 5 minutos — ver docs/deploy-lemp.md §3.1 para el fix de
        // logging en general, ya aplicado (LOG_CHANNEL=daily). Con ese root
        // cause resuelto, se reactivó el 2026-09-17 con una frecuencia más
        // conservadora (`hourly()`, no el `everyFiveMinutes()` original) —
        // margen de sobra frente al tamaño de log esperado incluso sin
        // rotación, y suficiente para que el dashboard/cierres reflejen datos
        // recientes sin depender de que alguien recuerde sincronizar a mano.
        // El botón "Sincronizar ahora" del Dashboard
        // (Livewire\Dashboard::sincronizar()) sigue disponible para forzar
        // una corrida entre horas.
        $schedule->command('sdp:sync-tickets')->hourly();

        // `sdp:sync-sites` (Fase 8) es deliberadamente MANUAL-ONLY, no se
        // agrega aquí — los sitios geográficos cambian con muy poca
        // frecuencia, no justifican un schedule automático. Se corre a mano
        // por consola cuando haga falta refrescar el catálogo.

        // `sdp:sync-catalogos` (Fase 8, Parte 2) — mismo criterio MANUAL-ONLY
        // que sdp:sync-sites: los 12 catálogos de configuración (categorías,
        // prioridades, etc.) cambian con muy poca frecuencia. Se corre a
        // mano por consola o desde el botón "Sincronizar catálogos" de
        // /mesa-servicio/catalogos-sdp.

        // Cierre diario (Fase 4) — procesa "ayer" por defecto (ver
        // DailyCloseCommand). 00:05 le da margen a la última corrida de
        // sdp:sync-tickets de la noche para terminar de reflejar el día
        // completo antes de cortar el cierre.
        $schedule->command('sdp:daily-close')->dailyAt('00:05');

        // Cierre mensual (Fase 5) — procesa "el mes pasado completo" por
        // defecto (ver MonthlyCloseCommand). Corre el día 1 de cada mes a
        // las 00:15, con margen sobre el cierre diario de las 00:05 (que ya
        // cubre el último día del mes anterior).
        $schedule->command('sdp:monthly-close')->monthlyOn(1, '00:15');
    }
}
