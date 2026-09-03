<?php

namespace Modules\GestionTI\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\Catalogos\Empleados;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\Puesto;
use Modules\GestionTI\Models\Ubicacion;
use Modules\GestionTI\Models\UnidadNegocio;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmpleadosTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Catálogos',
            'name' => 'Empleados',
            'slug' => 'gestionti-catalogos-empleados',
            'route_name' => 'gestionti.catalogos.empleados',
            'permission_name' => 'screens.gestionti-catalogos-empleados.manage',
            'icon' => 'users',
            'order' => 11,
        ]);

        $role = Role::findOrCreate('Almacén/TI', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/catalogos/empleados')->assertForbidden();
    }

    public function test_can_create_an_empleado_with_fk_selects_populated(): void
    {
        $this->actingAs($this->actingUser());

        $puesto = Puesto::create(['nombre' => 'Analista TI']);
        $area = Area::create(['nombre' => 'Sistemas']);
        $ubicacion = Ubicacion::create(['nombre' => 'CDMX']);
        $unidadNegocio = UnidadNegocio::create(['nombre' => 'Corporativo']);
        $empresa = Empresa::create([
            'razon_social' => 'Kosmos Demo S.A. de C.V.',
            'nombre_comercial' => 'Kosmos Demo',
        ]);

        Livewire::test(Empleados::class)
            ->assertSee('Analista TI')
            ->assertSee('Sistemas')
            ->assertSee('CDMX')
            ->assertSee('Corporativo')
            ->assertSee('Kosmos Demo')
            ->call('create')
            ->set('form.numero_empleado', 'EMP-001')
            ->set('form.nombre', 'Juan Pérez')
            ->set('form.correo', 'juan.perez@example.com')
            ->set('form.puesto_id', $puesto->id)
            ->set('form.area_id', $area->id)
            ->set('form.ubicacion_id', $ubicacion->id)
            ->set('form.unidad_negocio_id', $unidadNegocio->id)
            ->set('form.empresa_id', $empresa->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'numero_empleado' => 'EMP-001',
            'nombre' => 'Juan Pérez',
            'puesto_id' => $puesto->id,
            'area_id' => $area->id,
            'ubicacion_id' => $ubicacion->id,
            'unidad_negocio_id' => $unidadNegocio->id,
            'empresa_id' => $empresa->id,
        ]);
    }

    public function test_can_create_an_empleado_with_full_reporting_line(): void
    {
        $this->actingAs($this->actingUser());

        $jefe = Empleado::create(['numero_empleado' => 'EMP-JEFE', 'nombre' => 'Jefe Inmediato']);
        $director = Empleado::create(['numero_empleado' => 'EMP-DIR', 'nombre' => 'Directora General']);
        $directorEjecutivo = Empleado::create(['numero_empleado' => 'EMP-DIREJ', 'nombre' => 'Director Ejecutivo']);

        Livewire::test(Empleados::class)
            ->call('create')
            ->set('form.numero_empleado', 'EMP-010')
            ->set('form.nombre', 'Empleado Con Línea De Mando')
            ->set('form.jefe_inmediato_id', $jefe->id)
            ->set('form.director_id', $director->id)
            ->set('form.director_ejecutivo_id', $directorEjecutivo->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'numero_empleado' => 'EMP-010',
            'jefe_inmediato_id' => $jefe->id,
            'director_id' => $director->id,
            'director_ejecutivo_id' => $directorEjecutivo->id,
        ]);
    }

    /**
     * Fase 4 etapa 2 (PDF de Responsiva — formato real): `rfc` es dato
     * maestro del empleado, opcional.
     */
    public function test_can_create_an_empleado_with_rfc(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Empleados::class)
            ->call('create')
            ->set('form.numero_empleado', 'EMP-020')
            ->set('form.nombre', 'Con RFC')
            ->set('form.rfc', 'XAXX010101000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'numero_empleado' => 'EMP-020',
            'rfc' => 'XAXX010101000',
        ]);
    }

    public function test_rfc_is_optional(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Empleados::class)
            ->call('create')
            ->set('form.numero_empleado', 'EMP-021')
            ->set('form.nombre', 'Sin RFC')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'numero_empleado' => 'EMP-021',
            'rfc' => null,
        ]);
    }

    public function test_validation_requires_numero_empleado_and_nombre(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Empleados::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors(['form.numero_empleado', 'form.nombre']);
    }

    public function test_can_save_leaving_optional_fk_selects_unassigned(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Empleados::class)
            ->call('create')
            ->set('form.numero_empleado', 'EMP-300')
            ->set('form.nombre', 'Sin Asignaciones')
            ->set('form.puesto_id', '')
            ->set('form.area_id', '')
            ->set('form.ubicacion_id', '')
            ->set('form.unidad_negocio_id', '')
            ->set('form.empresa_id', '')
            ->set('form.jefe_inmediato_id', '')
            ->set('form.director_id', '')
            ->set('form.director_ejecutivo_id', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'numero_empleado' => 'EMP-300',
            'puesto_id' => null,
            'area_id' => null,
            'ubicacion_id' => null,
            'unidad_negocio_id' => null,
            'empresa_id' => null,
            'jefe_inmediato_id' => null,
            'director_id' => null,
            'director_ejecutivo_id' => null,
        ]);
    }

    public function test_numero_empleado_must_be_unique(): void
    {
        $this->actingAs($this->actingUser());

        Empleado::create(['numero_empleado' => 'EMP-100', 'nombre' => 'Existente']);

        Livewire::test(Empleados::class)
            ->call('create')
            ->set('form.numero_empleado', 'EMP-100')
            ->set('form.nombre', 'Duplicado')
            ->call('save')
            ->assertHasErrors(['form.numero_empleado']);
    }

    public function test_editing_an_empleado_keeps_its_own_numero_empleado_valid(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create(['numero_empleado' => 'EMP-200', 'nombre' => 'Sin cambios']);

        Livewire::test(Empleados::class)
            ->call('edit', $empleado->id)
            ->set('form.nombre', 'Sin cambios actualizado')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_can_edit_and_reassign_fks(): void
    {
        $this->actingAs($this->actingUser());

        $puestoUno = Puesto::create(['nombre' => 'Analista TI']);
        $puestoDos = Puesto::create(['nombre' => 'Coordinador TI']);

        $empleado = Empleado::create([
            'numero_empleado' => 'EMP-002',
            'nombre' => 'María López',
            'puesto_id' => $puestoUno->id,
        ]);

        Livewire::test(Empleados::class)
            ->call('edit', $empleado->id)
            ->assertSet('form.puesto_id', $puestoUno->id)
            ->set('form.puesto_id', $puestoDos->id)
            ->set('form.nombre', 'María López Actualizada')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'id' => $empleado->id,
            'nombre' => 'María López Actualizada',
            'puesto_id' => $puestoDos->id,
        ]);
    }

    public function test_jefe_inmediato_options_exclude_self_when_editing(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create([
            'numero_empleado' => 'EMP-003',
            'nombre' => 'Carlos Ruiz',
        ]);

        $otro = Empleado::create([
            'numero_empleado' => 'EMP-004',
            'nombre' => 'Ana Torres',
        ]);

        $editingHtml = Livewire::test(Empleados::class)
            ->call('edit', $empleado->id)
            ->html();

        $this->assertStringContainsString('<option value="'.$otro->id.'">Ana Torres</option>', $editingHtml);
        $this->assertStringNotContainsString('<option value="'.$empleado->id.'">Carlos Ruiz</option>', $editingHtml);

        // Al crear uno nuevo (sin editingId), ambos deben estar disponibles como jefe inmediato.
        $creatingHtml = Livewire::test(Empleados::class)
            ->call('create')
            ->html();

        $this->assertStringContainsString('<option value="'.$otro->id.'">Ana Torres</option>', $creatingHtml);
        $this->assertStringContainsString('<option value="'.$empleado->id.'">Carlos Ruiz</option>', $creatingHtml);
    }

    public function test_director_options_exclude_self_when_editing(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create([
            'numero_empleado' => 'EMP-006',
            'nombre' => 'Beatriz Sánchez',
        ]);

        $otro = Empleado::create([
            'numero_empleado' => 'EMP-007',
            'nombre' => 'Roberto Díaz',
        ]);

        // El select de Director reutiliza la misma colección `empleadoOptions`
        // que Jefe inmediato/Director Ejecutivo — basta con confirmar que la
        // exclusión del propio registro al editar se sigue cumpliendo.
        $editingHtml = Livewire::test(Empleados::class)
            ->call('edit', $empleado->id)
            ->html();

        $this->assertStringContainsString('<option value="'.$otro->id.'">Roberto Díaz</option>', $editingHtml);
        $this->assertStringNotContainsString('<option value="'.$empleado->id.'">Beatriz Sánchez</option>', $editingHtml);
    }

    public function test_director_ejecutivo_options_exclude_self_when_editing(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create([
            'numero_empleado' => 'EMP-008',
            'nombre' => 'Fernando Castro',
        ]);

        $otro = Empleado::create([
            'numero_empleado' => 'EMP-009',
            'nombre' => 'Patricia Vega',
        ]);

        $editingHtml = Livewire::test(Empleados::class)
            ->call('edit', $empleado->id)
            ->html();

        $this->assertStringContainsString('<option value="'.$otro->id.'">Patricia Vega</option>', $editingHtml);
        $this->assertStringNotContainsString('<option value="'.$empleado->id.'">Fernando Castro</option>', $editingHtml);
    }

    public function test_can_toggle_activo(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create([
            'numero_empleado' => 'EMP-005',
            'nombre' => 'Luis Gómez',
        ]);

        Livewire::test(Empleados::class)
            ->call('toggleActivo', $empleado->id);

        $this->assertFalse($empleado->fresh()->activo);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-catalogos-empleados',
            'route_name' => 'gestionti.catalogos.empleados',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-catalogos-empleados.manage'));

        $screen = Screen::where('slug', 'gestionti-catalogos-empleados')->first();
        $this->assertNotNull($screen);
    }
}
