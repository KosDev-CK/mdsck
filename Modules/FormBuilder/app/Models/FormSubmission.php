<?php

namespace Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSubmission extends Model
{
    protected $fillable = [
        'form_id',
        'ticket_form_link_id',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function ticketFormLink(): BelongsTo
    {
        return $this->belongsTo(TicketFormLink::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FormAnswer::class, 'submission_id');
    }
}
