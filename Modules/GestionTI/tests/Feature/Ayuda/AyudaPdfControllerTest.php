<?php

namespace Modules\GestionTI\Tests\Feature\Ayuda;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AyudaPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('gestionti.ayuda.pdf', 'tickets'))->assertRedirect(route('login'));
    }

    /**
     * Las 21 pantallas del módulo con contenido de ayuda — un archivo por
     * slug en Modules/GestionTI/resources/ayuda/data/. Si se agrega una
     * pantalla nueva, agregar su slug aquí también.
     */
    public function test_any_authenticated_user_can_download_the_pdf_for_a_known_screen(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $slugs = [
            'dashboard', 'busqueda-global', 'catalogos-nucleo', 'catalogos-empleados',
            'catalogos-compras', 'catalogos-inventario', 'tickets', 'solicitudes-sic',
            'ebs-requisiciones', 'solicitudes-proveedor', 'recepciones', 'asignaciones',
            'presupuestos-proyecto', 'facturas', 'stock', 'registro-manual',
            'mantenimientos', 'ficha-activo', 'tipos-aviso', 'avisos-historial',
            'almacenamiento-documentos',
        ];

        foreach ($slugs as $slug) {
            $response = $this->actingAs($user)->get(route('gestionti.ayuda.pdf', $slug));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'));
            $response->assertDownload("ayuda-{$slug}.pdf");
        }
    }

    public function test_an_unknown_screen_slug_returns_404(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('gestionti.ayuda.pdf', 'no-existe'))
            ->assertNotFound();
    }

    public function test_a_path_traversal_slug_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/gestionti/ayuda/..%2F..%2F..%2F..%2Fenv/pdf')
            ->assertNotFound();
    }
}
