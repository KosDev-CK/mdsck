<?php

namespace Modules\GestionTI\Tests\Feature\PresupuestoProyectos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\PresupuestoProyectos\Show;
use Modules\GestionTI\Models\Area;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\ProyectoPresupuesto;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\ProyectoPresupuestoAutorizacion;
use Modules\GestionTI\Notifications\AvisoNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShowTest extends TestCase
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

    private function empleado(string $numero): Empleado
    {
        return Empleado::create(['numero_empleado' => $numero, 'nombre' => "Empleado {$numero}"]);
    }

    private function proyecto(array $overrides = []): ProyectoPresupuesto
    {
        $empresa = Empresa::create(['razon_social' => 'Kosmos S.A. de C.V.', 'nombre_comercial' => 'Kosmos']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $area = Area::create(['nombre' => 'Operaciones']);
        $pm = $this->empleado('EMP-PM');

        return ProyectoPresupuesto::create(array_merge([
            'nombre_proyecto' => 'Nuevo Centro Guadalajara',
            'empresa_id' => $empresa->id,
            'centro_costo_id' => $centroCosto->id,
            'direccion_centro' => 'Av. Siempre Viva 123',
            'area_operativa_solicitante_id' => $area->id,
            'pm_responsable_id' => $pm->id,
            'fecha_solicitud' => '2026-09-01',
            'fecha_limite_captura' => '2026-09-15',
        ], $overrides));
    }

    private function agregarArticulo(ProyectoPresupuesto $proyecto, array $overrides = []): ProyectoPresupuestoArticulo
    {
        $responsable = $this->empleado('EMP-RESP-'.random_int(1000, 9999));

        return $proyecto->articulos()->create(array_merge([
            'categoria' => 'laptops_desktops',
            'descripcion' => 'Laptop para gerente',
            'cantidad' => 1,
            'responsable_costo_id' => $responsable->id,
        ], $overrides));
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $proyecto = $this->proyecto();
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get("/presupuestos-proyecto/{$proyecto->id}")->assertForbidden();
    }

    public function test_can_add_edit_and_delete_articulo_only_while_armado(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto();
        $responsable = $this->empleado('EMP-R1');

        $component = Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('openArticuloModal')
            ->set('articuloForm.categoria', 'laptops_desktops')
            ->set('articuloForm.descripcion', 'Laptop Dell')
            ->set('articuloForm.cantidad', 3)
            ->set('articuloForm.responsable_costo_id', $responsable->id)
            ->call('saveArticulo')
            ->assertHasNoErrors();

        $articulo = $proyecto->articulos()->firstOrFail();
        $this->assertSame('Laptop Dell', $articulo->descripcion);

        $component->call('editArticulo', $articulo->id)
            ->set('articuloForm.descripcion', 'Laptop Dell Editada')
            ->call('saveArticulo');

        $this->assertSame('Laptop Dell Editada', $articulo->fresh()->descripcion);

        // Avanzamos el proyecto más allá de "armado" — las 3 acciones deben
        // dejar de tener efecto aunque se invoquen directamente.
        $proyecto->update(['estatus' => ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS]);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->assertDontSee('Agregar artículo')
            ->call('editArticulo', $articulo->id)
            ->set('articuloForm.descripcion', 'Intento de edición fuera de armado')
            ->call('saveArticulo');

        $this->assertSame('Laptop Dell Editada', $articulo->fresh()->descripcion);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('deleteArticulo', $articulo->id);

        $this->assertNotNull($articulo->fresh());
    }

    public function test_enviar_a_captura_costos_requires_at_least_one_articulo_and_validates_origen_estatus(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto();

        // Sin artículos — no debe transicionar.
        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('enviarACapturaCostos');

        $this->assertSame(ProyectoPresupuesto::ESTATUS_ARMADO, $proyecto->fresh()->estatus);

        $this->agregarArticulo($proyecto);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('enviarACapturaCostos');

        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS, $proyecto->fresh()->estatus);

        // Ya no está en armado — un segundo intento no debe hacer nada.
        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->call('enviarACapturaCostos');

        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS, $proyecto->fresh()->estatus);
    }

    public function test_capturar_costo_transitions_articulo_estatus(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS]);
        $articulo = $this->agregarArticulo($proyecto);
        $this->agregarArticulo($proyecto);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->set("costoInputs.{$articulo->id}", 15000.50)
            ->call('capturarCosto', $articulo->id)
            ->assertHasNoErrors();

        $articulo->refresh();
        $this->assertSame('capturado', $articulo->estatus_captura);
        $this->assertNotNull($articulo->fecha_captura);
        $this->assertEquals(15000.50, $articulo->costo_unitario);

        // Todavía queda un artículo pendiente — el proyecto no transiciona.
        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS, $proyecto->fresh()->estatus);
    }

    public function test_capturing_the_last_pending_articulo_transitions_proyecto_to_completo(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS]);
        $uno = $this->agregarArticulo($proyecto);
        $dos = $this->agregarArticulo($proyecto);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->set("costoInputs.{$uno->id}", 100)
            ->call('capturarCosto', $uno->id);

        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS, $proyecto->fresh()->estatus);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto->fresh()])
            ->set("costoInputs.{$dos->id}", 200)
            ->call('capturarCosto', $dos->id);

        $this->assertSame(ProyectoPresupuesto::ESTATUS_COMPLETO, $proyecto->fresh()->estatus);
    }

    public function test_enviar_a_autorizacion_creates_levels_in_order_and_transitions_estatus(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_COMPLETO]);
        $aprobador1 = $this->empleado('EMP-AP1');
        $aprobador2 = $this->empleado('EMP-AP2');

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('openAutorizacionModal')
            ->set('niveles.0.aprobador_id', $aprobador1->id)
            ->call('addNivel')
            ->set('niveles.1.aprobador_id', $aprobador2->id)
            ->call('enviarAAutorizacion')
            ->assertHasNoErrors();

        $proyecto->refresh();
        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION, $proyecto->estatus);

        $autorizaciones = $proyecto->autorizaciones()->orderBy('nivel')->get();
        $this->assertCount(2, $autorizaciones);
        $this->assertSame(1, $autorizaciones[0]->nivel);
        $this->assertSame($aprobador1->id, $autorizaciones[0]->aprobador_id);
        $this->assertSame(2, $autorizaciones[1]->nivel);
        $this->assertSame($aprobador2->id, $autorizaciones[1]->aprobador_id);
        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE, $autorizaciones[0]->estatus);
    }

    public function test_approving_nivel_out_of_order_is_silently_rejected(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION]);
        $aprobador1 = $this->empleado('EMP-AP1');
        $aprobador2 = $this->empleado('EMP-AP2');

        $nivel1 = $proyecto->autorizaciones()->create(['nivel' => 1, 'aprobador_id' => $aprobador1->id]);
        $nivel2 = $proyecto->autorizaciones()->create(['nivel' => 2, 'aprobador_id' => $aprobador2->id]);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('autorizarNivel', $nivel2->id);

        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE, $nivel2->fresh()->estatus);
        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION, $proyecto->fresh()->estatus);
    }

    public function test_approving_the_last_level_transitions_proyecto_to_autorizado(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION]);
        $aprobador1 = $this->empleado('EMP-AP1');
        $aprobador2 = $this->empleado('EMP-AP2');

        $nivel1 = $proyecto->autorizaciones()->create(['nivel' => 1, 'aprobador_id' => $aprobador1->id]);
        $nivel2 = $proyecto->autorizaciones()->create(['nivel' => 2, 'aprobador_id' => $aprobador2->id]);

        $component = Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('autorizarNivel', $nivel1->id, 'Todo en orden');

        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_APROBADO, $nivel1->fresh()->estatus);
        $this->assertSame(ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION, $proyecto->fresh()->estatus);

        $component->call('autorizarNivel', $nivel2->id, 'Aprobado también');

        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_APROBADO, $nivel2->fresh()->estatus);
        $this->assertSame(ProyectoPresupuesto::ESTATUS_AUTORIZADO, $proyecto->fresh()->estatus);
    }

    public function test_rejecting_any_level_transitions_proyecto_to_rechazado_without_touching_remaining_levels(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION]);
        $aprobador1 = $this->empleado('EMP-AP1');
        $aprobador2 = $this->empleado('EMP-AP2');

        $nivel1 = $proyecto->autorizaciones()->create(['nivel' => 1, 'aprobador_id' => $aprobador1->id]);
        $nivel2 = $proyecto->autorizaciones()->create(['nivel' => 2, 'aprobador_id' => $aprobador2->id]);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('rechazarNivel', $nivel1->id, 'No cumple presupuesto');

        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_RECHAZADO, $nivel1->fresh()->estatus);
        $this->assertSame(ProyectoPresupuesto::ESTATUS_RECHAZADO, $proyecto->fresh()->estatus);
        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE, $nivel2->fresh()->estatus);
    }

    /**
     * Regresión del bug encontrado en revisión: `render()` calculaba el nivel
     * "accionable" como simplemente el primer `pendiente` por orden, sin
     * verificar que los niveles anteriores estuvieran `aprobado` — un nivel 2
     * técnicamente `pendiente` tras el rechazo del nivel 1 se mostraba con
     * botones Aprobar/Rechazar aunque el proyecto ya era terminal
     * (`rechazado`). Verifica que ni la vista muestra esos botones para el
     * nivel 2 ni que `autorizarNivel()`/`rechazarNivel()` le hacen efecto.
     */
    public function test_no_level_past_a_rejected_one_is_ever_shown_as_actionable(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION]);
        $aprobador1 = $this->empleado('EMP-AP1');
        $aprobador2 = $this->empleado('EMP-AP2');

        $nivel1 = $proyecto->autorizaciones()->create(['nivel' => 1, 'aprobador_id' => $aprobador1->id]);
        $nivel2 = $proyecto->autorizaciones()->create(['nivel' => 2, 'aprobador_id' => $aprobador2->id]);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('rechazarNivel', $nivel1->id);

        $component = Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto->fresh()]);

        $this->assertNull($component->viewData('nivelAccionableId'));

        $component->call('autorizarNivel', $nivel2->id);
        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE, $nivel2->fresh()->estatus);

        $component->call('rechazarNivel', $nivel2->id);
        $this->assertSame(ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE, $nivel2->fresh()->estatus);
    }

    public function test_export_to_excel_returns_correct_content_type(): void
    {
        $this->actingAs($this->actingUser());
        $proyecto = $this->proyecto();
        $this->agregarArticulo($proyecto, ['costo_unitario' => 1000]);

        $response = $this->get(route('gestionti.presupuestos-proyecto.export', $proyecto));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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

    /**
     * Confirma que `capturarCosto()` dispara `PRESUPUESTO_LISTO_PARA_AUTORIZAR`
     * (Fase 4, "Configuración de Avisos") exactamente cuando el último
     * artículo pendiente queda capturado — mismo momento en que el proyecto
     * transiciona a `completo`.
     */
    public function test_capturing_the_last_pending_articulo_dispatches_presupuesto_listo_para_autorizar_aviso(): void
    {
        $this->actingAs($this->actingUser());
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_CAPTURA_COSTOS]);
        $pmUser = User::factory()->create(['email' => 'pm-responsable@example.com']);
        $proyecto->pmResponsable()->update(['correo' => 'pm-responsable@example.com']);
        $articulo = $this->agregarArticulo($proyecto);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->set("costoInputs.{$articulo->id}", 100)
            ->call('capturarCosto', $articulo->id);

        Notification::assertSentTo($pmUser, AvisoNotification::class);
        $this->assertSame(2, AvisoEnviado::where('destinatario_user_id', $pmUser->id)->count());
    }

    /**
     * Confirma que `autorizarNivel()` dispara `PROYECTO_AUTORIZADO` una vez
     * por cada `responsable_costo_id` distinto entre los artículos del
     * proyecto (no una vez por artículo) cuando el último nivel se aprueba.
     */
    public function test_approving_the_last_level_dispatches_proyecto_autorizado_once_per_distinct_responsable(): void
    {
        $this->actingAs($this->actingUser());
        $this->artisan('module:seed', ['module' => 'GestionTI']);
        Notification::fake();

        $proyecto = $this->proyecto(['estatus' => ProyectoPresupuesto::ESTATUS_EN_AUTORIZACION]);
        $aprobador1 = $this->empleado('EMP-AP1');

        $responsableCompartido = $this->empleado('EMP-RESP-COMPARTIDO');
        $responsableCompartido->update(['correo' => 'compartido@example.com']);
        $userCompartido = User::factory()->create(['email' => 'compartido@example.com']);

        // 2 artículos con el MISMO responsable — debe dispararse una sola vez.
        $this->agregarArticulo($proyecto, ['responsable_costo_id' => $responsableCompartido->id]);
        $this->agregarArticulo($proyecto, ['responsable_costo_id' => $responsableCompartido->id]);

        $nivel1 = $proyecto->autorizaciones()->create(['nivel' => 1, 'aprobador_id' => $aprobador1->id]);

        Livewire::test(Show::class, ['proyectoPresupuesto' => $proyecto])
            ->call('autorizarNivel', $nivel1->id);

        $this->assertSame(ProyectoPresupuesto::ESTATUS_AUTORIZADO, $proyecto->fresh()->estatus);
        Notification::assertSentToTimes($userCompartido, AvisoNotification::class, 1);
    }
}
