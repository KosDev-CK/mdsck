<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandingPreset extends Model
{
    protected $fillable = [
        'name',
        'primary_color',
        'success_color',
        'danger_color',
        'warning_color',
        'info_color',
        'topbar_color',
        'sidebar_header_color',
        'sidebar_body_color',
        'is_system',
    ];

    public const COLOR_FIELDS = [
        'primary_color',
        'success_color',
        'danger_color',
        'warning_color',
        'info_color',
        'topbar_color',
        'sidebar_header_color',
        'sidebar_body_color',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
