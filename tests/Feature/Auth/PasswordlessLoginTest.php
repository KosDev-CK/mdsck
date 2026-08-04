<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\RequestLoginCode;
use App\Livewire\Auth\VerifyLoginCode;
use App\Livewire\Auth\VerifyTwoFactor;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_code_creates_a_login_code_and_redirects_to_verify(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_active' => true]);

        Livewire::test(RequestLoginCode::class)
            ->set('email', $user->email)
            ->call('sendCode')
            ->assertRedirect(route('login.verify'));

        $this->assertDatabaseCount('login_codes', 1);
        Notification::assertSentTo($user, \App\Notifications\LoginCodeNotification::class);
    }

    public function test_requesting_a_code_for_an_unknown_email_shows_an_error_and_creates_no_code(): void
    {
        Livewire::test(RequestLoginCode::class)
            ->set('email', 'nobody@example.com')
            ->call('sendCode')
            ->assertHasErrors('email')
            ->assertNoRedirect();

        $this->assertDatabaseCount('login_codes', 0);
    }

    public function test_requesting_a_code_for_a_deactivated_account_shows_an_error(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        Livewire::test(RequestLoginCode::class)
            ->set('email', $user->email)
            ->call('sendCode')
            ->assertHasErrors('email')
            ->assertNoRedirect();

        $this->assertDatabaseCount('login_codes', 0);
    }

    public function test_requesting_a_code_for_a_locked_account_shows_an_error(): void
    {
        $user = User::factory()->create(['is_active' => true, 'locked_until' => now()->addMinutes(5)]);

        Livewire::test(RequestLoginCode::class)
            ->set('email', $user->email)
            ->call('sendCode')
            ->assertHasErrors('email')
            ->assertNoRedirect();

        $this->assertDatabaseCount('login_codes', 0);
    }

    public function test_correct_code_logs_the_user_in(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        LoginCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        session(['login.user_id' => $user->id]);

        Livewire::test(VerifyLoginCode::class)
            ->set('code', '123456')
            ->call('verifyCode')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->current_session_id);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_code_does_not_log_in_and_records_a_failed_attempt(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        LoginCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        session(['login.user_id' => $user->id]);

        Livewire::test(VerifyLoginCode::class)
            ->set('code', '000000')
            ->call('verifyCode')
            ->assertHasErrors('code');

        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->failed_login_attempts);
    }

    public function test_five_failed_attempts_locks_the_account_for_five_minutes(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            LoginCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10),
            ]);

            session(['login.user_id' => $user->id]);

            Livewire::test(VerifyLoginCode::class)
                ->set('code', '000000')
                ->call('verifyCode');
        }

        $user->refresh();

        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertSame(1, $user->lockout_cycles);
        $this->assertTrue($user->isLocked());
        $this->assertTrue($user->locked_until->diffInMinutes(now()->addMinutes(5)) < 1);
    }

    public function test_three_lockout_cycles_escalate_to_a_24_hour_lock_and_notify_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Administrador', 'web'));

        $user = User::factory()->create(['is_active' => true]);

        for ($cycle = 0; $cycle < 3; $cycle++) {
            // Force-clear the short lockout from the previous cycle so the next
            // 5 attempts aren't rejected outright. Must run against a freshly
            // loaded model, otherwise Eloquent sees `locked_until` as already
            // null in its stale in-memory "original" state and skips the update.
            $user->fresh()->forceFill(['locked_until' => null])->save();

            for ($i = 0; $i < 5; $i++) {
                LoginCode::create([
                    'user_id' => $user->id,
                    'code_hash' => Hash::make('123456'),
                    'expires_at' => now()->addMinutes(10),
                ]);

                session(['login.user_id' => $user->id]);

                Livewire::test(VerifyLoginCode::class)
                    ->set('code', '000000')
                    ->call('verifyCode');
            }
        }

        $user->refresh();

        $this->assertSame(0, $user->lockout_cycles);
        $this->assertTrue($user->locked_until->diffInHours(now()->addHours(24)) < 1);

        Notification::assertSentTo($admin, \App\Notifications\AccountLockedNotification::class);
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_the_two_factor_challenge(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
        ]);

        LoginCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        session(['login.user_id' => $user->id]);

        Livewire::test(VerifyLoginCode::class)
            ->set('code', '123456')
            ->call('verifyCode')
            ->assertRedirect(route('login.two-factor'));

        $this->assertGuest();

        $valid = (new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp('ABCDEFGHIJKLMNOP');

        Livewire::test(VerifyTwoFactor::class)
            ->set('code', $valid)
            ->call('verify')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_valid_recovery_code_logs_the_user_in_and_is_consumed(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['AAAAAAAAAA', 'BBBBBBBBBB'],
        ]);

        session(['login.two_factor_user_id' => $user->id]);

        Livewire::test(VerifyTwoFactor::class)
            ->call('toggleRecoveryCode')
            ->set('recoveryCode', 'aaaaaaaaaa')
            ->call('verifyWithRecoveryCode')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['BBBBBBBBBB'], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_a_used_recovery_code_cannot_be_reused(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['BBBBBBBBBB'],
        ]);

        session(['login.two_factor_user_id' => $user->id]);

        Livewire::test(VerifyTwoFactor::class)
            ->call('toggleRecoveryCode')
            ->set('recoveryCode', 'AAAAAAAAAA')
            ->call('verifyWithRecoveryCode')
            ->assertHasErrors('recoveryCode');

        $this->assertGuest();
        $this->assertSame(['BBBBBBBBBB'], $user->fresh()->two_factor_recovery_codes);
    }
}
