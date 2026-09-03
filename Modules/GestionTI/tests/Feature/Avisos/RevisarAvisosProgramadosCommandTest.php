<?php

namespace Modules\GestionTI\Tests\Feature\Avisos;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Notifications\AvisoNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RevisarAvisosProgramadosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function seedTiposAviso(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);
    }

    private function tipoEquipo(string $nombre = 'Laptop'): TipoEquipo
    {
        return TipoEquipo::firstOrCreate(['nombre' => $nombre]);
    }

    private function asset(?TipoEquipo $tipo = null, ?Ubicacion $ubicacion = null): Asset
    {
        $tipo ??= $this->tipoEquipo();
        $estatus = EstatusActivo::firstOrCreate(['codigo' => 'en_stock'], ['nombre' => 'En stock']);

        return Asset::create([
            'codigo' => 'KOS-LAPTOP-'.str_pad((string) (Asset::count() + 1), 6, '0', STR_PAD_LEFT),
            'tipo_equipo_id' => $tipo->id,
            'origen_tipo' => 'alta_manual',
            'estatus_id' => $estatus->id,
            'ubicacion_actual_id' => $ubicacion?->id,
        ]);
    }

    private function almacenTiUser(): User
    {
        $role = Role::findOrCreate('Almacén/TI', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function empleado(string $nombre, ?string $correo = null): Empleado
    {
        return Empleado::create([
            'numero_empleado' => 'EMP-'.(Empleado::count() + 1),
            'nombre' => $nombre,
            'correo' => $correo,
            'activo' => true,
        ]);
    }

    public function test_mantenimiento_proximo_a_vencer_dispatches_aviso_and_dedupes_across_runs(): void
    {
        $this->seedTiposAviso();
        Notification::fake();
        $user = $this->almacenTiUser();

        $asset = $this->asset();
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(3)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();
        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertSentToTimes($user, AvisoNotification::class, 1);
    }

    public function test_mantenimiento_fuera_de_rango_no_dispatches(): void
    {
        $this->seedTiposAviso();
        Notification::fake();
        $this->almacenTiUser();

        $asset = $this->asset();
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(10)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_mantenimiento_realizado_no_dispatches_even_within_range(): void
    {
        $this->seedTiposAviso();
        Notification::fake();
        $this->almacenTiUser();

        $asset = $this->asset();
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->addDays(2)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_REALIZADO,
        ]);

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_mantenimiento_vencido_dispatches_aviso_and_dedupes_across_runs(): void
    {
        $this->seedTiposAviso();
        Notification::fake();
        $user = $this->almacenTiUser();

        $asset = $this->asset();
        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_CORRECTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => today()->subDays(2)->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_REPROGRAMADO,
        ]);

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();
        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertSentToTimes($user, AvisoNotification::class, 1);
    }

    public function test_stock_bajo_minimo_dispatches_aviso_and_dedupes_within_the_same_day_but_not_the_next(): void
    {
        $this->seedTiposAviso();
        Notification::fake();
        $user = $this->almacenTiUser();

        $ubicacion = Ubicacion::create(['nombre' => 'CDMX']);
        $tipo = $this->tipoEquipo();
        StockMinimo::create([
            'tipo_equipo_id' => $tipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 5,
            'activo' => true,
        ]);
        // Sin ningún Asset en_stock para ese tipo/ubicación -> stock_actual=0 < 5.

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();
        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertSentToTimes($user, AvisoNotification::class, 1);

        Carbon::setTestNow(now()->addDay());

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertSentToTimes($user, AvisoNotification::class, 2);
    }

    public function test_stock_above_minimum_does_not_dispatch(): void
    {
        $this->seedTiposAviso();
        Notification::fake();
        $this->almacenTiUser();

        $ubicacion = Ubicacion::create(['nombre' => 'CDMX']);
        $tipo = $this->tipoEquipo();
        StockMinimo::create([
            'tipo_equipo_id' => $tipo->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 1,
            'activo' => true,
        ]);
        $this->asset($tipo, $ubicacion);

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_presupuesto_costo_pendiente_dispatches_when_fecha_limite_is_close_and_dedupes_daily(): void
    {
        $this->seedTiposAviso();
        Notification::fake();

        $empresa = \Modules\GestionTI\Models\Empresa::create(['razon_social' => 'Kosmos S.A.', 'nombre_comercial' => 'Kosmos']);
        $centroCosto = \Modules\GestionTI\Models\CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $area = \Modules\GestionTI\Models\Area::create(['nombre' => 'Operaciones']);
        $pm = $this->empleado('PM');
        $responsable = $this->empleado('Responsable', 'responsable-presupuesto@example.com');
        $responsableUser = User::factory()->create(['email' => 'responsable-presupuesto@example.com']);

        $proyecto = ProyectoPresupuesto::create([
            'nombre_proyecto' => 'Proyecto con costo pendiente',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => $centroCosto->id,
            'direccion_centro' => 'Av. Siempre Viva 123',
            'area_operativa_solicitante_id' => $area->id,
            'pm_responsable_id' => $pm->id,
            'fecha_solicitud' => today()->format('Y-m-d'),
            'fecha_limite_captura' => today()->addDays(2)->format('Y-m-d'),
            'estatus' => ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS,
        ]);

        ProyectoPresupuestoArticulo::create([
            'proyecto_id' => $proyecto->id,
            'categoria' => 'laptops_desktops',
            'descripcion' => 'Laptop pendiente',
            'cantidad' => 1,
            'responsable_costo_id' => $responsable->id,
            'estatus_captura' => ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_PENDIENTE,
        ]);

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();
        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertSentToTimes($responsableUser, AvisoNotification::class, 1);

        Carbon::setTestNow(now()->addDay());

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertSentToTimes($responsableUser, AvisoNotification::class, 2);
    }

    public function test_presupuesto_costo_pendiente_not_dispatched_when_fecha_limite_is_far_in_the_future(): void
    {
        $this->seedTiposAviso();
        Notification::fake();

        $empresa = \Modules\GestionTI\Models\Empresa::create(['razon_social' => 'Kosmos S.A.', 'nombre_comercial' => 'Kosmos']);
        $centroCosto = \Modules\GestionTI\Models\CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $area = \Modules\GestionTI\Models\Area::create(['nombre' => 'Operaciones']);
        $pm = $this->empleado('PM');
        $responsable = $this->empleado('Responsable', 'responsable-lejano@example.com');
        User::factory()->create(['email' => 'responsable-lejano@example.com']);

        $proyecto = ProyectoPresupuesto::create([
            'nombre_proyecto' => 'Proyecto lejano',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => $centroCosto->id,
            'direccion_centro' => 'Av. Siempre Viva 123',
            'area_operativa_solicitante_id' => $area->id,
            'pm_responsable_id' => $pm->id,
            'fecha_solicitud' => today()->format('Y-m-d'),
            'fecha_limite_captura' => today()->addDays(30)->format('Y-m-d'),
            'estatus' => ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS,
        ]);

        ProyectoPresupuestoArticulo::create([
            'proyecto_id' => $proyecto->id,
            'categoria' => 'laptops_desktops',
            'descripcion' => 'Laptop lejana',
            'cantidad' => 1,
            'responsable_costo_id' => $responsable->id,
            'estatus_captura' => ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_PENDIENTE,
        ]);

        $this->artisan('gestionti:revisar-avisos-programados')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
