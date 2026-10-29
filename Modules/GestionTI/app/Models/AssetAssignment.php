<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    // Viene literal del spec original 7.8: "estado_equipo_entrega
    // (nuevo/usado/reacondicionado)" — mismo patrón que SolicitudSicBorrador::URGENCIAS.
    public const ESTADOS_EQUIPO_ENTREGA = ['nuevo', 'usado', 'reacondicionado'];

    protected $fillable = [
        'asset_id', 'empleado_id', 'ticket_id', 'sic_id',
        'fecha_asignacion', 'fecha_devolucion', 'estado_equipo_entrega',
        'accesorios_entregados', 'responsable_entrega_id', 'observaciones',
        'documento_responsiva_id',
        // Folio de la SIC que cubre la entrega (obligatorio al crear, aunque la SIC
        // aún no esté autorizada) y su liga a la SIC local / requisición de EBS /
        // línea concreta de la requisición. Ver Support\Sic\ResolutorSic.
        'sic_folio', 'ebs_requisition_id', 'ebs_requisition_line_id',
        'excepcion_sin_sic', 'motivo_excepcion', 'registrado_por_user_id',
        'sic_ligada_at', 'sic_ligada_por_user_id',
        // Configuración técnica (Fase 4 etapa 2 — PDF de Responsiva, formato
        // real) — todos opcionales, no todo tipo de equipo tiene esta
        // información (ej. un Access Point).
        'ip', 'mac_wifi', 'mac_ethernet', 'sistema_operativo_id',
        'version_office', 'antivirus', 'dominio', 'usuario_dominio',
        'id_producto_so', 'libra_cloud', 'oracle_ebs',
    ];

    protected $casts = [
        'libra_cloud' => 'boolean',
        'oracle_ebs' => 'boolean',
        'excepcion_sin_sic' => 'boolean',
        'sic_ligada_at' => 'datetime',
    ];

    public const COBERTURA_CUBIERTA = 'cubierta';

    public const COBERTURA_POR_LIGAR = 'por_ligar';

    public const COBERTURA_PENDIENTE = 'pendiente';

    public const COBERTURA_SIN_DATO = 'sin_dato';

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sic()
    {
        return $this->belongsTo(SolicitudSicBorrador::class, 'sic_id');
    }

    public function ebsRequisition()
    {
        return $this->belongsTo(EbsRequisition::class, 'ebs_requisition_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'registrado_por_user_id');
    }

    /** Folio de la SIC a mostrar: el capturado, o el de la SIC local / requisición ligada. */
    public function folioSic(): ?string
    {
        return $this->sic_folio ?: ($this->sic?->folio_sic ?: $this->ebsRequisition?->code);
    }

    /**
     * Cobertura de la SIC, calculada (no capturada): `cubierta` cuando está
     * ligada a una SIC autorizada; `por_ligar` cuando el folio ya coincide con
     * una SIC autorizada pero la asignación aún no se liga a ella (o a su
     * línea); `pendiente` mientras la SIC no exista o no esté autorizada;
     * `sin_dato` para asignaciones sin SIC (históricas o del registro manual).
     */
    public function estadoCobertura(): string
    {
        if ($this->sic_id) {
            return $this->sic?->estatus === SolicitudSicBorrador::ESTATUS_AUTORIZADA
                ? self::COBERTURA_CUBIERTA
                : self::COBERTURA_PENDIENTE;
        }

        if ($this->ebs_requisition_id) {
            return $this->ebsRequisition?->status === 'APPROVED'
                ? self::COBERTURA_CUBIERTA
                : self::COBERTURA_PENDIENTE;
        }

        if ($this->sic_folio) {
            return \Modules\GestionTI\Support\Sic\ResolutorSic::porFolio($this->sic_folio)['autorizada']
                ? self::COBERTURA_POR_LIGAR
                : self::COBERTURA_PENDIENTE;
        }

        return self::COBERTURA_SIN_DATO;
    }

    public function documentoResponsiva()
    {
        return $this->belongsTo(DocumentoDigitalizado::class, 'documento_responsiva_id');
    }

    public function responsableEntrega()
    {
        return $this->belongsTo(Validador::class, 'responsable_entrega_id');
    }

    public function sistemaOperativo()
    {
        return $this->belongsTo(SistemaOperativo::class, 'sistema_operativo_id');
    }
}
