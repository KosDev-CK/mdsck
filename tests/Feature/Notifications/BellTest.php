<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Notifications\Bell;
use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BellTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_unread_count_and_marks_notifications_as_read(): void
    {
        $admin = User::factory()->create();
        $lockedUser = User::factory()->create();

        $admin->notify(new AccountLockedNotification($lockedUser));

        $component = Livewire::actingAs($admin)
            ->test(Bell::class)
            ->assertSet('unreadCount', 1);

        $notificationId = $admin->notifications()->first()->id;

        $component->call('markAsRead', $notificationId)
            ->assertSet('unreadCount', 0);
    }

    public function test_mark_all_as_read_clears_the_unread_count(): void
    {
        $admin = User::factory()->create();

        $admin->notify(new AccountLockedNotification(User::factory()->create()));
        $admin->notify(new AccountLockedNotification(User::factory()->create()));

        Livewire::actingAs($admin)
            ->test(Bell::class)
            ->assertSet('unreadCount', 2)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);
    }

    public function test_the_notification_broadcasts_on_the_users_private_channel(): void
    {
        $admin = User::factory()->create();
        $lockedUser = User::factory()->create();

        $notification = new AccountLockedNotification($lockedUser);
        $event = new \Illuminate\Notifications\Events\BroadcastNotificationCreated(
            $admin,
            $notification,
            $notification->toArray($admin)
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertSame('private-App.Models.User.'.$admin->id, $channels[0]->name);
    }
}
