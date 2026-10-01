<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mapeo EBS -> Artículo estándar (Fase 5) — un `item_id` real de Oracle EBS
 * (`EbsRequisitionLine.item_id`) se asocia, una sola vez, a un artículo
 * genérico ya existente en el catálogo (`ArticuloSolicitud`, ej. "Laptop
 * Ejecutiva", no la marca/modelo real que en verdad llega del proveedor —
 * eso se resuelve después, en Recepción de Proveedor). `EbsRequisitionSyncService`
 * crea la fila sin mapear la primera vez que ve un `item_id` nuevo; un
 * humano completa `articulo_id` desde el tab "Artículos EBS" de
 * `Catalogos\Compras`. Ver docs/gestionti-progreso.md.
 */
class EbsArticulo extends Model
{
    protected $table = 'ebs_articulos';

    protected $fillable = [
        'ebs_item_id',
        'ebs_item_description',
        'articulo_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function articulo()
    {
        return $this->belongsTo(ArticuloSolicitud::class, 'articulo_id');
    }
}
