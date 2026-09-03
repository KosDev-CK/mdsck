<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudProveedorLinea extends Model
{
    protected $table = 'solicitud_proveedor_lineas';

    protected $fillable = [
        'solicitud_id',
        'articulo_id',
        'descripcion_libre',
        'cantidad_solicitada',
        'cantidad_recibida',
        'precio_unitario_cotizado',
        'es_activo_inventariable',
        'detalle_adicional',
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

    public function recepcionLineas()
    {
        return $this->hasMany(RecepcionLinea::class, 'solicitud_proveedor_linea_id');
    }
}
