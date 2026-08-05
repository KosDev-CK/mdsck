<?php

namespace App\Livewire\Messages;

use App\Models\User;
use App\Notifications\AdminMessageNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Send extends Component
{
    public string $subject = '';

    public string $body = '';

    public string $search = '';

    public bool $sendToAll = false;

    public array $recipientIds = [];

    protected function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function send()
    {
        $this->validate();

        $recipients = $this->sendToAll
            ? User::where('is_active', true)->get()
            : User::whereIn('id', $this->recipientIds)->where('is_active', true)->get();

        if ($recipients->isEmpty()) {
            $this->addError('recipientIds', 'Selecciona al menos un destinatario.');

            return;
        }

        Notification::send($recipients, new AdminMessageNotification($this->subject, $this->body, auth()->user()));

        session()->flash('status', 'Mensaje enviado a '.$recipients->count().' usuario(s).');

        $this->reset(['subject', 'body', 'recipientIds', 'sendToAll']);
    }

    public function render()
    {
        return view('livewire.messages.send', [
            'users' => User::where('is_active', true)
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
