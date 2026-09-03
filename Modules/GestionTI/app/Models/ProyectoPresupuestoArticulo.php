<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoPresupuestoArticulo extends Model
{
    public const ESTATUS_CAPTURA_PENDIENTE = 'pendiente';

    public const ESTATUS_CAPTURA_CAPTURADO = 'capturado';

    /**
     * Lista fija validada en la capa de aplicación (no es un catálogo con
     * tabla propia) — mismo patrón que `tipo_solicitud`/`urgencia` en otras
     * pantallas de este módulo.
     */
    public const CATEGORIAS = [
        'celulares',
        'telefonia_fija',
        'laptops_desktops',
        'multifuncionales',
        'redes',
        'comunicacion',
        'internet',
        'infraestructura',
        'vpn',
        'ciberseguridad',
        'antivirus',
    ];

    public const CATEGORIA_LABELS = [
        'celulares' => 'Celulares',
        'telefonia_fija' => 'Telefonía fija',
        'laptops_desktops' => 'Laptops/Desktops',
        'multifuncionales' => 'Multifuncionales',
        'redes' => 'Redes',
        'comunicacion' => 'Comunicación',
        'internet' => 'Internet',
        'infraestructura' => 'Infraestructura',
        'vpn' => 'VPN',
        'ciberseguridad' => 'Ciberseguridad',
        'antivirus' => 'Antivirus',
    ];

    protected $fillable = [
        'proyecto_id',
        'categoria',
        'descripcion',
        'cantidad',
        'responsable_costo_id',
        'costo_unitario',
        'estatus_captura',
        'fecha_captura',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:2',
        'fecha_captura' => 'date',
    ];

    public function proyecto()
    {
        return $this->belongsTo(ProyectoPresupuesto::class, 'proyecto_id');
    }

    public function responsableCosto()
    {
        return $this->belongsTo(Empleado::class, 'responsable_costo_id');
    }

    /**
     * Necesaria para filtrar qué artículos de categoría `laptops_desktops` de
     * un proyecto autorizado ya fueron recogidos por una Solicitud a
     * Proveedor (`whereDoesntHave('solicitudProveedor')`) — ver
     * docs/gestionti-progreso.md, decisión de diseño de "disparar la
     * generación de Solicitud a Proveedor".
     */
    public function solicitudProveedor()
    {
        return $this->hasOne(SolicitudProveedor::class, 'proyecto_presupuesto_articulo_id');
    }
}
