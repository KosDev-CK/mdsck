<?php

namespace Tests\Feature\Layout;

use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_header_links_to_the_users_home_screen(): void
    {
        $this->seed(\Database\Seeders\CoreSeeder::class);

        $admin = User::where('email', 'victor.gonzalez@landit.com.mx')->first();
        $connections = Screen::where('slug', 'connections')->first();
        $admin->update(['home_screen_id' => $connections->id]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeInOrder(['href="'.route('connections.index').'"', 'title="Ir a mi pantalla de inicio"'], false);
    }

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

        // The "collapsed" (icon-only) state must only ever hide screen labels
        // at the lg+ breakpoint via a scoped `lg:hidden` class binding on the
        // label span itself — never via a bare x-show, otherwise it also
        // hides labels in the mobile off-canvas drawer (which shares the same
        // Alpine state through localStorage). Regression guard for that bug.
        $this->assertMatchesRegularExpression(
            '/<span :class="\{ \'lg:hidden\': collapsed \}" class="truncate">Dashboard<\/span>/',
            $html
        );
    }
}
