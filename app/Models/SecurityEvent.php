<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class SecurityEvent extends Model
{
    public const LOGIN_SUCCESS = 'login_success';

    public const LOGIN_FAILED = 'login_failed';

    public const LOCKED_SHORT = 'locked_short';

    public const LOCKED_LONG = 'locked_long';

    public const TWO_FACTOR_FAILED = 'two_factor_failed';

    public const LOGOUT = 'logout';

    public const SESSION_REVOKED = 'session_revoked';

    public const INVITATION_SENT = 'invitation_sent';

    public const INVITATION_ACCEPTED = 'invitation_accepted';

    protected $fillable = [
        'user_id',
        'email',
        'event_type',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(string $eventType, ?Request $request = null, ?User $user = null, ?string $email = null, array $meta = []): self
    {
        return static::create([
            'user_id' => $user?->id,
            'email' => $email ?? $user?->email,
            'event_type' => $eventType,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'meta' => $meta,
        ]);
    }
}
