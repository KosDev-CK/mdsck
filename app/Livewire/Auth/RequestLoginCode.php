<?php

namespace App\Livewire\Auth;

use App\Concerns\GuardsAgainstFlooding;
use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class RequestLoginCode extends Component
{
    use GuardsAgainstFlooding;

    public string $email = '';

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function sendCode()
    {
        if ($this->tooManyRequests('login.send-code')) {
            $this->addError('email', 'Demasiadas solicitudes desde tu conexión. Intenta de nuevo en unos minutos.');

            return;
        }

        $this->validate();

        $user = User::where('email', $this->email)->first();

        if (! $user) {
            $this->addError('email', 'Este correo no tiene acceso al sistema.');

            return;
        }

        if (! $user->is_active) {
            $this->addError('email', 'Esta cuenta está desactivada. Contacta al administrador.');

            return;
        }

        if ($user->isLocked()) {
            $this->addError('email', 'Esta cuenta está bloqueada temporalmente. Intenta más tarde.');

            return;
        }

        $code = $this->generateNumericCode(config('security.login_code_length'));

        LoginCode::where('user_id', $user->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        LoginCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('security.login_code_ttl_minutes')),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $user->notify(new LoginCodeNotification($code));

        session(['login.user_id' => $user->id]);

        return redirect()->route('login.verify');
    }

    protected function generateNumericCode(int $length): string
    {
        $min = (int) str_repeat('1', 1).str_repeat('0', $length - 1);
        $max = (int) str_repeat('9', $length);

        return (string) random_int($min, $max);
    }

    public function render()
    {
        return view('livewire.auth.request-login-code');
    }
}
