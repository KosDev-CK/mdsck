<?php

namespace Modules\GestionTI\Tests\Feature\MesaServicio;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\GestionTI\Livewire\MesaServicio\EbsRequisiciones;
use Modules\GestionTI\Models\CentroCosto;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Empresa;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\Ticket;
use Modules\GestionTI\Models\TipoEquipo;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EbsRequisicionesTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'GestionTI',
            'group_label' => 'Mesa de Servicio',
            'name' => 'SIC en EBS',
            'slug' => 'gestionti-ebs-requisiciones',
            'route_name' => 'gestionti.ebs-requisiciones.index',
            'permission_name' => 'screens.gestionti-ebs-requisiciones.manage',
            'icon' => 'arrow-path',
            'order' => 3,
        ]);

        $role = Role::findOrCreate('Mesa de Servicio', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function baseCatalogos(): array
    {
        $empleado = Empleado::create(['numero_empleado' => 'EMP-SCR-1', 'nombre' => 'Solicitante Pantalla']);
        $ticket = Ticket::create(['fecha' => '2026-08-01', 'empleado_id' => $empleado->id, 'sdp_display_id' => 'SDP-SCR-1']);
        $tipoEquipo = TipoEquipo::create(['nombre' => 'Laptop']);
        $empresa = Empresa::create(['razon_social' => 'Kosmos Pantalla S.A. de C.V.', 'nombre_comercial' => 'Kosmos Pantalla']);
        $centroCosto = CentroCosto::create(['codigo' => 'CC-SCR', 'nombre' => 'Corporativo', 'empresa_id' => $empresa->id]);

        return compact('empleado', 'ticket', 'tipoEquipo', 'centroCosto');
    }

    public function test_route_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/ebs-requisiciones')->assertForbidden();
    }

    public function test_lists_ebs_requisitions(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => '6489',
            'description' => 'Equipo Laptop Nueva',
            'status' => 'APPROVED',
            'fecha_creacion' => '2026-09-01',
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->assertSee('6489')
            ->assertSee('Equipo Laptop Nueva');
    }

    public function test_codigo_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create(['requisition_header_id' => 1, 'code' => '6489', 'description' => 'A']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => '9999', 'description' => 'B']);

        Livewire::test(EbsRequisiciones::class)
            ->set('codigoFilter', '6489')
            ->assertSee('6489')
            ->assertDontSee('9999');
    }

    public function test_estatus_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());

        EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'A1', 'status' => 'APPROVED']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => 'A2', 'status' => 'REJECTED']);

        Livewire::test(EbsRequisiciones::class)
            ->set('estatusFilter', 'REJECTED')
            ->assertSee('A2')
            ->assertDontSee('A1');
    }

    public function test_vinculacion_filter_narrows_the_list(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $vinculada = EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'V1']);
        EbsRequisition::create(['requisition_header_id' => 2, 'code' => 'V2']);

        SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'ebs_requisition_id' => $vinculada->id,
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->set('vinculacionFilter', 'no_vinculada')
            ->assertSee('V2')
            ->assertDontSee('V1');
    }

    public function test_can_link_manually_to_a_solicitud_and_it_syncs_the_local_status(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $ebsRequisicion = EbsRequisition::create([
            'requisition_header_id' => 1,
            'code' => 'MANUAL-9',
            'status' => 'APPROVED',
        ]);

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'folio_sic' => 'SIN-MATCH',
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->call('openVincular', $ebsRequisicion->id)
            ->set('vincularSearch', 'SIN-MATCH')
            ->set('vincularSolicitudId', $solicitud->id)
            ->call('confirmVincular')
            ->assertHasNoErrors();

        $solicitud->refresh();
        $this->assertSame($ebsRequisicion->id, $solicitud->ebs_requisition_id);
        $this->assertSame(SolicitudSicBorrador::ESTATUS_AUTORIZADA, $solicitud->estatus);
    }

    public function test_cannot_link_a_solicitud_already_linked_to_a_different_requisition(): void
    {
        $this->actingAs($this->actingUser());
        $c = $this->baseCatalogos();

        $otra = EbsRequisition::create(['requisition_header_id' => 1, 'code' => 'OTRA']);
        $nueva = EbsRequisition::create(['requisition_header_id' => 2, 'code' => 'NUEVA']);

        $solicitud = SolicitudSicBorrador::create([
            'ticket_id' => $c['ticket']->id,
            'empleado_id' => $c['empleado']->id,
            'tipo_equipo_id' => $c['tipoEquipo']->id,
            'motivo' => 'x',
            'centro_costo_id' => $c['centroCosto']->id,
            'urgencia' => 'baja',
            'fecha_solicitud' => '2026-08-01',
            'ebs_requisition_id' => $otra->id,
        ]);

        Livewire::test(EbsRequisiciones::class)
            ->call('openVincular', $nueva->id)
            ->set('vincularSolicitudId', $solicitud->id)
            ->call('confirmVincular')
            ->assertHasErrors(['vincularSolicitudId']);

        $this->assertSame($otra->id, $solicitud->fresh()->ebs_requisition_id);
    }

    public function test_screen_is_seeded_and_visible_to_administrador(): void
    {
        $this->artisan('module:seed', ['module' => 'GestionTI']);

        $this->assertDatabaseHas('screens', [
            'slug' => 'gestionti-ebs-requisiciones',
            'route_name' => 'gestionti.ebs-requisiciones.index',
        ]);

        $admin = Role::findOrCreate('Administrador', 'web');
        $this->assertTrue($admin->hasPermissionTo('screens.gestionti-ebs-requisiciones.manage'));
    }
}
