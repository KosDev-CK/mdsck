<?php

namespace Modules\GestionTI\Support\Catalogos;

/**
 * Las 11 categorías compartidas por todo el módulo — extraídas literalmente
 * de `ProyectoPresupuestoArticulo::CATEGORIAS`/`CATEGORIA_LABELS` para que
 * `ArticuloSolicitud` (Catálogo de Compras) y Presupuesto por Proyecto usen
 * la misma clasificación en vez de dos listas paralelas. Ver
 * docs/gestionti-progreso.md, entrada "Catálogo unificado de Artículos".
 *
 * Lista fija validada en la capa de aplicación (no es un catálogo con tabla
 * propia) — mismo patrón que `tipo_solicitud`/`urgencia` en otras pantallas
 * de este módulo.
 */
class CategoriaArticulo
{
    public const OPTIONS = [
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

    public const LABELS = [
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
}
