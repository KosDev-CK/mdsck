<?php

namespace App\Livewire\Invitations;

use App\Models\Invitation;
use App\Models\SecurityEvent;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Manage extends Component
{
    use WithPagination;

    public string $name = '';

    public string $email = '';

    public array $roleIds = [];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'roleIds' => ['required', 'array', 'min:1'],
        ];
    }

    public function send()
    {
        $this->validate();

        [$rawToken, $hash] = Invitation::generateToken();

        $invitation = Invitation::create([
            'name' => $this->name,
            'email' => $this->email,
            'token_hash' => $hash,
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(config('security.invitation_ttl_days')),
        ]);

        $invitation->roles()->sync($this->roleIds);

        Notification::route('mail', $this->email)
            ->notify(new UserInvitationNotification($invitation, $rawToken));

        SecurityEvent::log(SecurityEvent::INVITATION_SENT, request(), auth()->user(), $this->email);

        $this->reset(['name', 'email', 'roleIds']);
        session()->flash('status', 'Invitación enviada.');
    }

    public function revoke(int $invitationId)
    {
        $invitation = Invitation::find($invitationId);

        if ($invitation && $invitation->isPending()) {
            $invitation->update(['revoked_at' => now()]);
        }
    }

    public function resend(int $invitationId)
    {
        $invitation = Invitation::find($invitationId);

        if (! $invitation || ! $invitation->isPending()) {
            return;
        }

        [$rawToken, $hash] = Invitation::generateToken();
        $invitation->update(['token_hash' => $hash, 'expires_at' => now()->addDays(config('security.invitation_ttl_days'))]);

        Notification::route('mail', $invitation->email)
            ->notify(new UserInvitationNotification($invitation, $rawToken));

        session()->flash('status', 'Invitación reenviada.');
    }

    public function render()
    {
        return view('livewire.invitations.manage', [
            'invitations' => Invitation::with(['invitedBy', 'roles'])->latest()->paginate(10),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
