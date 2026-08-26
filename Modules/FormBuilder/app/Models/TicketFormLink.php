<?php

namespace Modules\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TicketFormLink extends Model
{
    protected $fillable = [
        'form_id',
        'ticket_number',
        'recipient_email',
        'token_hash',
        'expires_at',
        'used_at',
        'failed_verify_attempts',
        'locked_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(FormSubmission::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isUsed() && ! $this->isLocked() && ! $this->isExpired();
    }

    /**
     * Whether the given internal user may view this link's detail/PDF/print
     * screens — its creator, or any Administrador. Shared by Links\Show and
     * the print controller so the rule can't drift between them.
     */
    public function viewableBy(User $user): bool
    {
        return $this->created_by === $user->id || $user->hasRole('Administrador');
    }

    /**
     * Single source of truth for the link's state, used both by the public
     * fill screen and by the internal "Mis Formularios" list/detail screens
     * — status must never be derived twice, independently, in two places.
     */
    public function status(): string
    {
        return match (true) {
            $this->isUsed() => 'used',
            $this->isLocked() => 'locked',
            $this->isExpired() => 'expired',
            default => 'pending',
        };
    }

    /**
     * Generate a new random ticket-link token and its deterministic lookup
     * hash. The raw token is only ever handed to the recipient via email;
     * only the hash is persisted (same approach as Invitation::generateToken()).
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
