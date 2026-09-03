<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Par clave/valor de una `EbsRequisition`, solo poblado por el "método"
 * `requisition_header_approved` de la API de EBS. Las claves NO están
 * estandarizadas por Oracle — se guardan tal cual vienen (`clave`/`valor`),
 * sin intentar mapearlas a columnas fijas. "EbsRequisitionNote" ->
 * "ebs_requisition_notes" es pluralización regular en inglés, no requiere
 * `$table` explícito.
 */
class EbsRequisitionNote extends Model
{
    protected $fillable = [
        'ebs_requisition_id',
        'clave',
        'valor',
    ];

    public function ebsRequisition(): BelongsTo
    {
        return $this->belongsTo(EbsRequisition::class);
    }
}
