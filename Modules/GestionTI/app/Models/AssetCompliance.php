<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class AssetCompliance extends Model
{
    protected $fillable = [
        'asset_id', 'crowdstrike', 'crowdstrike_fecha', 'bitlocker',
        'licencia_1_id', 'licencia_2_id', 'mantenimiento_preventivo',
        'fecha_validacion', 'validado_por_id',
    ];

    protected $casts = [
        'crowdstrike' => 'boolean',
        'bitlocker' => 'boolean',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function licenciaUno()
    {
        return $this->belongsTo(Licencia::class, 'licencia_1_id');
    }

    public function licenciaDos()
    {
        return $this->belongsTo(Licencia::class, 'licencia_2_id');
    }

    public function validadoPor()
    {
        return $this->belongsTo(Validador::class, 'validado_por_id');
    }
}
