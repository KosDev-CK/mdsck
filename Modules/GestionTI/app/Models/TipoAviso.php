<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de configuración de "Configuración de Avisos" (sección 7.15 /
 * sección 4 del spec original). Ver docs/gestionti-progreso.md para el
 * diseño completo del resolutor (`Modules\GestionTI\Support\Avisos\AvisoDispatcher`).
 */
class TipoAviso extends Model
{
    protected $table = 'tipos_aviso';

    /**
     * Los 9 códigos de evento del spec original. `EVENTO_SIC_LIGA_POR_EXPIRAR`
     * se deja declarada pero deliberadamente SIN sembrar (ver seeder) — depende
     * de `FormularioSicLink`, que hoy es solo tabla sin pantalla ni flujo real
     * (Fase 5, pausada).
     */
    public const EVENTO_SIC_AUTORIZADA = 'SIC_AUTORIZADA';

    public const EVENTO_SIC_RECHAZADA = 'SIC_RECHAZADA';

    public const EVENTO_MANTENIMIENTO_PROXIMO_VENCER = 'MANTENIMIENTO_PROXIMO_VENCER';

    public const EVENTO_MANTENIMIENTO_VENCIDO = 'MANTENIMIENTO_VENCIDO';

    public const EVENTO_STOCK_BAJO_MINIMO = 'STOCK_BAJO_MINIMO';

    public const EVENTO_PRESUPUESTO_COSTO_PENDIENTE = 'PRESUPUESTO_COSTO_PENDIENTE';

    public const EVENTO_PRESUPUESTO_LISTO_PARA_AUTORIZAR = 'PRESUPUESTO_LISTO_PARA_AUTORIZAR';

    public const EVENTO_PROYECTO_AUTORIZADO = 'PROYECTO_AUTORIZADO';

    public const EVENTO_SIC_LIGA_POR_EXPIRAR = 'SIC_LIGA_POR_EXPIRAR';

    protected $fillable = [
        'codigo',
        'descripcion',
        'entidad_relacionada',
        'evento_disparador',
        'plantilla_mensaje',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function destinatarios()
    {
        return $this->hasMany(TipoAvisoDestinatario::class);
    }
}
