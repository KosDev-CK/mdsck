<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
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
        'proyecto_presupuesto_articulo_id',
        'tipo_solicitud',
        'estatus',
        'enviada_at',
        'ultimo_envio_at',
        'creado_por_user_id',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'enviada_at' => 'datetime',
        'ultimo_envio_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Proveedor::class, 'vendor_id');
    }

    /**
     * Usuario que creó esta Solicitud a Proveedor en el sistema — escrito
     * una sola vez en `Compras\SolicitudesProveedor::save()` (rama de
     * creación, cuando `editingId` es null), usado por el correo de
     * envío/reenvío (`SolicitudProveedorMail`) como "solicitante".
     */
    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por_user_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // `sic()` (header) se eliminó — la SIC ahora vive en la línea
    // (`SolicitudProveedorLinea::sic()`), porque una solicitud puede traer
    // de 1 a N SICs. Ver docs/gestionti-progreso.md, rediseño de "Solicitud
    // a Proveedores: selección de 1 a N SICs autorizadas".

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
