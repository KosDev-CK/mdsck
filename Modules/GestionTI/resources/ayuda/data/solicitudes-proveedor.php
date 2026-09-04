<?php

return [
    'titulo' => 'Solicitud a Proveedores',
    'concepto' => 'La Solicitud a Proveedores es el paso donde una necesidad de compra — una Solicitud de SIC (en cualquier estatus), o un artículo de un Proyecto de Presupuesto ya autorizado — se convierte en un pedido formal a un proveedor específico, con líneas de artículos, cantidades y precio cotizado. Puede llevar como origen una SIC o un artículo de proyecto, pero nunca ambos a la vez; también puede crearse sin ningún origen vinculado, para compras que no parten de ninguno de los dos flujos.',
    'resuelve' => 'Es el puente entre "ya se autorizó comprar algo" y "ya se le pidió formalmente a un proveedor". Sin este registro no habría contra qué recibir la mercancía después: la pantalla "Recepción de Proveedor" siempre trabaja sobre las líneas de una Solicitud a Proveedores ya existente, y va descontando cantidades pendientes conforme llegan embarques (parciales o completos). También sirve para dar seguimiento al estatus del pedido completo: Solicitada, Parcialmente recibida, Recibida, Facturada o Cancelada.',
    'proceso' => [
        'Da clic en "Nuevo" — el folio se sugiere automáticamente (formato SP-AAAAMMDD-###) pero puedes cambiarlo si lo necesitas.',
        'Selecciona el proveedor, la fecha de solicitud y el tipo de solicitud (Regular o Compra especial).',
        'Si aplica, vincula el Ticket relacionado.',
        'Si aplica, elige el origen de la solicitud: una Solicitud de SIC, O un artículo de un Proyecto de Presupuesto ya autorizado (solo aparecen artículos de la categoría Laptops/Desktops que ninguna otra solicitud haya recogido todavía) — nunca ambos al mismo tiempo. Es posible dejar la solicitud sin ningún origen vinculado.',
        'Agrega una o más líneas con "+ Agregar línea": para cada una, elige un artículo del catálogo O escribe una descripción libre (no ambos), captura la cantidad solicitada y, si ya la tienes, el precio unitario cotizado.',
        'Marca "Es activo inventariable" en las líneas que representan un equipo que debe darse de alta en el inventario al recibirse (por ejemplo una laptop, a diferencia de un consumible).',
        'Guarda — la solicitud queda en estatus "Solicitada".',
        'Cuando llegue la mercancía, regístrala desde "Recepción de Proveedor" contra esta misma solicitud (puede recibirse en varios embarques parciales).',
        'Si ya no se va a surtir, puedes cancelarla mientras siga en estatus "Solicitada".',
    ],
    'campos' => [
        ['nombre' => 'Folio', 'explicacion' => 'Identificador de la solicitud. Se sugiere automáticamente pero es editable — debe ser único.'],
        ['nombre' => 'Proveedor', 'explicacion' => 'A quién se le hace el pedido, del catálogo de proveedores activos. Obligatorio.'],
        ['nombre' => 'Fecha de solicitud', 'explicacion' => 'Fecha en que se levanta el pedido al proveedor. Obligatorio.'],
        ['nombre' => 'Tipo de solicitud', 'explicacion' => '"Regular" para compras del flujo normal, "Compra especial" para casos que se salen de ese flujo. Obligatorio.'],
        ['nombre' => 'Ticket', 'explicacion' => 'Ticket de Mesa de Servicio relacionado, si existe. Opcional.'],
        ['nombre' => 'Solicitud de SIC', 'explicacion' => 'La SIC que originó este pedido, si el origen es una SIC. Opcional, pero mutuamente excluyente con "Artículo de Proyecto de Presupuesto" — no se pueden elegir los dos.'],
        ['nombre' => 'Artículo de Proyecto de Presupuesto', 'explicacion' => 'El artículo (solo categoría Laptops/Desktops) de un proyecto de presupuesto ya autorizado que origina este pedido, si el origen es un proyecto en vez de una SIC. Opcional, mutuamente excluyente con "Solicitud de SIC".'],
        ['nombre' => 'Artículo del catálogo (por línea)', 'explicacion' => 'El artículo tal como existe en el catálogo de artículos de solicitud. Se usa este O la descripción libre, nunca ambos en la misma línea.'],
        ['nombre' => 'Descripción libre (por línea)', 'explicacion' => 'Texto libre para describir el artículo cuando no está dado de alta en el catálogo. Se usa esto O el artículo del catálogo, nunca ambos.'],
        ['nombre' => 'Cantidad solicitada (por línea)', 'explicacion' => 'Cuántas unidades se piden de ese artículo. Obligatorio, mínimo 1.'],
        ['nombre' => 'Precio unitario cotizado (por línea)', 'explicacion' => 'El precio por unidad que dio el proveedor, si ya se tiene. Opcional.'],
        ['nombre' => 'Es activo inventariable (por línea)', 'explicacion' => 'Actívalo si esa línea, al recibirse, debe generar un Activo dado de alta en el inventario (con número de serie propio). Si se deja apagado, la recepción solo registra la cantidad recibida, sin crear ningún Activo.'],
        ['nombre' => 'Estatus', 'explicacion' => 'Solicitada (recién creada), Parcialmente recibida, Recibida, Facturada o Cancelada — avanza automáticamente conforme se registran recepciones y facturas, salvo "Cancelada" que se marca a mano.'],
    ],
];
