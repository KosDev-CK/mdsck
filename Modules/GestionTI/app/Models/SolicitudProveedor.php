<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudProveedor extends Model
{
    // Nombre de tabla explícito — la pluralización automática de Eloquent
    // para "SolicitudProveedor" daría "solicitud_proveedors" (mismo riesgo
    // ya documentado para Proveedor/Validador/etc. en Fase 1), no
    // "solicitudes_proveedor".
    protected $table = 'solicitudes_proveedor';

    public const ESTATUS_SOLICITADA = 'solicitada';

    public const ESTATUS_PARCIALMENTE_RECIBIDA = 'parcialmente_recibida';

    public const ESTATUS_RECIBIDA = 'recibida';

    public const ESTATUS_FACTURADA = 'facturada';

    public const ESTATUS_CANCELADA = 'cancelada';

    public const TIPOS = ['regular', 'compra_especial'];

    protected $fillable = [
        'folio',
        'vendor_id',
        'fecha_solicitud',
        'ticket_id',
        'sic_id',
        'proyecto_presupuesto_articulo_id',
        'tipo_solicitud',
        'estatus',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
    ];

    public function vendor()
    {
        return $this->belongsTo(Proveedor::class, 'vendor_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sic()
    {
        return $this->belongsTo(SolicitudSicBorrador::class, 'sic_id');
    }

    public function lineas()
    {
        return $this->hasMany(SolicitudProveedorLinea::class, 'solicitud_id');
    }

    public function proyectoPresupuestoArticulo()
    {
        return $this->belongsTo(ProyectoPresupuestoArticulo::class, 'proyecto_presupuesto_articulo_id');
    }

    /**
     * No existía todavía — necesaria para que Facturación (Fase 3 etapa 6)
     * pueda determinar si todas las remisiones de esta solicitud ya
     * quedaron facturadas.
     */
    public function recepciones()
    {
        return $this->hasMany(Recepcion::class, 'solicitud_proveedor_id');
    }
}
