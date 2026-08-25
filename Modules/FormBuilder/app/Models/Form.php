<?php

namespace Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Form extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            $form->slug ??= static::uniqueSlugFrom($form->name);
        });
    }

    protected static function uniqueSlugFrom(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->whereNull('parent_field_id')->orderBy('order')->with('children');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function ticketFormLinks(): HasMany
    {
        return $this->hasMany(TicketFormLink::class);
    }

    public function scopeWherePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
