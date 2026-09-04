<?php

return [
    'titulo' => 'Búsqueda Global',
    'concepto' => 'Un único cuadro de búsqueda que recorre varias entidades del módulo a la vez — Activos, Empleados, Solicitudes de SIC, Solicitudes a Proveedor, Recepciones y Facturas — y muestra los resultados agrupados por categoría. No es una pantalla de detalle nueva ni guarda nada: cada resultado enlaza directo a la pantalla real donde vive ese registro.',
    'resuelve' => 'Evita tener que adivinar en qué pantalla del módulo vive un folio, una serie o una persona antes de poder buscarlo — es el "¿dónde está esto?" de todo el módulo en un solo lugar. Además, cada categoría de resultado solo se busca y se muestra si tu perfil tiene permiso sobre la pantalla real a la que pertenece, así que nunca vas a terminar en un enlace que te niegue el acceso.',
    'proceso' => [
        'Escribe al menos 2 caracteres en el cuadro "Buscar en todo el módulo".',
        'Los resultados aparecen agrupados por categoría (Activos, Empleados, Solicitudes de SIC, etc.) conforme escribes, sin necesidad de dar Enter.',
        'Da clic en cualquier resultado para ir directo a la pantalla y al registro correspondiente.',
        'Cada categoría muestra un máximo de 5 resultados. Si hay más, verás el aviso "Mostrando X de Y — refina tu búsqueda"; agrega más texto para acotar la búsqueda.',
    ],
    'campos' => [
        ['nombre' => 'Activos', 'explicacion' => 'Busca por código de activo, número de serie o service tag. Solo aparece si tienes permiso sobre la pantalla "Ficha del Activo".'],
        ['nombre' => 'Empleados', 'explicacion' => 'Busca por número de empleado o por nombre. Solo aparece si tienes permiso sobre "Catálogos > Empleados".'],
        ['nombre' => 'Solicitudes de SIC', 'explicacion' => 'Busca por folio de la Solicitud Interna de Compra. Solo aparece si tienes permiso sobre "Solicitud de SIC".'],
        ['nombre' => 'Solicitudes a Proveedor', 'explicacion' => 'Busca por folio de la solicitud enviada a un proveedor. Solo aparece si tienes permiso sobre "Solicitud a Proveedores".'],
        ['nombre' => 'Recepciones', 'explicacion' => 'Busca por folio de remisión de una recepción de mercancía. Solo aparece si tienes permiso sobre "Recepciones".'],
        ['nombre' => 'Facturas', 'explicacion' => 'Busca por folio de factura. Solo aparece si tienes permiso sobre "Facturación".'],
    ],
];
