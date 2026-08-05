<?php

namespace Tests\Feature\Console;

use App\Models\BrandingPreset;
use App\Models\DatabaseConnection;
use App\Models\Invitation;
use App\Models\SecurityEvent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CleanTestDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_only_the_configured_admin_and_wipes_the_rest(): void
    {
        config(['mds.admin_email' => 'admin@example.com']);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $testRole = Role::findOrCreate('test', 'web');

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(5),
            'current_session_id' => 'abc123',
        ]);
        $admin->assignRole($adminRole);

        $testUser = User::factory()->create();
        $testUser->assignRole($testRole);

        Invitation::create([
            'name' => 'Prueba',
            'email' => 'prueba@example.com',
            'token_hash' => hash('sha256', 'token'),
            'invited_by' => $admin->id,
            'expires_at' => now()->addDay(),
        ]);

        SecurityEvent::log(SecurityEvent::LOGIN_SUCCESS, request(), $admin);
        $admin->notify(new AccountLockedNotification($testUser));

        $this->artisan('mds:clean-test-data', ['--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('users', ['id' => $testUser->id]);
        $this->assertDatabaseCount('invitations', 0);
        $this->assertDatabaseCount('security_events', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseMissing('roles', ['name' => 'test']);

        $admin->refresh();
        $this->assertSame(0, $admin->failed_login_attempts);
        $this->assertNull($admin->locked_until);
        $this->assertNull($admin->current_session_id);
        $this->assertTrue($admin->hasRole('Administrador'));
    }

    public function test_it_always_wipes_database_connections(): void
    {
        config(['mds.admin_email' => 'admin@example.com']);

        User::factory()->create(['email' => 'admin@example.com']);

        DatabaseConnection::create([
            'name' => 'Conexión de prueba',
            'key' => 'test-conn',
            'driver' => 'mysql',
            'mode' => 'single',
        ]);

        $this->artisan('mds:clean-test-data', ['--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseCount('database_connections', 0);
    }

    public function test_it_resets_branding_to_default_and_keeps_only_system_presets(): void
    {
        config(['mds.admin_email' => 'admin@example.com']);

        User::factory()->create(['email' => 'admin@example.com']);

        SiteSetting::current()->update(['primary_color' => '#F36522']);

        BrandingPreset::create([
            'name' => 'Preset de prueba',
            'is_system' => false,
            ...array_fill_keys(BrandingPreset::COLOR_FIELDS, '#000000'),
        ]);

        BrandingPreset::create([
            'name' => 'Predeterminado',
            'is_system' => true,
            ...array_fill_keys(BrandingPreset::COLOR_FIELDS, '#111111'),
        ]);

        $this->artisan('mds:clean-test-data', ['--force' => true])
            ->assertExitCode(0);

        $this->assertSame(SiteSetting::DEFAULTS['primary_color'], SiteSetting::current()->primary_color);
        $this->assertDatabaseMissing('branding_presets', ['name' => 'Preset de prueba']);
        $this->assertDatabaseHas('branding_presets', ['name' => 'Predeterminado', 'is_system' => true]);
    }

    public function test_it_fails_when_the_keep_email_does_not_exist(): void
    {
        config(['mds.admin_email' => 'admin@example.com']);

        $this->artisan('mds:clean-test-data', ['--force' => true])
            ->assertExitCode(1);
    }
}
