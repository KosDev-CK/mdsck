<?php

namespace Modules\MesaServicio\Tests\Feature\Ayuda;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AyudaPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('mesaservicio.ayuda.pdf', 'dashboard'))->assertRedirect(route('login'));
    }

    /**
     * Las 5 pantallas del módulo con contenido de ayuda — un archivo por
     * slug en Modules/MesaServicio/resources/ayuda/data/. Si se agrega una
     * pantalla nueva, agregar su slug aquí también.
     */
    public function test_any_authenticated_user_can_download_the_pdf_for_a_known_screen(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $slugs = [
            'dashboard', 'tecnicos', 'destinatarios', 'reportes', 'ficha-tecnico',
        ];

        foreach ($slugs as $slug) {
            $response = $this->actingAs($user)->get(route('mesaservicio.ayuda.pdf', $slug));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('content-type'));
            $response->assertDownload("ayuda-{$slug}.pdf");
        }
    }

    public function test_an_unknown_screen_slug_returns_404(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('mesaservicio.ayuda.pdf', 'no-existe'))
            ->assertNotFound();
    }

    public function test_a_path_traversal_slug_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/mesa-servicio/ayuda/..%2F..%2F..%2F..%2Fenv/pdf')
            ->assertNotFound();
    }
}
