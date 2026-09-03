<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una `EbsRequisition`, solo poblada por el "método"
 * `requisition_header_line` de la API de EBS (`requisition_header_approved`
 * no trae líneas). "EbsRequisitionLine" -> "ebs_requisition_lines" es
 * pluralización regular en inglés, no requiere `$table` explícito.
 */
class EbsRequisitionLine extends Model
{
    protected $fillable = [
        'ebs_requisition_id',
        'requisition_line_id',
        'line_number',
        'line_type_id',
        'category_id',
        'item_id',
        'item_description',
        'unit_measurement',
        'unit_price',
        'quantity',
        'currency_code',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
    ];

    public function ebsRequisition(): BelongsTo
    {
        return $this->belongsTo(EbsRequisition::class);
    }
}
