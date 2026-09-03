<?php

namespace Modules\GestionTI\Providers;

use Livewire\Livewire;
use Modules\GestionTI\Console\Commands\EbsBackfillCommand;
use Modules\GestionTI\Console\Commands\EbsSincronizarAprobadasCommand;
use Modules\GestionTI\Console\Commands\EbsSincronizarCreadasCommand;
use Modules\GestionTI\Console\Commands\ImportarHistoricoCommand;
use Modules\GestionTI\Console\Commands\RevisarAvisosProgramadosCommand;
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
use Modules\GestionTI\Livewire\Inventarios\Stock;
use Modules\GestionTI\Livewire\MesaServicio\EbsRequisiciones;
use Modules\GestionTI\Livewire\MesaServicio\SolicitudesSic;
use Modules\GestionTI\Livewire\MesaServicio\Tickets;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Manage as PresupuestoProyectosManage;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Show as PresupuestoProyectosShow;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Modules\GestionTI\Support\SharePoint\SharePointClient;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class GestionTIServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'GestionTI';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'gestionti';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ImportarHistoricoCommand::class,
        RevisarAvisosProgramadosCommand::class,
        EbsSincronizarCreadasCommand::class,
        EbsSincronizarAprobadasCommand::class,
        EbsBackfillCommand::class,
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

        // Mismo criterio que Modules\MesaServicio\Providers\MesaServicioServiceProvider
        // para SdpClient: singleton construido desde config (services.ebs),
        // no desde env() directo, para que los tests puedan sobreescribirlo
        // fácilmente vía config().
        $this->app->singleton(EbsRequisitionsClient::class, fn () => new EbsRequisitionsClient(
            baseUrl: (string) config('services.ebs.base_url'),
            organizationCode: (string) config('services.ebs.organization_code'),
            username: (string) config('services.ebs.username'),
            password: (string) config('services.ebs.password'),
        ));

        // Fase 5 (SharePoint) — mismo criterio que EbsRequisitionsClient de
        // arriba: singleton construido desde config (services.sharepoint),
        // no desde env() directo, para que los tests puedan sobreescribirlo
        // fácilmente vía config().
        $this->app->singleton(SharePointClient::class, fn () => new SharePointClient(
            tenantId: (string) config('services.sharepoint.tenant_id'),
            clientId: (string) config('services.sharepoint.client_id'),
            clientSecret: (string) config('services.sharepoint.client_secret'),
            siteHostname: (string) config('services.sharepoint.site_hostname'),
            sitePath: (string) config('services.sharepoint.site_path'),
            carpetas: (array) config('services.sharepoint.carpetas', []),
            proxy: config('services.sharepoint.proxy'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        Livewire::component('gestionti.dashboard', Dashboard::class);
        Livewire::component('gestionti.busqueda-global', BusquedaGlobal::class);
        Livewire::component('gestionti.catalogos.nucleo', CatalogosNucleo::class);
        Livewire::component('gestionti.catalogos.empleados', CatalogosEmpleados::class);
        Livewire::component('gestionti.catalogos.compras', CatalogosCompras::class);
        Livewire::component('gestionti.catalogos.inventario', CatalogosInventario::class);
        Livewire::component('gestionti.tickets', Tickets::class);
        Livewire::component('gestionti.solicitudes-sic', SolicitudesSic::class);
        Livewire::component('gestionti.ebs-requisiciones', EbsRequisiciones::class);
        Livewire::component('gestionti.solicitudes-proveedor', SolicitudesProveedor::class);
        Livewire::component('gestionti.recepciones', Recepciones::class);
        Livewire::component('gestionti.facturas', Facturas::class);
        Livewire::component('gestionti.asignaciones', Asignaciones::class);
        Livewire::component('gestionti.stock', Stock::class);
        Livewire::component('gestionti.registro-manual', RegistroManual::class);
        Livewire::component('gestionti.mantenimientos', Mantenimientos::class);
        Livewire::component('gestionti.ficha-activo.buscar', FichaActivoBuscar::class);
        Livewire::component('gestionti.ficha-activo.show', FichaActivoShow::class);
        Livewire::component('gestionti.presupuesto-proyectos.manage', PresupuestoProyectosManage::class);
        Livewire::component('gestionti.presupuesto-proyectos.show', PresupuestoProyectosShow::class);
        Livewire::component('gestionti.avisos.tipos-aviso', TiposAviso::class);
        Livewire::component('gestionti.avisos.historial', AvisosHistorial::class);
        Livewire::component('gestionti.configuracion.almacenamiento-documentos', AlmacenamientoDocumentos::class);
    }

    /**
     * Define module schedules.
     *
     * `gestionti:revisar-avisos-programados` cubre los 3 disparadores de
     * `TipoAviso` que no ocurren dentro de una acción de usuario
     * (`MANTENIMIENTO_PROXIMO_VENCER`/`MANTENIMIENTO_VENCIDO`/`STOCK_BAJO_MINIMO`/
     * `PRESUPUESTO_COSTO_PENDIENTE`) — registrado aquí (hook nativo de
     * `nwidart/laravel-modules`, invocado automáticamente por
     * `ModuleServiceProvider::boot()`) en vez de en el `routes/console.php`
     * raíz del core, para mantener el módulo autocontenido. En producción
     * esto requiere el cron estándar de Laravel
     * (`* * * * * php artisan schedule:run`) — ver docs/gestionti-progreso.md.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('gestionti:revisar-avisos-programados')->daily();

        // Fase 5, punto 1 (EBS) — creadas SIEMPRE antes de aprobadas (crea
        // el registro base que aprobadas completa). Mismo horario (01:00)
        // por instrucción explícita — el scheduler de Laravel las ejecuta
        // en el orden en que se registran para el mismo tick.
        $schedule->command('gestionti:ebs-sincronizar-creadas')->dailyAt('01:00');
        $schedule->command('gestionti:ebs-sincronizar-aprobadas')->dailyAt('01:00');
    }
}
