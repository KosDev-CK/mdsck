<?php

namespace App\Livewire\Auth;

use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Services\LoginSecurityManager;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class VerifyLoginCode extends Component
{
    public string $code = '';

    protected function rules(): array
    {
        return [
            'code' => ['required', 'digits:'.config('security.login_code_length')],
        ];
    }

    public function verifyCode(LoginSecurityManager $security)
    {
        $this->validate();

        $genericError = 'Código inválido o expirado.';

        $user = $this->pendingUser();

        if (! $user) {
            $this->addError('code', $genericError);

            return;
        }

        if ($user->isLocked()) {
            $this->addError('code', 'Esta cuenta está bloqueada temporalmente. Intenta más tarde.');

            return;
        }

        $loginCode = LoginCode::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $loginCode || ! $loginCode->isValid() || ! $loginCode->matches($this->code)) {
            $security->recordFailure($user, request());
            $this->addError('code', $genericError);

            return;
        }

        $loginCode->update(['consumed_at' => now()]);

        if ($user->hasTwoFactorEnabled()) {
            session(['login.two_factor_user_id' => $user->id]);
            session()->forget('login.user_id');

            return redirect()->route('login.two-factor');
        }

        $security->completeLogin($user, request());

        return redirect()->route('dashboard');
    }

    public function resend()
    {
        $user = $this->pendingUser();

        if ($user && $user->is_active && ! $user->isLocked()) {
            $code = (string) random_int(100000, 999999);

            LoginCode::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            LoginCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(config('security.login_code_ttl_minutes')),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $user->notify(new LoginCodeNotification($code));
        }

        session()->flash('status', 'Si el correo existe, se envió un nuevo código.');
    }

    protected function pendingUser(): ?User
    {
        $userId = session('login.user_id');

        return $userId ? User::find($userId) : null;
    }

    public function render()
    {
        return view('livewire.auth.verify-login-code');
    }
}
