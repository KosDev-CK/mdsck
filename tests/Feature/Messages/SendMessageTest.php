<?php

namespace Tests\Feature\Messages;

use App\Livewire\Messages\Send;
use App\Models\Screen;
use App\Models\User;
use App\Notifications\AdminMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $screen = Screen::create([
            'name' => 'Mensajes',
            'slug' => 'messages',
            'route_name' => 'messages.index',
            'permission_name' => 'screens.messages.manage',
            'order' => 1,
        ]);

        $adminRole = Role::findOrCreate('Administrador', 'web');
        $adminRole->givePermissionTo($screen->permission_name);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($adminRole);

        return $admin;
    }

    public function test_an_admin_can_send_a_message_to_selected_users(): void
    {
        Notification::fake();

        $admin = $this->actingAdmin();
        $recipient = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(Send::class)
            ->set('subject', 'Aviso importante')
            ->set('body', 'Revisen el nuevo procedimiento.')
            ->set('recipientIds', [$recipient->id])
            ->call('send')
            ->assertHasNoErrors();

        Notification::assertSentTo($recipient, AdminMessageNotification::class);
        Notification::assertNotSentTo($other, AdminMessageNotification::class);
    }

    public function test_an_admin_can_send_a_message_to_all_active_users(): void
    {
        Notification::fake();

        $admin = $this->actingAdmin();
        $active = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        Livewire::actingAs($admin)
            ->test(Send::class)
            ->set('subject', 'Aviso general')
            ->set('body', 'Para todos.')
            ->set('sendToAll', true)
            ->call('send')
            ->assertHasNoErrors();

        Notification::assertSentTo($active, AdminMessageNotification::class);
        Notification::assertNotSentTo($inactive, AdminMessageNotification::class);
    }

    public function test_sending_without_recipients_shows_an_error(): void
    {
        $admin = $this->actingAdmin();

        Livewire::actingAs($admin)
            ->test(Send::class)
            ->set('subject', 'Aviso')
            ->set('body', 'Contenido')
            ->call('send')
            ->assertHasErrors('recipientIds');
    }

    public function test_guests_and_unauthorized_users_cannot_reach_the_screen(): void
    {
        $this->actingAdmin();

        $this->get(route('messages.index'))->assertRedirect(route('login'));

        $plainUser = User::factory()->create(['is_active' => true]);
        $this->actingAs($plainUser)->get(route('messages.index'))->assertForbidden();
    }
}
