<?php

namespace App\Livewire\Profile;

use App\Models\SecurityEvent;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use PragmaRX\Google2FA\Google2FA;

#[Layout('layouts.app')]
class Show extends Component
{
    public string $name = '';

    public string $company = '';

    public string $cedis = '';

    public string $area = '';

    public string $employeeNumber = '';

    public string $location = '';

    public bool $enablingTwoFactor = false;

    public string $pendingSecret = '';

    public string $confirmationCode = '';

    public string $disableCode = '';

    public ?array $recoveryCodes = null;

    public function mount()
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->company = $user->company ?? '';
        $this->cedis = $user->cedis ?? '';
        $this->area = $user->area ?? '';
        $this->employeeNumber = $user->employee_number ?? '';
        $this->location = $user->location ?? '';
    }

    public function updateName()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'cedis' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'employeeNumber' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        auth()->user()->update([
            'name' => $this->name,
            'company' => $this->company ?: null,
            'cedis' => $this->cedis ?: null,
            'area' => $this->area ?: null,
            'employee_number' => $this->employeeNumber ?: null,
            'location' => $this->location ?: null,
        ]);

        session()->flash('status', 'Perfil actualizado.');
    }

    public function startEnablingTwoFactor()
    {
        $this->pendingSecret = (new Google2FA)->generateSecretKey();
        $this->enablingTwoFactor = true;
        $this->confirmationCode = '';
    }

    public function cancelEnablingTwoFactor()
    {
        $this->enablingTwoFactor = false;
        $this->pendingSecret = '';
        $this->confirmationCode = '';
    }

    public function confirmTwoFactor()
    {
        $this->validate(['confirmationCode' => ['required', 'digits:6']]);

        $valid = (new Google2FA)->verifyKey($this->pendingSecret, $this->confirmationCode);

        if (! $valid) {
            $this->addError('confirmationCode', 'Código inválido.');

            return;
        }

        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(10)))->values()->all();

        auth()->user()->forceFill([
            'two_factor_secret' => $this->pendingSecret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        SecurityEvent::log(SecurityEvent::TWO_FACTOR_ENABLED, request(), auth()->user());

        $this->recoveryCodes = $codes;
        $this->enablingTwoFactor = false;
        $this->pendingSecret = '';
        $this->confirmationCode = '';
    }

    public function downloadRecoveryCodes()
    {
        if (! $this->recoveryCodes) {
            return;
        }

        $content = implode(PHP_EOL, $this->recoveryCodes).PHP_EOL;

        return response()->streamDownload(
            fn () => print $content,
            'mds-codigos-recuperacion.txt'
        );
    }

    public function disableTwoFactor()
    {
        $this->validate(['disableCode' => ['required', 'digits:6']]);

        $valid = (new Google2FA)->verifyKey(auth()->user()->two_factor_secret, $this->disableCode);

        if (! $valid) {
            $this->addError('disableCode', 'Código inválido.');

            return;
        }

        auth()->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        SecurityEvent::log(SecurityEvent::TWO_FACTOR_DISABLED, request(), auth()->user());

        $this->disableCode = '';
    }

    public function getQrCodeSvgProperty(): ?string
    {
        if (! $this->pendingSecret) {
            return null;
        }

        $otpAuthUrl = (new Google2FA)->getQRCodeUrl(
            config('app.name'),
            auth()->user()->email,
            $this->pendingSecret
        );

        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($otpAuthUrl);
    }

    public function render()
    {
        return view('livewire.profile.show');
    }
}
