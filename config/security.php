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

    /*
    |--------------------------------------------------------------------------
    | Límite de solicitudes por IP (anti fuerza bruta / flood)
    |--------------------------------------------------------------------------
    |
    | Independiente del bloqueo por cuenta de arriba: limita, por IP, cuántas
    | veces se puede llamar a una acción sensible (pedir código, verificar
    | código/2FA, aceptar invitación) en la ventana de tiempo definida. Cubre
    | el caso de un atacante que reparte los intentos entre muchas cuentas o
    | dispara solicitudes sin siquiera tener una cuenta válida.
    |
    */

    'max_requests_per_minute' => (int) env('SECURITY_MAX_REQUESTS_PER_MINUTE', 50),

    'request_throttle_decay_seconds' => (int) env('SECURITY_REQUEST_THROTTLE_DECAY_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Reverse proxy de confianza
    |--------------------------------------------------------------------------
    |
    | IP(s) del proxy que termina el SSL en frente de este servidor (ver
    | docs/deploy-lemp.md, sección 6.1). Necesario para que request()->ip()
    | vea al cliente real (no al proxy) y request()->isSecure() detecte
    | https vía X-Forwarded-Proto. Lista separada por comas en .env.
    |
    */

    'trusted_proxies' => array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))),

];
