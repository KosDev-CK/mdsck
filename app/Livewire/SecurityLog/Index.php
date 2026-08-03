<?php

namespace App\Livewire\SecurityLog;

use App\Models\SecurityEvent;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $eventType = '';

    public string $from = '';

    public string $to = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedEventType()
    {
        $this->resetPage();
    }

    public function updatedFrom()
    {
        $this->resetPage();
    }

    public function updatedTo()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'eventType', 'from', 'to']);
    }

    public function render()
    {
        $events = SecurityEvent::query()
            ->with('user')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('email', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->eventType, fn ($q) => $q->where('event_type', $this->eventType))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest()
            ->paginate(20);

        return view('livewire.security-log.index', [
            'events' => $events,
            'eventTypes' => [
                SecurityEvent::LOGIN_SUCCESS => 'Ingreso exitoso',
                SecurityEvent::LOGIN_FAILED => 'Ingreso fallido',
                SecurityEvent::LOCKED_SHORT => 'Bloqueo 5 min',
                SecurityEvent::LOCKED_LONG => 'Bloqueo 24 h',
                SecurityEvent::TWO_FACTOR_FAILED => '2FA fallido',
                SecurityEvent::TWO_FACTOR_ENABLED => '2FA activado',
                SecurityEvent::TWO_FACTOR_DISABLED => '2FA desactivado',
                SecurityEvent::LOGOUT => 'Cierre de sesión',
                SecurityEvent::SESSION_REVOKED => 'Sesión revocada',
                SecurityEvent::INVITATION_SENT => 'Invitación enviada',
                SecurityEvent::INVITATION_ACCEPTED => 'Invitación aceptada',
            ],
        ]);
    }
}
