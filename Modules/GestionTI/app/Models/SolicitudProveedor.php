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

    /** Días que se proponen para la entrega prometida (editable a mano en cada solicitud). */
    public const DIAS_ENTREGA_PROMETIDA = 3;

    protected $fillable = [
        'folio',
        'vendor_id',
        'fecha_solicitud',
        'fecha_entrega_prometida',
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
        'fecha_entrega_prometida' => 'date',
        'enviada_at' => 'datetime',
        'ultimo_envio_at' => 'datetime',
    ];

    /** Fecha de entrega que se propone para una solicitud de esa fecha (Y-m-d). */
    public static function entregaPrometidaPorDefecto(string|\DateTimeInterface $fechaSolicitud): string
    {
        return \Illuminate\Support\Carbon::parse($fechaSolicitud)->addDays(self::DIAS_ENTREGA_PROMETIDA)->toDateString();
    }

    /**
     * Código de barras que se imprime en el PDF junto a cada línea: `L{id}-{n}`
     * (id de la solicitud y n = posición de la línea, 1, 2, 3..., por id). Es
     * CORTO a propósito: un código largo (el folio completo) obliga a barras
     * muy finas al imprimirlo y las pistolas lo leen mal. Recepción de
     * Proveedor lo escanea para ir a esa línea.
     */
    public function codigoLinea(int $ordinal): string
    {
        return "L{$this->id}-{$ordinal}";
    }

    /**
     * Interpreta un código de línea escaneado. Devuelve `[id|folio, n]`: el
     * primer elemento es el id de la solicitud (int) para el formato actual
     * `L{id}-{n}`, o el folio (string) para el formato anterior
     * `{folio}-L{n}` (PDFs ya impresos); `null` si no tiene ninguna de las dos
     * formas.
     *
     * @return array{0: int|string, 1: int}|null
     */
    public static function parsearCodigoLinea(string $codigo): ?array
    {
        $codigo = trim($codigo);

        if (preg_match('/^L(\d+)-(\d+)$/i', $codigo, $m) === 1) {
            return [(int) $m[1], (int) $m[2]];
        }

        if (preg_match('/^(.+)-L(\d+)$/i', $codigo, $m) === 1) {
            return [$m[1], (int) $m[2]];
        }

        return null;
    }

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
