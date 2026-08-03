<?php

namespace App\Livewire\Auth;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\LoginSecurityManager;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

#[Layout('layouts.guest')]
class VerifyTwoFactor extends Component
{
    public string $code = '';

    public function mount()
    {
        if (! session('login.two_factor_user_id')) {
            return redirect()->route('login');
        }
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }

    public function verify(LoginSecurityManager $security)
    {
        $this->validate();

        $userId = session('login.two_factor_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isLocked()) {
            $this->addError('code', 'Esta cuenta está bloqueada temporalmente. Intenta más tarde.');

            return;
        }

        $valid = (new Google2FA)->verifyKey($user->two_factor_secret, $this->code);

        if (! $valid) {
            $security->recordFailure($user, request());
            SecurityEvent::log(SecurityEvent::TWO_FACTOR_FAILED, request(), $user);
            $this->addError('code', 'Código inválido.');

            return;
        }

        session()->forget('login.two_factor_user_id');
        $security->completeLogin($user, request());

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.verify-two-factor');
    }
}
