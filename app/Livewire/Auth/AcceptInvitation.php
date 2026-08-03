<?php

namespace App\Livewire\Auth;

use App\Models\Invitation;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\LoginSecurityManager;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class AcceptInvitation extends Component
{
    public string $token;

    public ?Invitation $invitation = null;

    public string $status = 'invalid';

    public function mount(string $token)
    {
        $this->token = $token;
        $this->invitation = Invitation::findByToken($token);

        $this->status = match (true) {
            ! $this->invitation => 'invalid',
            $this->invitation->isAccepted() => 'accepted',
            $this->invitation->isRevoked() => 'revoked',
            $this->invitation->isExpired() => 'expired',
            default => 'pending',
        };
    }

    public function accept(LoginSecurityManager $security)
    {
        if ($this->status !== 'pending' || ! $this->invitation) {
            return;
        }

        $user = User::firstOrNew(['email' => $this->invitation->email]);
        $user->name = $this->invitation->name;
        $user->is_active = true;
        $user->invited_by = $this->invitation->invited_by;
        $user->invitation_accepted_at = now();
        $user->save();

        $user->syncRoles($this->invitation->roles->pluck('name'));

        $this->invitation->update(['accepted_at' => now()]);

        SecurityEvent::log(SecurityEvent::INVITATION_ACCEPTED, request(), $user);

        $security->completeLogin($user, request());

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.accept-invitation');
    }
}
