<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\GestionTI\Support\Catalogos\CategoriaArticulo;

class ProyectoPresupuestoArticulo extends Model
{
    public const ESTATUS_CAPTURA_PENDIENTE = 'pendiente';

    public const ESTATUS_CAPTURA_CAPTURADO = 'capturado';

    /**
     * Alias de `CategoriaArticulo::OPTIONS`/`LABELS` — las 11 categorías se
     * extrajeron a esa clase compartida para que `ArticuloSolicitud`
     * (Catálogo de Compras) las reutilice sin duplicar la lista. Cero
     * cambio de comportamiento aquí, ver docs/gestionti-progreso.md, entrada
     * "Catálogo unificado de Artículos".
     */
    public const CATEGORIAS = CategoriaArticulo::OPTIONS;

    public const CATEGORIA_LABELS = CategoriaArticulo::LABELS;

    /**
     * Agrupación contable de 5 valores fijos que exige el Excel corporativo
     * real (ver la migración `add_excel_corporativo_fields_to_presupuesto`)
     * — orden fijo a propósito, es el mismo orden numerado (1-5) en que
     * aparecen las secciones en ese documento y en el export.
     */
    public const CATEGORIAS_CONTABLES = [
        'aplicativos',
        'infraestructura',
        'telco',
        'ciberseguridad',
        'gastos_implementacion',
    ];

    public const CATEGORIA_CONTABLE_LABELS = [
        'aplicativos' => 'Aplicativos',
        'infraestructura' => 'Infraestructura',
        'telco' => 'Telco',
        'ciberseguridad' => 'Ciberseguridad',
        'gastos_implementacion' => 'Gastos de Implementación',
    ];

    public const TIPOS_SERVICIO = [
        'consultoria',
        'equipo',
        'servicio',
        'licencia',
        'envio',
    ];

    public const TIPO_SERVICIO_LABELS = [
        'consultoria' => 'Consultoría',
        'equipo' => 'Equipo',
        'servicio' => 'Servicio',
        'licencia' => 'Licencia',
        'envio' => 'Envío',
    ];

    public const CASHFLOW_ONE_TIME = 'one_time';

    public const CASHFLOW_ON_GOING = 'on_going';

    public const CASHFLOW_TIPOS = [
        self::CASHFLOW_ONE_TIME,
        self::CASHFLOW_ON_GOING,
    ];

    public const CASHFLOW_LABELS = [
        self::CASHFLOW_ONE_TIME => 'One Time',
        self::CASHFLOW_ON_GOING => 'On going',
    ];

    protected $fillable = [
        'proyecto_id',
        'categoria',
        'categoria_contable',
        'descripcion',
        'cantidad',
        'responsable_costo_id',
        'costo_unitario',
        'proveedor',
        'razon_social_facturada',
        'tipo_servicio',
        'cashflow_tipo',
        'no_meses',
        'costo_unitario_usd',
        'estatus_captura',
        'fecha_captura',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:2',
        'costo_unitario_usd' => 'decimal:2',
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
