<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\AcceptInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcceptInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pending_invitation_can_be_accepted_and_logs_the_user_in(): void
    {
        $inviter = User::factory()->create();
        $role = Role::findOrCreate('Perfil Demo', 'web');

        [$rawToken, $hash] = Invitation::generateToken();

        $invitation = Invitation::create([
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'token_hash' => $hash,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        $invitation->roles()->attach($role);

        Livewire::test(AcceptInvitation::class, ['token' => $rawToken])
            ->assertSet('status', 'pending')
            ->call('accept')
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'nuevo@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('Perfil Demo'));
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $inviter = User::factory()->create();

        [$rawToken, $hash] = Invitation::generateToken();

        Invitation::create([
            'name' => 'Nuevo Usuario',
            'email' => 'expirado@example.com',
            'token_hash' => $hash,
            'invited_by' => $inviter->id,
            'expires_at' => now()->subDay(),
        ]);

        Livewire::test(AcceptInvitation::class, ['token' => $rawToken])
            ->assertSet('status', 'expired');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'expirado@example.com']);
    }

    public function test_an_unknown_token_shows_invalid_state(): void
    {
        Livewire::test(AcceptInvitation::class, ['token' => 'not-a-real-token'])
            ->assertSet('status', 'invalid');
    }
}
