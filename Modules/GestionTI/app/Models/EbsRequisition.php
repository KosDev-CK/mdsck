<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Réplica local de una requisición (SIC) de Oracle EBS. Ver
 * `Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService` y
 * docs/gestionti-progreso.md para el diseño completo de la sincronización.
 *
 * "EbsRequisition" -> "ebs_requisitions" es pluralización regular en
 * inglés, no requiere `$table` explícito (mismo criterio ya documentado
 * para `Invoice`).
 */
class EbsRequisition extends Model
{
    protected $fillable = [
        'requisition_header_id',
        'code',
        'description',
        'status',
        'fecha_creacion',
        'wf_item_key',
        'wf_item_type',
        'organization_code',
        'organization_description',
        'created_by_user',
        'created_by_description',
        'sequence_num',
        'approver_user',
        'approver_name',
        'approver_date',
        'action_code',
        'action_date',
        'ultima_sincronizacion_creadas_at',
        'ultima_sincronizacion_aprobadas_at',
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'approver_date' => 'datetime',
        'action_date' => 'datetime',
        'ultima_sincronizacion_creadas_at' => 'datetime',
        'ultima_sincronizacion_aprobadas_at' => 'datetime',
        'sequence_num' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(EbsRequisitionLine::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EbsRequisitionNote::class);
    }

    /**
     * La FK real vive en `solicitudes_sic_borrador.ebs_requisition_id`, no
     * aquí — `hasOne` inverso.
     */
    public function solicitudSicBorrador(): HasOne
    {
        return $this->hasOne(SolicitudSicBorrador::class, 'ebs_requisition_id');
    }

    /**
     * Búsqueda general usada por la pantalla "SIC en EBS" (input "Buscar
     * por código...") y su export a Excel — deben producir exactamente el
     * mismo conjunto de resultados, así que ambos llaman este scope en vez
     * de repetir la condición por su cuenta. Busca por coincidencia parcial
     * en el código, la descripción, quién creó/autorizó la requisición y el
     * contenido de sus notas.
     */
    public function scopeMatchesSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('created_by_user', 'like', "%{$term}%")
                ->orWhere('created_by_description', 'like', "%{$term}%")
                ->orWhere('approver_user', 'like', "%{$term}%")
                ->orWhere('approver_name', 'like', "%{$term}%")
                ->orWhereHas('notes', fn ($q2) => $q2->where('valor', 'like', "%{$term}%"));
        });
    }
}
