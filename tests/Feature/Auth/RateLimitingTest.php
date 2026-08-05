<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Auth\RequestLoginCode;
use App\Livewire\Auth\VerifyLoginCode;
use App\Livewire\Auth\VerifyTwoFactor;
use App\Models\Invitation;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.max_requests_per_minute' => 2]);
    }

    public function test_requesting_a_login_code_is_throttled_per_ip(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_active' => true]);

        Livewire::test(RequestLoginCode::class)->set('email', $user->email)->call('sendCode');
        Livewire::test(RequestLoginCode::class)->set('email', $user->email)->call('sendCode');

        $this->assertDatabaseCount('login_codes', 2);

        Livewire::test(RequestLoginCode::class)
            ->set('email', $user->email)
            ->call('sendCode')
            ->assertHasErrors('email');

        $this->assertDatabaseCount('login_codes', 2);
        $this->assertDatabaseHas('security_events', ['event_type' => SecurityEvent::RATE_LIMITED]);
    }

    public function test_verifying_a_login_code_is_throttled_per_ip(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        session(['login.user_id' => $user->id]);

        Livewire::test(VerifyLoginCode::class)->set('code', '000000')->call('verifyCode');
        Livewire::test(VerifyLoginCode::class)->set('code', '000000')->call('verifyCode');

        $user->refresh();
        $this->assertSame(2, $user->failed_login_attempts);

        Livewire::test(VerifyLoginCode::class)
            ->set('code', '000000')
            ->call('verifyCode')
            ->assertHasErrors('code');

        $user->refresh();
        $this->assertSame(2, $user->failed_login_attempts, 'a throttled attempt must not reach the account lockout logic');
    }

    public function test_verifying_two_factor_is_throttled_per_ip(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->create(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        session(['login.two_factor_user_id' => $user->id]);

        Livewire::test(VerifyTwoFactor::class)->set('code', '000000')->call('verify');
        Livewire::test(VerifyTwoFactor::class)->set('code', '000000')->call('verify');

        $user->refresh();
        $this->assertSame(2, $user->failed_login_attempts);

        Livewire::test(VerifyTwoFactor::class)
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors('code');

        $user->refresh();
        $this->assertSame(2, $user->failed_login_attempts, 'a throttled attempt must not reach the account lockout logic');
    }

    public function test_accepting_an_invitation_is_throttled_per_ip(): void
    {
        $inviter = User::factory()->create();

        $makeInvitation = function () use ($inviter) {
            [$rawToken, $hash] = Invitation::generateToken();

            $invitation = Invitation::create([
                'name' => 'Prueba',
                'email' => 'prueba-'.uniqid().'@example.com',
                'token_hash' => $hash,
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDay(),
            ]);

            return [$rawToken, $invitation];
        };

        [$firstToken] = $makeInvitation();
        [$secondToken] = $makeInvitation();
        [$thirdToken] = $makeInvitation();

        Livewire::test(AcceptInvitation::class, ['token' => $firstToken])->call('accept');
        Livewire::test(AcceptInvitation::class, ['token' => $secondToken])->call('accept');

        Livewire::test(AcceptInvitation::class, ['token' => $thirdToken])
            ->call('accept')
            ->assertSet('status', 'rate_limited');
    }
}
