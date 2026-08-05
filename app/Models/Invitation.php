<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class Invitation extends Model
{
    protected $fillable = [
        'name',
        'email',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'invitation_role');
    }

    /**
     * The account created when this invitation was accepted, matched by
     * email since acceptance doesn't store a foreign key back (see
     * AcceptInvitation::accept()).
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'email', 'email');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * Generate a new random invitation token and its deterministic lookup hash.
     * The raw token is only ever handed to the invitee via email; only the
     * hash is persisted (same approach Sanctum uses for API tokens).
     *
     * @return array{0: string, 1: string} [$rawToken, $hash]
     */
    public static function generateToken(): array
    {
        $raw = Str::random(48);

        return [$raw, static::hashToken($raw)];
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function findByToken(string $rawToken): ?self
    {
        return static::where('token_hash', static::hashToken($rawToken))->first();
    }
}
