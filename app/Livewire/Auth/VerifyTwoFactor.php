<?php

namespace App\Livewire\Auth;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\LoginSecurityManager;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

#[Layout('layouts.guest')]
class VerifyTwoFactor extends Component
{
    public string $code = '';

    public bool $usingRecoveryCode = false;

    public string $recoveryCode = '';

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

    public function toggleRecoveryCode()
    {
        $this->usingRecoveryCode = ! $this->usingRecoveryCode;
        $this->code = '';
        $this->recoveryCode = '';
        $this->resetErrorBag();
    }

    protected function currentLoginUser(): ?User
    {
        $userId = session('login.two_factor_user_id');

        return $userId ? User::find($userId) : null;
    }

    public function verify(LoginSecurityManager $security)
    {
        $this->validate();

        $user = $this->currentLoginUser();

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

    public function verifyWithRecoveryCode(LoginSecurityManager $security)
    {
        $this->validate(['recoveryCode' => ['required', 'string']]);

        $user = $this->currentLoginUser();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isLocked()) {
            $this->addError('recoveryCode', 'Esta cuenta está bloqueada temporalmente. Intenta más tarde.');

            return;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $submitted = Str::upper(trim($this->recoveryCode));
        $matchedIndex = array_search($submitted, $codes, true);

        if ($matchedIndex === false) {
            $security->recordFailure($user, request());
            SecurityEvent::log(SecurityEvent::TWO_FACTOR_FAILED, request(), $user);
            $this->addError('recoveryCode', 'Código de recuperación inválido.');

            return;
        }

        unset($codes[$matchedIndex]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        SecurityEvent::log(SecurityEvent::TWO_FACTOR_RECOVERY_USED, request(), $user);

        session()->forget('login.two_factor_user_id');
        $security->completeLogin($user, request());

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.verify-two-factor');
    }
}
