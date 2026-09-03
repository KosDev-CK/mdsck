<?php

namespace Modules\GestionTI\Tests\Feature\MesaServicio;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\MesaServicio\Tickets;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Tickets',
            'slug' => 'gestionti-tickets',
            'route_name' => 'gestionti.tickets.index',
            'permission_name' => 'screens.gestionti-tickets.manage',
            'icon' => 'ticket',
            'order' => 1,
        ]);

        $role = Role::findOrCreate('Mesa de Servicio', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/tickets')->assertForbidden();
    }

    public function test_can_create_a_ticket(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create(['numero_empleado' => 'EMP-001', 'nombre' => 'Juan Pérez']);

        Livewire::test(Tickets::class)
            ->call('create')
            ->set('form.sdp_id', '12345')
            ->set('form.sdp_display_id', 'SDP-12345')
            ->set('form.fecha', '2026-08-31')
            ->set('form.empleado_id', $empleado->id)
            ->set('form.observaciones', 'Equipo dañado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tickets', [
            'sdp_id' => '12345',
            'sdp_display_id' => 'SDP-12345',
            'empleado_id' => $empleado->id,
            'observaciones' => 'Equipo dañado',
        ]);
    }

    public function test_validation_requires_fecha_and_empleado(): void
    {
        $this->actingAs($this->actingUser());

        Livewire::test(Tickets::class)
            ->call('create')
            ->set('form.fecha', '')
            ->set('form.empleado_id', '')
            ->call('save')
            ->assertHasErrors(['form.fecha', 'form.empleado_id']);
    }

    public function test_can_edit_a_ticket(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create(['numero_empleado' => 'EMP-002', 'nombre' => 'María López']);
        $ticket = Ticket::create([
            'fecha' => '2026-08-01',
            'empleado_id' => $empleado->id,
        ]);

        Livewire::test(Tickets::class)
            ->call('edit', $ticket->id)
            ->assertSet('form.empleado_id', $empleado->id)
            ->set('form.observaciones', 'Actualizado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'observaciones' => 'Actualizado',
        ]);
    }

    public function test_search_filters_by_folio_and_solicitante(): void
    {
        // No se usan assertSee/assertDontSee sobre nombres aquí a propósito:
        // el modal de crear/editar siempre se renderiza en el HTML (Alpine
        // solo lo oculta con x-show), así que los <option> del select de
        // Solicitante contienen TODOS los empleados sin importar el filtro
        // de búsqueda de la tabla — se verifica contra los `records` que
        // llegan a la vista en vez de contra el HTML completo.
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create(['numero_empleado' => 'EMP-003', 'nombre' => 'Carlos Ruiz']);
        $ticketMatch = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id, 'sdp_display_id' => 'SDP-999']);

        $otroEmpleado = Empleado::create(['numero_empleado' => 'EMP-004', 'nombre' => 'Ana Torres']);
        $ticketOther = Ticket::create(['fecha' => '2026-08-02', 'empleado_id' => $otroEmpleado->id, 'sdp_display_id' => 'SDP-111']);

        $records = Livewire::test(Tickets::class)
            ->set('search', 'SDP-999')
            ->viewData('records');

        $this->assertTrue($records->contains('id', $ticketMatch->id));
        $this->assertFalse($records->contains('id', $ticketOther->id));
    }

    public function test_list_shows_solicitudes_sic_count(): void
    {
        $this->actingAs($this->actingUser());

        $empleado = Empleado::create(['numero_empleado' => 'EMP-005', 'nombre' => 'Luis Gómez']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id]);

        $empresa = \Modules\GestionTI\Models\Empresa::create(['razon_social' => 'Kosmos Demo S.A. de C.V.', 'nombre_comercial' => 'Kosmos Demo']);
        $centroCosto = \Modules\GestionTI\Models\CentroCosto::create(['codigo' => 'CC-1', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);
        $tipoEquipo = \Modules\GestionTI\Models\TipoEquipo::create(['nombre' => 'Laptop']);

        SolicitudSicBorrador::create([
            'ticket_id' => $ticket->id,
            'empleado_id' => $empleado->id,
            'tipo_equipo_id' => $tipoEquipo->id,
            'motivo' => 'Equipo nuevo',
            'centro_costo_id' => $centroCosto->id,
            'urgencia' => 'media',
            'fecha_solicitud' => '2026-08-01',
        ]);

        $records = Livewire::test(Tickets::class)->viewData('records');

        $this->assertSame(1, $records->firstWhere('id', $ticket->id)->solicitudes_sic_count);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-tickets',
            'route_name' => 'gestionti.tickets.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-tickets.manage'));
    }
}
