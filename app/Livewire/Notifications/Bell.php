<?php

namespace App\Livewire\Notifications;

use Livewire\Attributes\On;
use Livewire\Component;

class Bell extends Component
{
    public int $unreadCount = 0;

    public int $totalCount = 0;

    public int $readCount = 0;

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

    public function deleteNotification(string $id)
    {
        auth()->user()->notifications()->where('id', $id)->whereNotNull('read_at')->delete();
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
        $this->totalCount = auth()->user()->notifications()->count();
        $this->readCount = $this->totalCount - $this->unreadCount;
    }

    public function render()
    {
        return view('livewire.notifications.bell', [
            'notifications' => auth()->user()->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
