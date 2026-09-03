<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'razon_social', 'nombre_comercial', 'rfc',
        'contacto_nombre', 'contacto_telefono', 'contacto_correo', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
