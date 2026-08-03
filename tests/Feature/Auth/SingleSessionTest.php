<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_session_that_no_longer_matches_the_users_current_session_is_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        // Simulate the user having logged in elsewhere: their "current" session
        // id in the DB no longer matches the one this request is carrying.
        $user->forceFill(['current_session_id' => 'some-other-session-id'])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_request_carrying_the_current_session_id_is_left_alone(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->withSession([])->get('/dashboard');

        // On this request the session id won't have been persisted to the user
        // yet (that only happens during LoginSecurityManager::completeLogin),
        // so current_session_id is null and the middleware must not interfere.
        $response->assertOk();
        $this->assertAuthenticated();
    }

    public function test_a_deactivated_user_is_logged_out_on_their_next_request(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_session_lifetime_matches_the_three_hour_idle_timeout_requirement(): void
    {
        $this->assertSame(180, (int) config('session.lifetime'));
    }
}
