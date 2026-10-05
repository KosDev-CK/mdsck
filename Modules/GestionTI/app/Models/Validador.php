<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\MesaServicio\Models\SdpTechnician;

class Validador extends Model
{
    protected $table = 'validadores';

    protected $fillable = ['nombre', 'activo', 'user_id', 'tecnico_id', 'iniciales', 'lugar_entrega_id'];

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

    /**
     * Primera referencia de `GestionTI` a un modelo de `Modules\MesaServicio`
     * — decisión deliberada, no una filtración de capas: los 14 registros
     * reales de este catálogo son, en el fondo, técnicos de TI (hoy
     * capturados como códigos sueltos con datos sucios). Ese catálogo de
     * técnicos ya existe como fuente de verdad en `Modules\MesaServicio`
     * (`sdp_technicians`, pantalla "Técnicos", ya en producción) — enlazar
     * en vez de duplicarlo. `nullable` porque casos como "No aplica" o
     * registros legacy aún sin enlazar a mano se quedan sin técnico real.
     */
    /**
     * Sitio que atiende este técnico en Recepción de Proveedor: con valor,
     * solo puede recibir las líneas cuyo lugar de entrega sea ESE; sin valor,
     * puede recibir en cualquier sitio.
     */
    public function lugarEntrega()
    {
        return $this->belongsTo(LugarEntrega::class, 'lugar_entrega_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(SdpTechnician::class, 'tecnico_id');
    }
}
