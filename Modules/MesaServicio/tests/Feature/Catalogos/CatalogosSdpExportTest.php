<?php

namespace Modules\MesaServicio\Tests\Feature\Catalogos;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\MesaServicio\Models\SdpCatalogEntry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogosSdpExportTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $screen = Screen::create([
            'module' => 'MesaServicio',
            'group_label' => 'Mesa de Servicio',
            'name' => 'Catálogos SDP',
            'slug' => 'mesaservicio-catalogos-sdp',
            'route_name' => 'mesaservicio.catalogos-sdp.index',
            'permission_name' => 'screens.mesaservicio-catalogos-sdp.manage',
            'icon' => 'book-open',
            'order' => 5,
        ]);

        $role = Role::findOrCreate('Rol Catalogos SDP export test', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Descarga el export de una pestaña y devuelve la hoja como matriz
     * (primera fila = encabezados).
     *
     * @return list<list<string|null>>
     */
    private function descargar(string $tab): array
    {
        $response = $this->get('/mesa-servicio/catalogos-sdp/exportar?tab='.$tab);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $ruta = tempnam(sys_get_temp_dir(), 'sdpexp').'.xlsx';
        file_put_contents($ruta, $response->streamedContent());

        try {
            return IOFactory::load($ruta)->getActiveSheet()->toArray();
        } finally {
            @unlink($ruta);
        }
    }

    public function test_export_requires_the_screen_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/mesa-servicio/catalogos-sdp/exportar?tab=categories')->assertForbidden();
    }

    public function test_unknown_tab_returns_404(): void
    {
        $this->actingAs($this->actingUser())->get('/mesa-servicio/catalogos-sdp/exportar?tab=no-existe')->assertNotFound();
    }

    public function test_every_catalog_tab_exports_an_xlsx(): void
    {
        $this->actingAs($this->actingUser());

        foreach (SdpCatalogEntry::todos() as $tab) {
            $hoja = $this->descargar($tab);

            $this->assertNotEmpty($hoja[0], "El catálogo {$tab} no exportó encabezados.");
        }
    }

    public function test_flat_catalog_exports_id_name_color_and_status(): void
    {
        SdpCatalogEntry::create(['catalogo' => 'priorities', 'sdp_id' => 'p1', 'nombre' => 'Urgente', 'color' => '#ff0000', 'activo' => true]);
        SdpCatalogEntry::create(['catalogo' => 'priorities', 'sdp_id' => 'p2', 'nombre' => 'Baja', 'activo' => false]);
        SdpCatalogEntry::create(['catalogo' => 'modes', 'sdp_id' => 'm1', 'nombre' => 'E-Mail', 'activo' => true]);

        $this->actingAs($this->actingUser());

        $hoja = $this->descargar('priorities');

        $this->assertSame(['ID SDP', 'Nombre', 'Descripción', 'Color', 'Estatus'], $hoja[0]);
        $this->assertCount(3, $hoja, 'Encabezado + solo las 2 prioridades, sin mezclar otros catálogos.');
        $this->assertSame(['p2', 'Baja', null, null, 'Inactivo'], $hoja[1]);
        $this->assertSame(['p1', 'Urgente', null, '#ff0000', 'Activo'], $hoja[2]);
    }

    public function test_items_export_has_category_and_subcategory_in_separate_columns(): void
    {
        SdpCatalogEntry::create([
            'catalogo' => 'items', 'sdp_id' => 'i1', 'nombre' => 'ABC', 'activo' => true,
            'extra' => ['category_nombre' => 'Libra', 'subcategory_nombre' => 'Accesos al Sistema'],
        ]);

        $this->actingAs($this->actingUser());

        $hoja = $this->descargar('items');

        $this->assertSame(['ID SDP', 'Categoría', 'Subcategoría', 'Artículo', 'Descripción', 'Estatus'], $hoja[0]);
        $this->assertSame(['i1', 'Libra', 'Accesos al Sistema', 'ABC', null, 'Activo'], $hoja[1]);
    }

    public function test_priority_matrix_export_unpacks_urgency_impact_and_priority(): void
    {
        SdpCatalogEntry::create([
            'catalogo' => 'priority_matrices', 'sdp_id' => md5('u1|i1'), 'nombre' => 'Alta + Alto → Urgente',
            'color' => '#ff0000', 'activo' => true,
            'extra' => ['urgency' => ['name' => 'Alta'], 'impact' => ['name' => 'Alto'], 'priority' => ['name' => 'Urgente']],
        ]);

        $this->actingAs($this->actingUser());

        $hoja = $this->descargar('priority_matrices');

        $this->assertSame(['Urgencia', 'Impacto', 'Prioridad resultante', 'Color'], $hoja[0]);
        $this->assertSame(['Alta', 'Alto', 'Urgente', '#ff0000'], $hoja[1]);
    }

    public function test_sla_export_has_minutes_criteria_and_calendar_columns(): void
    {
        SdpCatalogEntry::create([
            'catalogo' => 'slas', 'sdp_id' => 'sla-1', 'nombre' => 'High SLA', 'activo' => false,
            'extra' => [
                'sitio' => 'Base Site', 'orden' => 1, 'es_servicio' => false,
                'respuesta_minutos' => null, 'resolucion_minutos' => 60,
                'criterios_texto' => 'Prioridad es Alta', 'ignora_fines_de_semana' => true,
            ],
        ]);

        $this->actingAs($this->actingUser());

        $hoja = $this->descargar('slas');

        $this->assertSame('Primera respuesta (min)', $hoja[0][6]);
        $this->assertSame(
            ['sla-1', 'High SLA', null, 'Base Site', '1', 'No', null, '60', 'Prioridad es Alta', 'Sí', 'No', 'No', 'Inactivo'],
            $hoja[1]
        );
    }

    public function test_export_includes_every_record_of_the_tab_not_just_one_page(): void
    {
        foreach (range(1, 60) as $n) {
            SdpCatalogEntry::create(['catalogo' => 'items', 'sdp_id' => "i{$n}", 'nombre' => "Artículo {$n}", 'activo' => true]);
        }

        $this->actingAs($this->actingUser());

        $this->assertCount(61, $this->descargar('items'), 'Encabezado + los 60 artículos.');
    }

    public function test_values_starting_with_equals_are_not_treated_as_formulas(): void
    {
        SdpCatalogEntry::create(['catalogo' => 'modes', 'sdp_id' => 'm1', 'nombre' => '=1+1', 'activo' => true]);

        $this->actingAs($this->actingUser());

        $hoja = $this->descargar('modes');

        $this->assertSame('=1+1', $hoja[1][1]);
    }
}
