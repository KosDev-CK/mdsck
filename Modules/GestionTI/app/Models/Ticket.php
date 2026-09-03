<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'sdp_id', 'sdp_display_id', 'fecha', 'empleado_id', 'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function solicitudesSic()
    {
        return $this->hasMany(SolicitudSicBorrador::class);
    }

    public function formularioSicLinks()
    {
        return $this->hasMany(FormularioSicLink::class);
    }
}
