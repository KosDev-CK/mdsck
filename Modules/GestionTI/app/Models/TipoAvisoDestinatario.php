<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class TipoAvisoDestinatario extends Model
{
    protected $table = 'tipo_aviso_destinatarios';

    public const TIPO_ROL_FIJO = 'rol_fijo';

    public const TIPO_VALIDADOR_ESPECIFICO = 'validador_especifico';

    public const TIPO_DINAMICO_SOLICITANTE = 'dinamico_solicitante';

    public const TIPO_DINAMICO_RESPONSABLE = 'dinamico_responsable';

    public const TIPOS = [
        self::TIPO_ROL_FIJO,
        self::TIPO_VALIDADOR_ESPECIFICO,
        self::TIPO_DINAMICO_SOLICITANTE,
        self::TIPO_DINAMICO_RESPONSABLE,
    ];

    protected $fillable = [
        'tipo_aviso_id',
        'tipo_destinatario',
        'rol_nombre',
        'validador_id',
    ];

    public function tipoAviso()
    {
        return $this->belongsTo(TipoAviso::class);
    }

    public function validador()
    {
        return $this->belongsTo(Validador::class);
    }
}
