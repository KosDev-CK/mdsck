<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class LoginCode extends Model
{
    protected $fillable = [
        'user_id',
        'code_hash',
        'expires_at',
        'consumed_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    public function matches(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->code_hash);
    }
}
