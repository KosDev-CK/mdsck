<?php

namespace Modules\MesaServicio\Providers;

use Livewire\Livewire;
use Modules\MesaServicio\Console\Commands\DailyCloseCommand;
use Modules\MesaServicio\Console\Commands\MonthlyCloseCommand;
use Modules\MesaServicio\Console\Commands\SyncTechniciansCommand;
use Modules\MesaServicio\Console\Commands\SyncTicketsCommand;
use Modules\MesaServicio\Console\Commands\SyncTicketStatusesCommand;
use Modules\MesaServicio\Console\Commands\TestConnectionCommand;
use Modules\MesaServicio\Livewire\Catalogos\Destinatarios;
use Modules\MesaServicio\Livewire\Catalogos\Tecnicos;
use Modules\MesaServicio\Livewire\Dashboard;
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
     * para que este `everyFiveMinutes()` quede activo — basta con
     * sobreescribir este método aquí, en el service provider del módulo.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('sdp:sync-tickets')->everyFiveMinutes();

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
