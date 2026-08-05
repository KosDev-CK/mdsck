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

    public function test_it_exposes_total_read_and_unread_counts(): void
    {
        $admin = User::factory()->create();

        $admin->notify(new AccountLockedNotification(User::factory()->create()));
        $admin->notify(new AccountLockedNotification(User::factory()->create()));

        $component = Livewire::actingAs($admin)
            ->test(Bell::class)
            ->assertSet('totalCount', 2)
            ->assertSet('unreadCount', 2)
            ->assertSet('readCount', 0);

        $notificationId = $admin->notifications()->first()->id;

        $component->call('markAsRead', $notificationId)
            ->assertSet('totalCount', 2)
            ->assertSet('unreadCount', 1)
            ->assertSet('readCount', 1);
    }

    public function test_a_read_notification_can_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->notify(new AccountLockedNotification(User::factory()->create()));

        $notificationId = $admin->notifications()->first()->id;

        Livewire::actingAs($admin)
            ->test(Bell::class)
            ->call('markAsRead', $notificationId)
            ->call('deleteNotification', $notificationId)
            ->assertSet('totalCount', 0);

        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
    }

    public function test_an_unread_notification_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->notify(new AccountLockedNotification(User::factory()->create()));

        $notificationId = $admin->notifications()->first()->id;

        Livewire::actingAs($admin)
            ->test(Bell::class)
            ->call('deleteNotification', $notificationId)
            ->assertSet('totalCount', 1);

        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
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
