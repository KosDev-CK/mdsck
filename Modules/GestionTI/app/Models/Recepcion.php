<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Recepcion extends Model
{
    // Nombre de tabla explícito — la pluralización automática de Eloquent
    // para "Recepcion" daría "recepcions" (mismo riesgo ya documentado para
    // Proveedor/Validador/SolicitudProveedor/etc. en fases previas), no
    // "recepciones".
    protected $table = 'recepciones';

    protected $fillable = [
        'solicitud_proveedor_id',
        'folio_remision',
        'fecha_recepcion',
        'recibido_por_id',
        'documento_remision_id',
        'ubicacion_id',
        'observaciones',
    ];

    protected $casts = [
        'fecha_recepcion' => 'date',
    ];

    public function solicitudProveedor()
    {
        return $this->belongsTo(SolicitudProveedor::class, 'solicitud_proveedor_id');
    }

    public function recibidoPor()
    {
        return $this->belongsTo(Validador::class, 'recibido_por_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_id');
    }

    public function documentoRemision()
    {
        return $this->belongsTo(DocumentoDigitalizado::class, 'documento_remision_id');
    }

    public function lineas()
    {
        return $this->hasMany(RecepcionLinea::class);
    }

    /**
     * M:N — ver `Invoice::recepciones()`, misma tabla pivote
     * `invoice_recepciones`.
     */
    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_recepciones');
    }
}
