<?php

namespace Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FormField extends Model
{
    public const CHOICE_TYPES = ['single_choice', 'multiple_choice'];

    public const DISPLAY_ONLY_TYPES = ['label'];

    /**
     * Field types a repeater's sub-fields may use — no nested label,
     * timestamp, or repeater, to keep the data model flat.
     */
    public const REPEATER_CHILD_TYPES = [
        'short_text', 'long_text', 'number', 'date', 'email',
        'single_choice', 'multiple_choice', 'checkbox',
    ];

    public const TYPES = [
        'short_text' => 'Texto corto',
        'long_text' => 'Texto largo',
        'number' => 'Número',
        'date' => 'Fecha',
        'email' => 'Correo',
        'single_choice' => 'Selección única',
        'multiple_choice' => 'Selección múltiple',
        'checkbox' => 'Casilla de verificación',
        'label' => 'Etiqueta de texto (instrucciones o encabezado)',
        'timestamp' => 'Fecha y hora automática',
        'repeater' => 'Tabla',
    ];

    protected $fillable = [
        'form_id',
        'parent_field_id',
        'type',
        'label',
        'help_text',
        'field_key',
        'options',
        'is_required',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FormField $field) {
            $field->field_key ??= static::uniqueKeyFor($field->form_id, $field->label);
        });
    }

    protected static function uniqueKeyFor(int $formId, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'campo';
        $key = $base;
        $i = 1;

        while (static::where('form_id', $formId)->where('field_key', $key)->exists()) {
            $key = "{$base}_{$i}";
            $i++;
        }

        return $key;
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FormAnswer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'parent_field_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FormField::class, 'parent_field_id')->orderBy('order');
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    public function isDisplayOnly(): bool
    {
        return in_array($this->type, self::DISPLAY_ONLY_TYPES, true);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Human-readable rendering of a raw answer value for this field,
     * resolving option value(s) back to their labels for choice-type
     * fields. Shared by FormAnswer::displayValue() and the read-only
     * ticket-link detail screen.
     */
    public function formatValue(mixed $value): string
    {
        if ($this->type === 'checkbox') {
            return $value ? 'Sí' : 'No';
        }

        if ($this->type === 'repeater') {
            return count((array) $value).' fila(s) capturada(s)';
        }

        if ($this->hasOptions()) {
            $options = collect($this->options ?? [])->keyBy('value');
            $values = is_array($value) ? $value : [$value];

            return collect($values)
                ->map(fn ($v) => $options->get($v)['label'] ?? $v)
                ->implode(', ');
        }

        return is_array($value) ? implode(', ', $value) : (string) $value;
    }
}
