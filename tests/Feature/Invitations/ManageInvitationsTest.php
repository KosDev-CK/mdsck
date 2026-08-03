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
}
