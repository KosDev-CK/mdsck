<?php

return [
    'titulo' => 'Ficha de Activo',
    'concepto' => 'La Ficha de Activo es la trazabilidad completa de un equipo (Asset): una línea de tiempo con absolutamente todo lo que le ha pasado, en orden cronológico — desde cómo entró al inventario (alta manual, migración histórica o compra, con toda la cadena Ticket → SIC/Proyecto de Presupuesto → Solicitud a Proveedor → Recepción cuando aplica), pasando por cada asignación y devolución, cada mantenimiento, cada traslado entre ubicaciones y su facturación, hasta hoy. Esta pantalla es solo el buscador de entrada: localiza el equipo por su código, número de serie o service tag y da acceso a su ficha individual.',
    'resuelve' => 'Antes de esta pantalla, reconstruir la historia de un equipo (¿a quién se le asignó?, ¿cuántas veces se le dio mantenimiento?, ¿de qué compra vino?, ¿se ha trasladado de sede?) significaba revisar varias pantallas distintas y cruzar registros a mano. La Ficha de Activo lo concentra todo en una sola vista de trazabilidad, ordenada en el tiempo, y desde ahí se puede exportar un reporte en PDF con esa misma historia.',
    'proceso' => [
        'Escribe en el buscador el código del activo, su número de serie o su service tag — no hace falta el dato completo, basta una coincidencia parcial.',
        'Localiza el equipo en la tabla de resultados (se muestra su tipo, marca/modelo, número de serie y estatus actual).',
        'Da clic en "Ver ficha" para abrir la trazabilidad completa de ese equipo específico.',
    ],
    'campos' => [
        ['nombre' => 'Buscador', 'explicacion' => 'Busca por código de activo, número de serie o service tag al mismo tiempo — cualquiera de los tres que coincida con lo escrito trae el equipo en los resultados.'],
        ['nombre' => 'Código', 'explicacion' => 'Identificador interno único del equipo dentro del inventario de TI.'],
        ['nombre' => 'Tipo', 'explicacion' => 'Tipo de equipo (laptop, desktop, celular, etc.) según el catálogo de Tipos de Equipo.'],
        ['nombre' => 'Marca/Modelo', 'explicacion' => 'Marca y modelo del equipo, cuando están registrados.'],
        ['nombre' => 'N° de serie', 'explicacion' => 'Número de serie del fabricante, uno de los tres campos por los que se puede buscar.'],
        ['nombre' => 'Estatus', 'explicacion' => 'Estatus actual del activo (en stock, asignado, en mantenimiento, etc.) — el mismo catálogo que usa la pantalla "Stock".'],
        ['nombre' => 'Ver ficha', 'explicacion' => 'Abre el detalle de trazabilidad de ese equipo: origen (alta/compra), asignaciones y devoluciones, mantenimientos, traslados y facturación, todo ordenado cronológicamente, con opción de exportarlo a PDF.'],
        ['nombre' => 'Ubicación actual (en la ficha)', 'explicacion' => 'Ubicación/almacén donde se encuentra el equipo en este momento, mostrada en el encabezado de la ficha.'],
        ['nombre' => 'SIC reservada actual (en la ficha)', 'explicacion' => 'Solo aparece cuando el estatus del equipo es "Reservado": muestra el folio de la SIC para la que está apartado.'],
    ],
];
