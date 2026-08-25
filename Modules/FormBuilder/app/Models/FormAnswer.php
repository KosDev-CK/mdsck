<?php

namespace Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormAnswer extends Model
{
    protected $fillable = [
        'submission_id',
        'form_field_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    /**
     * Human-readable rendering of the stored value, resolving option
     * value(s) back to their labels for choice-type fields.
     */
    public function displayValue(): string
    {
        if (! $this->field) {
            return is_array($this->value) ? implode(', ', $this->value) : (string) $this->value;
        }

        return $this->field->formatValue($this->value);
    }
}
