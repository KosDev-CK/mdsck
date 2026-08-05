<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    protected $fillable = [
        'logo_path',
        'favicon_path',
        'primary_color',
        'success_color',
        'danger_color',
        'warning_color',
        'info_color',
        'topbar_color',
        'sidebar_header_color',
        'sidebar_body_color',
    ];

    public const DEFAULTS = [
        'primary_color' => '#4F46E5',
        'success_color' => '#059669',
        'danger_color' => '#DC2626',
        'warning_color' => '#D97706',
        'info_color' => '#2563EB',
        'topbar_color' => '#FFFFFF',
        'sidebar_header_color' => '#111827',
        'sidebar_body_color' => '#111827',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], self::DEFAULTS);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }
}
