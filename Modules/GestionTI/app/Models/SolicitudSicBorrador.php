<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudSicBorrador extends Model
{
    // Nombre de tabla explícito — la pluralización automática de Eloquent
    // daría "solicitud_sic_borradors", no "solicitudes_sic_borrador".
    protected $table = 'solicitudes_sic_borrador';

    public const ESTATUS_CAPTURADO = 'capturado';

    public const ESTATUS_SIC_CREADA = 'sic_creada';

    public const ESTATUS_AUTORIZADA = 'autorizada';

    public const ESTATUS_RECHAZADA = 'rechazada';

    public const URGENCIAS = ['baja', 'media', 'alta'];

    protected $fillable = [
        'ticket_id',
        'empleado_id',
        'tipo_equipo_id',
        'motivo',
        'especificaciones_requeridas',
        'centro_costo_id',
        'unidad_negocio_id',
        'urgencia',
        'fecha_solicitud',
        'estatus',
        'folio_sic',
        'ebs_requisition_id',
        'articulo_id',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    /**
     * Artículo inventariable elegido en la captura local — opcional, no lo
     * traen las SIC sincronizadas de Oracle EBS (esa clasificación no existe
     * del lado de EBS). Ver docs/gestionti-progreso.md, entrada "Catálogo
     * unificado de Artículos".
     */
    public function articulo()
    {
        return $this->belongsTo(ArticuloSolicitud::class, 'articulo_id');
    }

    public function centroCosto()
    {
        return $this->belongsTo(CentroCosto::class);
    }

    public function unidadNegocio()
    {
        return $this->belongsTo(UnidadNegocio::class);
    }

    public function formularioSicLink()
    {
        return $this->hasOne(FormularioSicLink::class, 'solicitud_sic_borrador_id');
    }

    /**
     * Vínculo (Fase 5, punto 1) hacia la requisición real de Oracle EBS que
     * le corresponde a esta SIC — la FK real vive en esta tabla
     * (`ebs_requisition_id`), poblada tanto por la sincronización automática
     * como por la vinculación manual desde "SIC en EBS". Ver
     * `Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService`.
     */
    public function ebsRequisition()
    {
        return $this->belongsTo(EbsRequisition::class);
    }

    /**
     * Usada por la pantalla de Asignación (Fase 3, etapa 4) para calcular
     * qué SICs autorizadas siguen "pendientes" — no hay un estatus nuevo
     * "asignada" en el enum de arriba, se infiere por ausencia de una
     * AssetAssignment relacionada (`whereDoesntHave('assetAssignments')`).
     */
    public function assetAssignments()
    {
        return $this->hasMany(AssetAssignment::class, 'sic_id');
    }

    /**
     * Líneas de Solicitud a Proveedor que ya recogieron esta SIC (de 1 a N
     * por solicitud, nunca más de una solicitud a la vez en la práctica —
     * ver `Compras\SolicitudesProveedor::sicPickerOptions()`) — usada para
     * calcular qué SICs autorizadas siguen "disponibles" en el picker
     * (`whereDoesntHave('solicitudProveedorLineas')`), mismo criterio ya
     * usado por `assetAssignments()` de abajo.
     */
    public function solicitudProveedorLineas()
    {
        return $this->hasMany(SolicitudProveedorLinea::class, 'sic_id');
    }

    /**
     * "Autorizada y seleccionable para Solicitud a Proveedor" — mismo
     * criterio usado tanto por `Compras\SolicitudesProveedor::sicPickerOptions()`
     * (picker de checkboxes al armar una solicitud) como por "SIC en EBS"
     * (`MesaServicio\EbsRequisiciones`, filtro "Solo SICs autorizadas y
     * seleccionables" + checkbox por fila): autorizada + artículo de una
     * categoría marcada "Va a Compras" + sin asignar todavía a ninguna
     * línea de Solicitud a Proveedor. Extraído a un scope compartido para no
     * duplicar la consulta entre ambas pantallas.
     *
     * `$exceptSolicitudId` reabre el hueco para el modo edición de
     * `SolicitudesProveedor`: una SIC ya recogida por ESA MISMA solicitud
     * sigue contando como "disponible" (de lo contrario desaparecería del
     * picker al reabrir la solicitud para editarla). "SIC en EBS" nunca pasa
     * este parámetro — no tiene noción de "solicitud en edición".
     */
    public function scopeAutorizadaYSeleccionable($query, ?int $exceptSolicitudId = null)
    {
        $categoriaIdsCompra = CategoriaArticulo::where('es_compra', true)->pluck('id');

        return $query->where('estatus', self::ESTATUS_AUTORIZADA)
            ->whereHas('articulo', fn ($q) => $q->whereIn('categoria_id', $categoriaIdsCompra))
            ->where(function ($q) use ($exceptSolicitudId) {
                $q->whereDoesntHave('solicitudProveedorLineas')
                    ->when($exceptSolicitudId, fn ($q2) => $q2->orWhereHas(
                        'solicitudProveedorLineas',
                        fn ($q3) => $q3->where('solicitud_id', $exceptSolicitudId)
                    ));
            });
    }

    /**
     * Documento adjunto más reciente (`tipo_documento = 'sic'`). No es una
     * relación morph real de Eloquent — `DocumentoDigitalizado` usa una
     * llave genérica (`entidad_relacionada`/`entidad_id`) por diseño, ver
     * la nota en ese modelo.
     */
    public function documentoAdjunto(): ?DocumentoDigitalizado
    {
        return DocumentoDigitalizado::where('entidad_relacionada', class_basename(self::class))
            ->where('entidad_id', $this->id)
            ->latest('fecha_subida')
            ->first();
    }
}
