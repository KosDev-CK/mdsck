<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'razon_social', 'nombre_comercial', 'rfc',
        'contacto_nombre', 'contacto_telefono', 'contacto_correo', 'activo', 'es_interno',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'es_interno' => 'boolean',
    ];

    /**
     * Encargados de un proveedor INTERNO (un área de la empresa): son los
     * usuarios que confirman en el sistema la entrega de lo que se les
     * solicitó (`Compras\EntregasInternas`).
     */
    public function usuarios()
    {
        return $this->belongsToMany(\App\Models\User::class, 'proveedor_usuario')->withTimestamps();
    }
}
