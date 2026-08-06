<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'invited_by',
        'company',
        'cedis',
        'area',
        'employee_number',
        'location',
        'home_screen_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'current_session_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invitation_accepted_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function homeScreen(): BelongsTo
    {
        return $this->belongsTo(Screen::class, 'home_screen_id');
    }

    /**
     * Name of the route to land on after login. Falls back to "dashboard"
     * if no preference was set, or if the chosen screen became inactive,
     * lost its route, or the user no longer has permission for it.
     */
    public function homeRouteName(): string
    {
        $screen = $this->homeScreen;

        if ($screen
            && $screen->is_active
            && Route::has($screen->route_name)
            && $this->can($screen->permission_name)
        ) {
            return $screen->route_name;
        }

        return 'dashboard';
    }
}
