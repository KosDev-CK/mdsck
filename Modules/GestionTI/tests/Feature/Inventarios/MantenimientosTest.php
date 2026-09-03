<?php

namespace Modules\GestionTI\Tests\Feature\Inventarios;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Inventarios\Mantenimientos;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\DocumentoDigitalizado;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\PeriodicidadMantenimiento;
use Modules\GestionTI\Models\Proveedor;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Validador;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MantenimientosTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Mantenimiento',
            'slug' => 'gestionti-mantenimientos',
            'route_name' => 'gestionti.mantenimientos.index',
            'permission_name' => 'screens.gestionti-mantenimientos.manage',
            'icon' => 'wrench-screwdriver',
            'order' => 34,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function tipoEquipo(string $nombre = 'Laptop'): TipoEquipo
    {
        return TipoEquipo::firstOrCreate(['nombre' => $nombre]);
    }

    private function asset(?TipoEquipo $tipo = null): Asset
    {
        $tipo ??= $this->tipoEquipo();
        $estatus = EstatusActivo::firstOrCreate(['codigo' => 'en_stock'], ['nombre' => 'En stock']);

        return Asset::create([
            'codigo' => 'KOS-LAPTOP-'.str_pad((string) (Asset::count() + 1), 6, '0', STR_PAD_LEFT),
            'tipo_equipo_id' => $tipo->id,
            'origen_tipo' => 'alta_manual',
            'estatus_id' => $estatus->id,
        ]);
    }

    private function ticket(): Ticket
    {
        $empleado = Empleado::create(['numero_empleado' => 'EMP-'.(Empleado::count() + 1), 'nombre' => 'Solicitante']);

        return Ticket::create(['fecha' => now()->format('Y-m-d'), 'empleado_id' => $empleado->id]);
    }

    private function proveedor(string $nombre = 'Proveedor de Mantenimiento'): Proveedor
    {
        return Proveedor::create(['razon_social' => $nombre, 'nombre_comercial' => $nombre]);
    }

    private function validador(string $nombre = 'Ana Torres'): Validador
    {
        return Validador::create(['nombre' => $nombre]);
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mantenimientos')->assertForbidden();
    }

    public function test_preventivo_suggests_fecha_programada_from_active_periodicidad(): void
    {
        $this->actingAs($this->actingUser());
        $tipo = $this->tipoEquipo();
        PeriodicidadMantenimiento::create(['tipo_equipo_id' => $tipo->id, 'meses_sugeridos' => 6, 'activo' => true]);
        $asset = $this->asset($tipo);

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.tipo', Mantenimiento::TIPO_PREVENTIVO)
            ->set('form.asset_id', $asset->id)
            ->assertSet('form.fecha_programada', now()->addMonths(6)->format('Y-m-d'));
    }

    public function test_preventivo_leaves_fecha_programada_empty_without_active_periodicidad(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.tipo', Mantenimiento::TIPO_PREVENTIVO)
            ->set('form.asset_id', $asset->id)
            ->assertSet('form.fecha_programada', null)
            ->assertHasNoErrors();
    }

    public function test_correctivo_can_be_created_with_and_without_ticket(): void
    {
        $this->actingAs($this->actingUser());
        $asset1 = $this->asset();
        $asset2 = $this->asset();
        $ticket = $this->ticket();
        $validador = $this->validador();

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.asset_id', $asset1->id)
            ->set('form.tipo', Mantenimiento::TIPO_CORRECTIVO)
            ->set('form.ticket_id', $ticket->id)
            ->set('form.origen_ejecucion', Mantenimiento::ORIGEN_INTERNO)
            ->call('save')
            ->assertHasNoErrors();

        $conTicket = Mantenimiento::where('asset_id', $asset1->id)->firstOrFail();
        $this->assertSame($ticket->id, $conTicket->ticket_id);

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.asset_id', $asset2->id)
            ->set('form.tipo', Mantenimiento::TIPO_CORRECTIVO)
            ->set('form.origen_ejecucion', Mantenimiento::ORIGEN_INTERNO)
            ->call('save')
            ->assertHasNoErrors();

        $sinTicket = Mantenimiento::where('asset_id', $asset2->id)->firstOrFail();
        $this->assertNull($sinTicket->ticket_id);
        // Validador creado arriba solo para mantener el escenario realista
        // (no se usa directamente en esta prueba, ver las pruebas de origen).
        $this->assertNotNull($validador);
    }

    public function test_origen_externo_requires_vendor_id(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.asset_id', $asset->id)
            ->set('form.tipo', Mantenimiento::TIPO_CORRECTIVO)
            ->set('form.origen_ejecucion', Mantenimiento::ORIGEN_EXTERNO)
            ->call('save')
            ->assertHasErrors(['form.vendor_id']);

        $this->assertSame(0, Mantenimiento::count());

        $vendor = $this->proveedor();

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.asset_id', $asset->id)
            ->set('form.tipo', Mantenimiento::TIPO_CORRECTIVO)
            ->set('form.origen_ejecucion', Mantenimiento::ORIGEN_EXTERNO)
            ->set('form.vendor_id', $vendor->id)
            ->call('save')
            ->assertHasNoErrors();

        $record = Mantenimiento::firstOrFail();
        $this->assertSame($vendor->id, $record->vendor_id);
    }

    public function test_origen_interno_does_not_require_vendor_id_and_ignores_it_if_sent(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();
        $vendor = $this->proveedor();

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.asset_id', $asset->id)
            ->set('form.tipo', Mantenimiento::TIPO_CORRECTIVO)
            ->set('form.origen_ejecucion', Mantenimiento::ORIGEN_INTERNO)
            ->set('form.vendor_id', $vendor->id)
            ->call('save')
            ->assertHasNoErrors();

        $record = Mantenimiento::firstOrFail();
        $this->assertNull($record->vendor_id);
    }

    public function test_happy_path_cycle_programado_iniciar_en_proceso_completar_realizado_interno(): void
    {
        Storage::fake('public');
        $this->actingAs($this->actingUser());
        $asset = $this->asset();
        $validador = $this->validador();

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.asset_id', $asset->id)
            ->set('form.tipo', Mantenimiento::TIPO_CORRECTIVO)
            ->set('form.origen_ejecucion', Mantenimiento::ORIGEN_INTERNO)
            ->call('save')
            ->assertHasNoErrors();

        $record = Mantenimiento::firstOrFail();
        $this->assertSame(Mantenimiento::ESTATUS_PROGRAMADO, $record->estatus);

        $component = Livewire::test(Mantenimientos::class)
            ->call('iniciar', $record->id);

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_EN_PROCESO, $record->estatus);

        $file = UploadedFile::fake()->create('orden-servicio.pdf', 100, 'application/pdf');

        $component
            ->call('openCompletar', $record->id)
            ->set('completarForm.fecha_realizada', now()->format('Y-m-d'))
            ->set('completarForm.diagnostico', 'Se reemplazó la batería.')
            ->set('completarForm.realizado_por_id', $validador->id)
            ->set('completarAdjunto', $file)
            ->call('confirmCompletar')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_REALIZADO, $record->estatus);
        $this->assertSame('Se reemplazó la batería.', $record->diagnostico);
        $this->assertSame($validador->id, $record->realizado_por_id);
        $this->assertNull($record->costo);
        $this->assertNotNull($record->documento_id);

        $documento = DocumentoDigitalizado::findOrFail($record->documento_id);
        $this->assertSame('orden_servicio', $documento->tipo_documento);
        Storage::disk('public')->assertExists($documento->referencia);
    }

    public function test_happy_path_cycle_completar_externo_requires_costo(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();
        $vendor = $this->proveedor();

        Livewire::test(Mantenimientos::class)
            ->call('create')
            ->set('form.asset_id', $asset->id)
            ->set('form.tipo', Mantenimiento::TIPO_CORRECTIVO)
            ->set('form.origen_ejecucion', Mantenimiento::ORIGEN_EXTERNO)
            ->set('form.vendor_id', $vendor->id)
            ->call('save')
            ->assertHasNoErrors();

        $record = Mantenimiento::firstOrFail();

        $component = Livewire::test(Mantenimientos::class)->call('iniciar', $record->id);

        $component
            ->call('openCompletar', $record->id)
            ->set('completarForm.fecha_realizada', now()->format('Y-m-d'))
            ->set('completarForm.diagnostico', 'Falla de fuente de poder, reparada en sitio.')
            ->call('confirmCompletar')
            ->assertHasErrors(['completarForm.costo']);

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_EN_PROCESO, $record->estatus);

        $component
            ->set('completarForm.costo', 850.50)
            ->call('confirmCompletar')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_REALIZADO, $record->estatus);
        $this->assertEquals(850.50, $record->costo);
        $this->assertNull($record->realizado_por_id);
    }

    public function test_reprogramar_only_works_from_programado_or_reprogramado(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();

        $record = Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => now()->format('Y-m-d'),
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        $nuevaFecha = now()->addDays(10)->format('Y-m-d');

        Livewire::test(Mantenimientos::class)
            ->call('openReprogramar', $record->id)
            ->set('reprogramarForm.fecha_programada', $nuevaFecha)
            ->set('reprogramarForm.motivo', 'El proveedor no tenía refacción.')
            ->call('confirmReprogramar')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_REPROGRAMADO, $record->estatus);
        $this->assertSame($nuevaFecha, $record->fecha_programada->format('Y-m-d'));

        // Reprogramar de nuevo, desde `reprogramado`, debe seguir funcionando.
        $otraFecha = now()->addDays(20)->format('Y-m-d');

        Livewire::test(Mantenimientos::class)
            ->call('openReprogramar', $record->id)
            ->set('reprogramarForm.fecha_programada', $otraFecha)
            ->call('confirmReprogramar')
            ->assertHasNoErrors();

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_REPROGRAMADO, $record->estatus);
        $this->assertSame($otraFecha, $record->fecha_programada->format('Y-m-d'));

        // Marcarlo `realizado` a mano y confirmar que ya no se puede
        // reprogramar directamente, aunque se invoque el método sin pasar
        // por la vista.
        $record->update(['estatus' => Mantenimiento::ESTATUS_REALIZADO]);

        Livewire::test(Mantenimientos::class)
            ->set('reprogramandoId', $record->id)
            ->set('reprogramarForm.fecha_programada', now()->addDays(30)->format('Y-m-d'))
            ->call('confirmReprogramar');

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_REALIZADO, $record->estatus);
    }

    public function test_reprogramar_requires_a_date_different_from_the_current_one(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();
        $fecha = now()->format('Y-m-d');

        $record = Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'fecha_programada' => $fecha,
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        Livewire::test(Mantenimientos::class)
            ->call('openReprogramar', $record->id)
            ->set('reprogramarForm.fecha_programada', $fecha)
            ->call('confirmReprogramar')
            ->assertHasErrors(['reprogramarForm.fecha_programada']);

        $record->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_PROGRAMADO, $record->estatus);
    }

    public function test_iniciar_and_completar_fail_directly_from_a_disallowed_estatus(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();

        $cancelado = Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_CORRECTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'estatus' => Mantenimiento::ESTATUS_CANCELADO,
        ]);

        Livewire::test(Mantenimientos::class)->call('iniciar', $cancelado->id);
        $cancelado->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_CANCELADO, $cancelado->estatus);

        $programado = Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_CORRECTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        $component = Livewire::test(Mantenimientos::class)
            ->call('openCompletar', $programado->id);

        $this->assertFalse($component->get('showCompletarModal'));

        // Invocación directa de confirmCompletar sin haber pasado por
        // openCompletar (completandoId sigue null) — no debe hacer nada.
        $component->call('confirmCompletar');

        $programado->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_PROGRAMADO, $programado->estatus);
    }

    public function test_cancelar_only_works_from_programado_reprogramado_or_en_proceso(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();

        $programado = Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_CORRECTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        Livewire::test(Mantenimientos::class)->call('cancelar', $programado->id);
        $programado->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_CANCELADO, $programado->estatus);

        $realizado = Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_CORRECTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'estatus' => Mantenimiento::ESTATUS_REALIZADO,
        ]);

        Livewire::test(Mantenimientos::class)->call('cancelar', $realizado->id);
        $realizado->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_REALIZADO, $realizado->estatus);

        $yaCancelado = Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_CORRECTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'estatus' => Mantenimiento::ESTATUS_CANCELADO,
        ]);

        Livewire::test(Mantenimientos::class)->call('cancelar', $yaCancelado->id);
        $yaCancelado->refresh();
        $this->assertSame(Mantenimiento::ESTATUS_CANCELADO, $yaCancelado->estatus);
    }

    /**
     * Link "Ver ficha" agregado junto al código del activo (Fase 3 etapa 10,
     * Ficha de Activo/Trazabilidad) — apunta al detalle del Activo correcto.
     */
    public function test_ver_ficha_link_points_to_the_correct_asset(): void
    {
        $this->actingAs($this->actingUser());
        $asset = $this->asset();

        Mantenimiento::create([
            'asset_id' => $asset->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        Livewire::test(Mantenimientos::class)
            ->assertSee(route('gestionti.ficha-activo.show', $asset->id), false);
    }

    public function test_filters_by_tipo_origen_estatus_and_search_by_asset_codigo(): void
    {
        $this->actingAs($this->actingUser());
        $tipo = $this->tipoEquipo();

        $preventivo = Mantenimiento::create([
            'asset_id' => $this->asset($tipo)->id,
            'tipo' => Mantenimiento::TIPO_PREVENTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_INTERNO,
            'estatus' => Mantenimiento::ESTATUS_PROGRAMADO,
        ]);

        $correctivo = Mantenimiento::create([
            'asset_id' => $this->asset($tipo)->id,
            'tipo' => Mantenimiento::TIPO_CORRECTIVO,
            'origen_ejecucion' => Mantenimiento::ORIGEN_EXTERNO,
            'estatus' => Mantenimiento::ESTATUS_REALIZADO,
        ]);

        $records = Livewire::test(Mantenimientos::class)
            ->set('tipoFilter', Mantenimiento::TIPO_PREVENTIVO)
            ->viewData('records');
        $this->assertTrue($records->contains($preventivo));
        $this->assertFalse($records->contains($correctivo));

        $records = Livewire::test(Mantenimientos::class)
            ->set('origenFilter', Mantenimiento::ORIGEN_EXTERNO)
            ->viewData('records');
        $this->assertFalse($records->contains($preventivo));
        $this->assertTrue($records->contains($correctivo));

        $records = Livewire::test(Mantenimientos::class)
            ->set('estatusFilter', Mantenimiento::ESTATUS_REALIZADO)
            ->viewData('records');
        $this->assertFalse($records->contains($preventivo));
        $this->assertTrue($records->contains($correctivo));

        $records = Livewire::test(Mantenimientos::class)
            ->set('search', $preventivo->asset->codigo)
            ->viewData('records');
        $this->assertTrue($records->contains($preventivo));
        $this->assertFalse($records->contains($correctivo));
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-mantenimientos',
            'route_name' => 'gestionti.mantenimientos.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-mantenimientos.manage'));
    }
}
