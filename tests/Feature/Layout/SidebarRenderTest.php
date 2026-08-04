<?php

namespace Tests\Feature\Layout;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_renders_the_grouped_sidebar_with_icons(): void
    {
        $this->seed(\Database\Seeders\CoreSeeder::class);

        $admin = User::where('email', 'victor.gonzalez@landit.com.mx')->first();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Accesos');
        $response->assertSee('Sistema');
        $response->assertSee('Conexiones a BD');
        $response->assertSeeInOrder(['<svg', 'Dashboard'], false);

        $html = $response->getContent();

        // Collapsible sidebar state + mobile off-canvas drawer wiring.
        $this->assertStringContainsString('sidebarOpen', $html);
        $this->assertStringContainsString('collapsed', $html);
        $this->assertStringContainsString('toggleCollapsed', $html);
        $this->assertStringContainsString('localStorage', $html);

        // Responsive breakpoints present on the shell (mobile drawer, lg+ static sidebar).
        $this->assertStringContainsString('lg:static', $html);
        $this->assertStringContainsString('lg:hidden', $html);
        $this->assertStringContainsString('lg:w-64', $html);
        $this->assertStringContainsString('lg:w-20', $html);
        $this->assertStringContainsString('max-w-screen-2xl', $html);
    }
}
