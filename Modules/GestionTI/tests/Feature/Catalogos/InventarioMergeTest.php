<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Inventario;
use Modules\GestionTI\Models\ArticuloSolicitud;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\PeriodicidadMantenimiento;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\TipoEquipo;
use Modules\GestionTI\Models\Ubicacion;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventarioMergeTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Inventarios',
            'name' => 'Catálogos de Inventario',
            'slug' => 'gestionti-catalogos-inventario',
            'route_name' => 'gestionti.catalogos.inventario',
            'permission_name' => 'screens.gestionti-catalogos-inventario.manage',
            'icon' => 'archive-box',
            'order' => 30,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_merge_button_appears_only_for_tabs_with_merge_references_configured(): void
    {
        $this->actingAs($this->actingUser());

        // No se usa assertDontSee('Fusionar duplicados') a secas: la modal
        // de ayuda ("?", ver AyudaCatalog::contenido('catalogos-inventario'))
        // explica el concepto "Fusionar duplicados" de forma genérica para
        // toda la pantalla, sin importar la pestaña activa — no es un bug,
        // es documentación (mismo criterio ya aplicado en DashboardTest). Se
        // verifica en su lugar la ausencia del botón real, único por tener
        // el atributo wire:click="openMerge" (la modal de ayuda es texto
        // estático, sin ese atributo).
        Livewire::test(Inventario::class)
            ->assertSee('Fusionar duplicados')
            ->call('setTab', 'periodicidad_mantenimiento')
            ->assertDontSee('wire:click="openMerge"', false)
            ->call('setTab', 'stock_minimo')
            ->assertDontSee('wire:click="openMerge"', false);
    }

    public function test_merging_two_tipo_equipo_reassigns_every_reference_and_deletes_the_duplicate(): void
    {
        $this->actingAs($this->actingUser());

        $duplicate = TipoEquipo::create(['nombre' => 'MONITOR']);
        $keep = TipoEquipo::create(['nombre' => 'Monitor']);
        $estatus = EstatusActivo::create(['codigo' => 'en_stock', 'nombre' => 'En stock']);
        $ubicacion = Ubicacion::create(['nombre' => 'Guanajuato']);

        $articulo = ArticuloSolicitud::create([
            'codigo' => 'ART-100',
            'descripcion' => 'Monitor genérico',
            'unidad_medida' => 'pieza',
            'tipo_equipo_id' => $duplicate->id,
        ]);

        $periodicidad = PeriodicidadMantenimiento::create([
            'tipo_equipo_id' => $duplicate->id,
            'meses_sugeridos' => 12,
        ]);

        $stock = StockMinimo::create([
            'tipo_equipo_id' => $duplicate->id,
            'ubicacion_id' => $ubicacion->id,
            'cantidad_minima' => 3,
        ]);

        $asset = Asset::create([
            'codigo' => 'KOS-MONITOR-000001',
            'tipo_equipo_id' => $duplicate->id,
            'origen_tipo' => 'ajuste_manual',
            'estatus_id' => $estatus->id,
        ]);

        Livewire::test(Inventario::class)
            ->call('openMerge')
            ->set('mergeDeleteId', $duplicate->id)
            ->set('mergeKeepId', $keep->id)
            ->call('confirmMerge')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('tipos_equipo', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('tipos_equipo', ['id' => $keep->id]);

        $this->assertSame($keep->id, $articulo->fresh()->tipo_equipo_id);
        $this->assertSame($keep->id, $periodicidad->fresh()->tipo_equipo_id);
        $this->assertSame($keep->id, $stock->fresh()->tipo_equipo_id);
        $this->assertSame($keep->id, $asset->fresh()->tipo_equipo_id);
    }

    public function test_merging_a_record_into_itself_is_rejected(): void
    {
        $this->actingAs($this->actingUser());

        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);

        Livewire::test(Inventario::class)
            ->call('openMerge')
            ->set('mergeDeleteId', $tipoEquipo->id)
            ->set('mergeKeepId', $tipoEquipo->id)
            ->call('confirmMerge')
            ->assertHasErrors(['mergeDeleteId']);

        $this->assertDatabaseHas('tipos_equipo', ['id' => $tipoEquipo->id]);
    }
}
