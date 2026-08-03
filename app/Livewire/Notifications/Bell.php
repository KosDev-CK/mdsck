<?php

namespace App\Livewire\Notifications;

use Livewire\Attributes\On;
use Livewire\Component;

class Bell extends Component
{
    public int $unreadCount = 0;

    public function mount()
    {
        $this->refreshCount();
    }

    public function markAsRead(string $id)
    {
        auth()->user()->notifications()->where('id', $id)->first()?->markAsRead();
        $this->refreshCount();
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->refreshCount();
    }

    #[On('notification-received')]
    public function refresh()
    {
        $this->refreshCount();
    }

    protected function refreshCount(): void
    {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.notifications.bell', [
            'notifications' => auth()->user()->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
