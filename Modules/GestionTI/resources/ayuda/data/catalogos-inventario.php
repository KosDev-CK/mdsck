<?php

return [
    'titulo' => 'Catálogos de Inventario',
    'concepto' => 'Agrupa, en pestañas, todos los catálogos de apoyo que usa el módulo de Inventarios: Tipo de Equipo, Marca, Modelo, Sistema Operativo, Licencia, Propiedad, Validador y Estatus de Activo, más dos catálogos de "regla" — Periodicidad de Mantenimiento y Stock Mínimo — que no describen un activo, sino una política (cada cuánto se le da mantenimiento a un tipo de equipo, o cuántas unidades mínimas debe haber de un tipo de equipo en una ubicación).',
    'resuelve' => 'Normaliza la forma en que se describe cada activo del inventario. Sin estos catálogos, la Ficha de Activo tendría que capturar marca, modelo o sistema operativo como texto libre, lo que dificulta los reportes y genera duplicados con nombres ligeramente distintos (por ejemplo "HP" y "Hewlett-Packard" como si fueran marcas distintas). Periodicidad de Mantenimiento y Stock Mínimo, además, son la base de dos alertas automáticas del módulo: "Mantenimientos próximos" y "Alertas de stock bajo mínimo" en el Dashboard de TI.',
    'proceso' => [
        'Elige la pestaña del catálogo que quieres administrar.',
        'Da clic en "Nuevo" o en "Editar" sobre un registro existente.',
        'Completa el formulario — varía según la pestaña, ver el detalle de cada campo más abajo — y guarda.',
        'Usa "Desactivar" en vez de borrar cuando un valor ya no se debe seguir usando, para no romper los Activos que ya lo referencian.',
        'Usa "Fusionar duplicados" cuando detectes que el mismo valor quedó capturado dos veces. No está disponible en Periodicidad de Mantenimiento ni en Stock Mínimo: ahí no pueden existir duplicados por diseño (solo puede haber una regla por tipo de equipo, o por tipo de equipo + ubicación).',
        'Usa "Exportar a Excel" en cualquier momento para descargar el catálogo de la pestaña activa.',
    ],
    'campos' => [
        ['nombre' => 'Nombre (todas las pestañas) / Nombre conocido (solo Tipo de Equipo)', 'explicacion' => 'El nombre formal es obligatorio en todas las pestañas. "Nombre conocido" (cómo se le llama internamente si es distinto del nombre formal) solo existe en Tipo de Equipo, y ahí es opcional — las demás pestañas (Marca, Sistema Operativo, Licencia, Propiedad, Validador) solo tienen "Nombre".'],
        ['nombre' => 'En alcance del inventario activo (Tipo de Equipo)', 'explicacion' => 'Indica si ese tipo de equipo (por ejemplo Laptop, PC o Monitor) se considera parte del inventario activo que se controla de cerca. Es una decisión de negocio, no un estatus de activo/inactivo del catálogo — arranca marcado por default al crear uno nuevo.'],
        ['nombre' => 'Marca (Modelo)', 'explicacion' => 'A qué marca pertenece el modelo. Obligatoria — primero debe existir la marca.'],
        ['nombre' => 'Código (Estatus de Activo)', 'explicacion' => 'Clave estable usada internamente por el sistema (por ejemplo "en_stock" o "asignado"), distinta del nombre visible. Obligatoria y única.'],
        ['nombre' => 'Tipo de equipo (Periodicidad de Mantenimiento y Stock Mínimo)', 'explicacion' => 'A qué tipo de equipo aplica la regla. Obligatorio.'],
        ['nombre' => 'Meses sugeridos (Periodicidad de Mantenimiento)', 'explicacion' => 'Cada cuántos meses se recomienda dar mantenimiento a ese tipo de equipo. Solo puede existir una periodicidad por tipo de equipo — si ya existe una, hay que editarla en vez de crear otra.'],
        ['nombre' => 'Ubicación (Stock Mínimo)', 'explicacion' => 'Almacén o ubicación al que aplica el mínimo. Obligatoria.'],
        ['nombre' => 'Cantidad mínima (Stock Mínimo)', 'explicacion' => 'Unidades de ese tipo de equipo, en esa ubicación, por debajo de las cuales se genera una alerta de stock bajo (visible en el Dashboard de TI). Solo puede existir un mínimo por combinación de tipo de equipo y ubicación.'],
        ['nombre' => 'Estatus (Activo/Inactivo)', 'explicacion' => 'Activa o desactiva el registro sin borrarlo. Los selects de otras pantallas (por ejemplo, "Tipo de equipo" al dar de alta un Activo) solo listan registros activos.'],
    ],
];
