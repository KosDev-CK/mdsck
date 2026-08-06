<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\VerifyLoginCode;
use App\Models\LoginCode;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomeScreenRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function makeScreen(): Screen
    {
        return Screen::create([
            'name' => 'Conexiones a BD',
            'slug' => 'connections',
            'route_name' => 'connections.index',
            'permission_name' => 'screens.connections.manage',
            'order' => 1,
        ]);
    }

    public function test_default_home_route_is_dashboard(): void
    {
        $user = User::factory()->create();

        $this->assertSame('dashboard', $user->homeRouteName());
    }

    public function test_home_route_uses_the_configured_screen_when_permitted(): void
    {
        $screen = $this->makeScreen();
        $role = Role::findOrCreate('Conexiones', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['home_screen_id' => $screen->id]);
        $user->assignRole($role);

        $this->assertSame('connections.index', $user->homeRouteName());
    }

    public function test_home_route_falls_back_to_dashboard_without_permission(): void
    {
        $screen = $this->makeScreen();

        $user = User::factory()->create(['home_screen_id' => $screen->id]);

        $this->assertSame('dashboard', $user->homeRouteName());
    }

    public function test_home_route_falls_back_to_dashboard_when_the_screen_is_inactive(): void
    {
        $screen = $this->makeScreen();
        $screen->update(['is_active' => false]);

        $role = Role::findOrCreate('Conexiones', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['home_screen_id' => $screen->id]);
        $user->assignRole($role);

        $this->assertSame('dashboard', $user->homeRouteName());
    }

    public function test_logging_in_redirects_to_the_configured_home_screen(): void
    {
        $screen = $this->makeScreen();
        $role = Role::findOrCreate('Conexiones', 'web');
        $role->givePermissionTo($screen->permission_name);

        $user = User::factory()->create(['is_active' => true, 'home_screen_id' => $screen->id]);
        $user->assignRole($role);

        $code = '123456';

        LoginCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        session(['login.user_id' => $user->id]);

        Livewire::test(VerifyLoginCode::class)
            ->set('code', $code)
            ->call('verifyCode')
            ->assertRedirect(route('connections.index'));
    }
}
