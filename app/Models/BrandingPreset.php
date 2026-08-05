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
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
