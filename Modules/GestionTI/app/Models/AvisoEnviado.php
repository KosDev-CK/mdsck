<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial/auditoría de TODO lo que `AvisoDispatcher` ha enviado, cruzando
 * canales — NO es la bandeja in-app (esa ya existe, `App\Livewire\Notifications\Bell`
 * sobre la tabla nativa `notifications`). Un registro aquí se crea SOLO
 * cuando se resolvió un `App\Models\User` real y la notificación
 * efectivamente usó ese canal.
 */
class AvisoEnviado extends Model
{
    protected $table = 'avisos_enviados';

    public const CANAL_CORREO = 'correo';

    public const CANAL_IN_APP = 'in_app';

    public const ESTATUS_ENVIADO = 'enviado';

    public const ESTATUS_FALLIDO = 'fallido';

    protected $fillable = [
        'tipo_aviso_id',
        'entidad_relacionada',
        'entidad_id',
        'destinatario_user_id',
        'canal',
        'fecha_envio',
        'estatus_envio',
        'leido',
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
        'leido' => 'boolean',
    ];

    public function tipoAviso()
    {
        return $this->belongsTo(TipoAviso::class);
    }

    public function destinatario()
    {
        return $this->belongsTo(User::class, 'destinatario_user_id');
    }
}
