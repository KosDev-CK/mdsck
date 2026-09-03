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
