<?php

namespace Tests\Feature\SecurityLog;

use App\Livewire\SecurityLog\Index;
use App\Models\Screen;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Bitácora de seguridad',
            'slug' => 'security-log',
            'route_name' => 'security-log.index',
            'permission_name' => 'screens.security.view',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_only_authorized_users_can_view_the_log(): void
    {
        $this->actingAdmin();

        $this->get(route('security-log.index'))->assertRedirect(route('login'));

        $plainUser = User::factory()->create(['is_active' => true]);
        $this->actingAs($plainUser)->get(route('security-log.index'))->assertForbidden();
    }

    public function test_it_lists_events_and_filters_by_type(): void
    {
        $admin = $this->actingAdmin();
        $loginUser = User::factory()->create(['name' => 'Ana López']);
        $lockedUser = User::factory()->create(['name' => 'Beto Cruz']);

        SecurityEvent::log(SecurityEvent::LOGIN_SUCCESS, request(), $loginUser);
        SecurityEvent::log(SecurityEvent::LOCKED_LONG, request(), $lockedUser);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSee('Ana López')
            ->assertSee('Beto Cruz')
            ->set('eventType', SecurityEvent::LOCKED_LONG)
            ->assertSee('Beto Cruz')
            ->assertDontSee('Ana López');
    }

    public function test_it_filters_by_search_term(): void
    {
        $admin = $this->actingAdmin();

        SecurityEvent::log(SecurityEvent::LOGIN_FAILED, request(), null, 'intruso@example.com');
        SecurityEvent::log(SecurityEvent::LOGIN_SUCCESS, request(), User::factory()->create(['name' => 'Carlos Ruiz']));

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('search', 'intruso')
            ->assertSee('intruso@example.com')
            ->assertDontSee('Carlos Ruiz');
    }
}
