<?php

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;

class LoginSecurityManager
{
    /**
     * Establish the authenticated session after every verification step
     * (email code, and 2FA when enabled) has passed.
     *
     * Uses the Session facade rather than $request->session() because
     * Livewire component tests invoke component methods without the request
     * object carrying a bound session store.
     */
    public function completeLogin(User $user, Request $request): void
    {
        $this->resetCounters($user);

        Auth::login($user);
        Session::regenerate();

        $user->forceFill([
            'current_session_id' => Session::getId(),
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        SecurityEvent::log(SecurityEvent::LOGIN_SUCCESS, $request, $user);

        Session::forget(['login.user_id', 'login.two_factor_user_id']);
    }

    public function isLocked(User $user): bool
    {
        return $user->isLocked();
    }

    public function lockedForHumans(User $user): ?string
    {
        if (! $this->isLocked($user)) {
            return null;
        }

        return $user->locked_until->diffForHumans(now(), true);
    }

    /**
     * Record a failed OTP/2FA verification attempt and escalate the lockout
     * when the configured thresholds are crossed.
     */
    public function recordFailure(User $user, Request $request): void
    {
        $user->increment('failed_login_attempts');
        $user->refresh();

        SecurityEvent::log(SecurityEvent::LOGIN_FAILED, $request, $user);

        if ($user->failed_login_attempts < config('security.max_failed_attempts')) {
            return;
        }

        $user->forceFill(['failed_login_attempts' => 0])->save();
        $user->increment('lockout_cycles');
        $user->refresh();

        if ($user->lockout_cycles >= config('security.cycles_before_long_lockout')) {
            $user->forceFill([
                'lockout_cycles' => 0,
                'locked_until' => now()->addHours(config('security.long_lockout_hours')),
            ])->save();

            SecurityEvent::log(SecurityEvent::LOCKED_LONG, $request, $user);

            $this->notifyAdministrators($user);

            return;
        }

        $user->forceFill([
            'locked_until' => now()->addMinutes(config('security.short_lockout_minutes')),
        ])->save();

        SecurityEvent::log(SecurityEvent::LOCKED_SHORT, $request, $user);
    }

    /**
     * Clear all counters after a fully successful login.
     */
    public function resetCounters(User $user): void
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'lockout_cycles' => 0,
            'locked_until' => null,
        ])->save();
    }

    protected function notifyAdministrators(User $lockedUser): void
    {
        $admins = User::role('Administrador')->where('id', '!=', $lockedUser->id)->get();

        Notification::send($admins, new AccountLockedNotification($lockedUser));
    }
}
