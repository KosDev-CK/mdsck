<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission;

class Screen extends Model
{
    protected $fillable = [
        'module',
        'group_label',
        'name',
        'slug',
        'route_name',
        'permission_name',
        'icon',
        'parent_id',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Screen $screen) {
            Permission::findOrCreate($screen->permission_name, 'web');
        });

        static::deleting(function (Screen $screen) {
            Permission::where('name', $screen->permission_name)->where('guard_name', 'web')->delete();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Screen::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Screen::class, 'parent_id')->orderBy('order');
    }

    public function permission(): Permission
    {
        return Permission::where('name', $this->permission_name)->where('guard_name', 'web')->firstOrFail();
    }
}
