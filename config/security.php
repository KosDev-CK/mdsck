<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login attempt lockout policy
    |--------------------------------------------------------------------------
    |
    | 5 failed attempts (wrong OTP code or wrong 2FA code) trigger a short
    | lockout. If that happens 3 times in a row, the account is locked for
    | 24 hours and every administrator is notified.
    |
    */

    'max_failed_attempts' => (int) env('SECURITY_MAX_FAILED_ATTEMPTS', 5),

    'short_lockout_minutes' => (int) env('SECURITY_SHORT_LOCKOUT_MINUTES', 5),

    'cycles_before_long_lockout' => (int) env('SECURITY_CYCLES_BEFORE_LONG_LOCKOUT', 3),

    'long_lockout_hours' => (int) env('SECURITY_LONG_LOCKOUT_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Passwordless login code
    |--------------------------------------------------------------------------
    */

    'login_code_ttl_minutes' => (int) env('SECURITY_LOGIN_CODE_TTL_MINUTES', 10),

    'login_code_length' => (int) env('SECURITY_LOGIN_CODE_LENGTH', 6),

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */

    'invitation_ttl_days' => (int) env('SECURITY_INVITATION_TTL_DAYS', 7),

];
