<?php

namespace App\Livewire\Invitations;

use App\Models\Invitation;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class Manage extends Component
{
    use WithPagination;

    public string $name = '';

    public string $email = '';

    public array $roleIds = [];

    public ?int $revokingTwoFactorUserId = null;

    public string $adminTwoFactorCode = '';

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

    public function toggleActive(int $userId)
    {
        if ($userId === auth()->id()) {
            $this->addError('toggleActive', 'No puedes desactivar tu propia cuenta.');

            return;
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        SecurityEvent::log(
            $user->is_active ? SecurityEvent::USER_REACTIVATED : SecurityEvent::USER_DEACTIVATED,
            request(),
            auth()->user(),
            meta: ['target_user_id' => $user->id, 'target_email' => $user->email]
        );

        session()->flash('status', $user->is_active
            ? $user->name.' fue reactivado.'
            : $user->name.' fue desactivado.');
    }

    public function startRevokingTwoFactor(int $userId)
    {
        $this->revokingTwoFactorUserId = $userId;
        $this->adminTwoFactorCode = '';
        $this->resetErrorBag();
    }

    public function cancelRevokingTwoFactor()
    {
        $this->revokingTwoFactorUserId = null;
        $this->adminTwoFactorCode = '';
        $this->resetErrorBag();
    }

    public function confirmRevokeTwoFactor()
    {
        $admin = auth()->user();
        $target = User::find($this->revokingTwoFactorUserId);

        if (! $target) {
            $this->cancelRevokingTwoFactor();

            return;
        }

        if ($admin->hasTwoFactorEnabled()) {
            $this->validate(['adminTwoFactorCode' => ['required', 'digits:6']]);

            if (! (new Google2FA)->verifyKey($admin->two_factor_secret, $this->adminTwoFactorCode)) {
                $this->addError('adminTwoFactorCode', 'Código inválido.');

                return;
            }
        }

        $target->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        SecurityEvent::log(
            SecurityEvent::TWO_FACTOR_REVOKED_BY_ADMIN,
            request(),
            $admin,
            meta: ['target_user_id' => $target->id, 'target_email' => $target->email]
        );

        session()->flash('status', 'Se revocó el 2FA de '.$target->name.'.');

        $this->cancelRevokingTwoFactor();
    }

    public function render()
    {
        return view('livewire.invitations.manage', [
            'invitations' => Invitation::with(['invitedBy', 'roles', 'user'])->latest()->paginate(10),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
