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
    ];

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
