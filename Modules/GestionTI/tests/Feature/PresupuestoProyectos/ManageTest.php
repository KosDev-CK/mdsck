<?php

namespace Modules\GestionTI\Tests\Feature\PresupuestoProyectos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Manage;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Presupuesto de Proyectos',
            'name' => 'Presupuesto por Proyecto',
            'slug' => 'gestionti-presupuestos-proyecto',
            'route_name' => 'gestionti.presupuestos-proyecto.index',
            'permission_name' => 'screens.gestionti-presupuestos-proyecto.manage',
            'icon' => 'banknotes',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Compras', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function empresa(): Empresa
    {
        return Empresa::create(['razon_social' => 'Kosmos S.A. de C.V.', 'nombre_comercial' => 'Kosmos']);
    }

    private function centroCosto(Empresa $empresa): CentroCosto
    {
        return CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
    }

    private function area(): Area
    {
        return Area::create(['nombre' => 'Operaciones']);
    }

    private function empleado(string $numero = 'EMP-PM'): Empleado
    {
        return Empleado::create(['numero_empleado' => $numero, 'nombre' => 'PM de Prueba']);
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/presupuestos-proyecto')->assertForbidden();
    }

    public function test_creating_a_proyecto_redirects_to_its_detail_screen(): void
    {
        $this->actingAs($this->actingUser());
        $empresa = $this->empresa();
        $centroCosto = $this->centroCosto($empresa);
        $area = $this->area();
        $pm = $this->empleado();

        Livewire::test(Manage::class)
            ->call('create')
            ->set('form.nombre_proyecto', 'Nuevo Centro Guadalajara')
            ->set('form.empresa_id', $empresa->id)
            ->set('form.centro_costo_id', $centroCosto->id)
            ->set('form.direccion_centro', 'Av. Siempre Viva 123')
            ->set('form.area_operativa_solicitante_id', $area->id)
            ->set('form.pm_responsable_id', $pm->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.fecha_limite_captura', '2026-09-15')
            ->call('save')
            ->assertHasNoErrors();

        $record = ProyectoPresupuesto::where('nombre_proyecto', 'Nuevo Centro Guadalajara')->firstOrFail();

        $this->assertSame(ProyectoPresupuesto::ESTATUS_ARMADO, $record->estatus);
        // Prefill de `create()`, ver Manage::create() — confirma que el
        // factor se guarda aunque el usuario no lo toque.
        $this->assertEquals(1.035, $record->factor_administrativo);
    }

    /**
     * `factor_administrativo` varía por proyecto (confirmado con el
     * usuario) — se puede capturar un valor distinto al default antes de
     * guardar, y `min:1` rechaza valores sin sentido (un factor menor a 1
     * implicaría descontar del total, no es el caso de uso real).
     */
    public function test_factor_administrativo_can_be_customized_and_is_validated(): void
    {
        $this->actingAs($this->actingUser());
        $empresa = $this->empresa();
        $centroCosto = $this->centroCosto($empresa);
        $area = $this->area();
        $pm = $this->empleado();

        Livewire::test(Manage::class)
            ->call('create')
            ->set('form.nombre_proyecto', 'Proyecto Factor Custom')
            ->set('form.empresa_id', $empresa->id)
            ->set('form.centro_costo_id', $centroCosto->id)
            ->set('form.direccion_centro', 'Av. Siempre Viva 123')
            ->set('form.area_operativa_solicitante_id', $area->id)
            ->set('form.pm_responsable_id', $pm->id)
            ->set('form.fecha_solicitud', '2026-09-01')
            ->set('form.fecha_limite_captura', '2026-09-15')
            ->set('form.factor_administrativo', '1.10')
            ->call('save')
            ->assertHasNoErrors();

        $record = ProyectoPresupuesto::where('nombre_proyecto', 'Proyecto Factor Custom')->firstOrFail();
        $this->assertEquals(1.1, $record->factor_administrativo);

        Livewire::test(Manage::class)
            ->call('create')
            ->set('form.factor_administrativo', '0.5')
            ->call('save')
            ->assertHasErrors(['form.factor_administrativo' => 'min']);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Manage::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors([
                'form.nombre_proyecto' => 'required',
                'form.empresa_id' => 'required',
                'form.centro_costo_id' => 'required',
                'form.direccion_centro' => 'required',
                'form.area_operativa_solicitante_id' => 'required',
                'form.pm_responsable_id' => 'required',
            ]);
    }

    public function test_search_and_estatus_filter(): void
    {
        $this->actingAs($this->actingUser());
        $empresa = $this->empresa();
        $centroCosto = $this->centroCosto($empresa);
        $area = $this->area();
        $pm = $this->empleado();

        $uno = ProyectoPresupuesto::create([
            'nombre_proyecto' => 'Centro Monterrey',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => $centroCosto->id,
            'direccion_centro' => 'Calle 1',
            'area_operativa_solicitante_id' => $area->id,
            'pm_responsable_id' => $pm->id,
            'fecha_solicitud' => '2026-08-01',
            'fecha_limite_captura' => '2026-08-15',
        ]);

        $dos = ProyectoPresupuesto::create([
            'nombre_proyecto' => 'Centro Puebla',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => $centroCosto->id,
            'direccion_centro' => 'Calle 2',
            'area_operativa_solicitante_id' => $area->id,
            'pm_responsable_id' => $pm->id,
            'fecha_solicitud' => '2026-08-02',
            'fecha_limite_captura' => '2026-08-16',
            'estatus' => ProyectoPresupuesto::ESTATUS_AUTORIZADO,
        ]);

        $component = Livewire::test(Manage::class)->set('search', 'Monterrey');
        $nombres = $component->viewData('records')->pluck('nombre_proyecto')->all();
        $this->assertContains('Centro Monterrey', $nombres);
        $this->assertNotContains('Centro Puebla', $nombres);

        $component = Livewire::test(Manage::class)->set('estatusFilter', ProyectoPresupuesto::ESTATUS_AUTORIZADO);
        $nombres = $component->viewData('records')->pluck('nombre_proyecto')->all();
        $this->assertContains('Centro Puebla', $nombres);
        $this->assertNotContains('Centro Monterrey', $nombres);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-presupuestos-proyecto',
            'route_name' => 'gestionti.presupuestos-proyecto.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-presupuestos-proyecto.manage'));
    }
}
