<?php

namespace Modules\MesaServicio\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de sitios geográficos de SDP (Fase 8) — mismo estilo simple que
 * SdpTechnician/SdpTicketStatus. Alimentado por `sdp:sync-sites` (manual) y,
 * de forma parcial/perezosa (solo sdp_id+nombre), por
 * SyncTicketsCommand::resolveSiteId() cuando un ticket trae un sitio que
 * todavía no existe localmente.
 */
class SdpSite extends Model
{
    protected $table = 'sdp_sites';

    protected $fillable = [
        'sdp_id',
        'nombre',
        'pais',
        'estado',
        'region',
        'ciudad',
        'calle',
        'numero_puerta',
        'codigo_postal',
        'localidad',
        'punto_referencia',
        'zona_horaria',
    ];
}
