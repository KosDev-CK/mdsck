<?php

namespace App\Concerns;

use App\Models\SecurityEvent;
use Illuminate\Support\Facades\RateLimiter;

trait GuardsAgainstFlooding
{
    /**
     * IP-based flood guard, independent of the per-account lockout in
     * LoginSecurityManager — covers attackers who spread attempts across
     * many accounts, or who don't have a valid account at all.
     */
    protected function tooManyRequests(string $action): bool
    {
        $key = $action.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, config('security.max_requests_per_minute'))) {
            SecurityEvent::log(SecurityEvent::RATE_LIMITED, request(), null, null, ['action' => $action]);

            return true;
        }

        RateLimiter::hit($key, config('security.request_throttle_decay_seconds'));

        return false;
    }
}
