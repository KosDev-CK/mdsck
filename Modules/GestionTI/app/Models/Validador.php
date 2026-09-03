<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Validador extends Model
{
    protected $table = 'validadores';

    protected $fillable = ['nombre', 'activo', 'user_id'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * `Validador` no tenía ningún dato de contacto — `user_id` se agregó en
     * Fase 4 (Configuración de Avisos) para poder resolverlo a un
     * `App\Models\User` real cuando se usa como destinatario específico de
     * un `TipoAviso`. Es manual (nadie lo llena automáticamente); sin
     * poblar, simplemente no se le puede avisar por ese sistema.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
