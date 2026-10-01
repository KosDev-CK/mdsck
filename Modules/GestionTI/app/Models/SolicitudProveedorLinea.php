<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudProveedorLinea extends Model
{
    protected $table = 'solicitud_proveedor_lineas';

    protected $fillable = [
        'solicitud_id',
        'articulo_id',
        'sic_id',
        'folio_sic_manual',
        'ebs_requisition_id',
        'descripcion_libre',
        'cantidad_solicitada',
        'cantidad_recibida',
        'precio_unitario_cotizado',
        'es_activo_inventariable',
        'detalle_adicional',
        'observaciones_especificaciones',
    ];

    protected $casts = [
        'es_activo_inventariable' => 'boolean',
        'detalle_adicional' => 'array',
        'precio_unitario_cotizado' => 'decimal:2',
    ];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudProveedor::class, 'solicitud_id');
    }

    public function articulo()
    {
        return $this->belongsTo(ArticuloSolicitud::class, 'articulo_id');
    }

    /**
     * La SIC real que originó esta línea (una entre 1 a N por solicitud,
     * ver `Compras\SolicitudesProveedor`) — opcional, `null` cuando la línea
     * viene de un artículo de Proyecto de Presupuesto o de una captura
     * manual (`folio_sic_manual`, sin registro real de SIC).
     */
    public function sic()
    {
        return $this->belongsTo(SolicitudSicBorrador::class, 'sic_id');
    }

    /**
     * Tercer origen de línea (mutuamente excluyente con `sic_id`/
     * `folio_sic_manual`, ver arriba) — una requisición de EBS que nunca
     * tuvo match de SIC local, resuelta solo por el mapeo `EbsArticulo`.
     * Reemplaza al diseño anterior que fabricaba una `SolicitudSicBorrador`
     * "esqueleto" solo para tener algo a qué apuntar — ver
     * docs/gestionti-progreso.md, entrada del rediseño.
     */
    public function ebsRequisition()
    {
        return $this->belongsTo(EbsRequisition::class, 'ebs_requisition_id');
    }

    public function recepcionLineas()
    {
        return $this->hasMany(RecepcionLinea::class, 'solicitud_proveedor_linea_id');
    }

    /**
     * Número de SIC a mostrar en el PDF/correo de la Solicitud a Proveedor,
     * resuelto según el origen real de la línea (los 3 son mutuamente
     * excluyentes, ver arriba): el folio de la SIC local, el código de la
     * requisición de EBS cuando el origen es directo, o el folio capturado
     * a mano — en ese orden. `null` si la línea no tiene ningún origen de
     * SIC asociado (ej. viene de un artículo de Proyecto de Presupuesto).
     */
    public function folioSicDisplay(): ?string
    {
        return $this->sic?->folio_sic
            ?? $this->ebsRequisition?->code
            ?? $this->folio_sic_manual;
    }
}
