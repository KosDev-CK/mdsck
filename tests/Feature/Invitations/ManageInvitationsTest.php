<?php

namespace Tests\Feature\Invitations;

use App\Livewire\Invitations\Manage;
use App\Models\Invitation;
use App\Models\Screen;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Configuración de acceso',
            'slug' => 'invitations',
            'route_name' => 'invitations.index',
            'permission_name' => 'screens.invitations.manage',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_an_admin_can_send_an_invitation_with_selected_profiles(): void
    {
        Notification::fake();

        $admin = $this->actingAdmin();
        $role = Role::findOrCreate('Editor', 'web');

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->set('name', 'Nuevo Colaborador')
            ->set('email', 'colaborador@example.com')
            ->set('roleIds', [$role->id])
            ->call('send')
            ->assertHasNoErrors();

        $invitation = Invitation::where('email', 'colaborador@example.com')->first();

        $this->assertNotNull($invitation);
        $this->assertTrue($invitation->roles->contains($role));
        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_revoking_a_pending_invitation_marks_it_revoked(): void
    {
        $admin = $this->actingAdmin();

        [, $hash] = Invitation::generateToken();
        $invitation = Invitation::create([
            'name' => 'Pendiente',
            'email' => 'pendiente@example.com',
            'token_hash' => $hash,
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('revoke', $invitation->id);

        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_the_screen_shows_account_controls_for_an_accepted_invitation(): void
    {
        $admin = $this->actingAdmin();
        $user = User::factory()->create(['email' => 'ya-aceptado@example.com', 'is_active' => true]);

        [, $hash] = Invitation::generateToken();
        Invitation::create([
            'name' => $user->name,
            'email' => $user->email,
            'token_hash' => $hash,
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->assertSee('Activo')
            ->assertSee('Desactivar');
    }

    public function test_an_admin_can_deactivate_and_reactivate_an_invited_user(): void
    {
        $admin = $this->actingAdmin();
        $user = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('toggleActive', $user->id);

        $this->assertFalse($user->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('toggleActive', $user->id);

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('toggleActive', $admin->id);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_an_admin_without_2fa_can_revoke_a_users_2fa_without_a_code(): void
    {
        $admin = $this->actingAdmin();
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['AAAAAAAAAA'],
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('startRevokingTwoFactor', $user->id)
            ->call('confirmRevokeTwoFactor')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_an_admin_with_2fa_must_confirm_with_their_own_valid_code(): void
    {
        $adminSecret = (new Google2FA)->generateSecretKey();
        $admin = $this->actingAdmin();
        $admin->forceFill([
            'two_factor_secret' => $adminSecret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $userSecret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->create([
            'two_factor_secret' => $userSecret,
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('startRevokingTwoFactor', $user->id)
            ->set('adminTwoFactorCode', '000000')
            ->call('confirmRevokeTwoFactor')
            ->assertHasErrors('adminTwoFactorCode');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $validCode = (new Google2FA)->getCurrentOtp($adminSecret);

        Livewire::actingAs($admin)
            ->test(Manage::class)
            ->call('startRevokingTwoFactor', $user->id)
            ->set('adminTwoFactorCode', $validCode)
            ->call('confirmRevokeTwoFactor')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
