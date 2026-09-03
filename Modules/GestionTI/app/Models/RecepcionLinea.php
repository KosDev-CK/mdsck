<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class RecepcionLinea extends Model
{
    protected $table = 'recepcion_lineas';

    protected $fillable = [
        'recepcion_id',
        'solicitud_proveedor_linea_id',
        'cantidad_recibida',
        'asset_id',
    ];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class);
    }

    public function solicitudProveedorLinea()
    {
        return $this->belongsTo(SolicitudProveedorLinea::class, 'solicitud_proveedor_linea_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
