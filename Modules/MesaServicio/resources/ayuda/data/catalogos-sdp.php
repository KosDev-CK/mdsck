<?php

return [
    'titulo' => 'Catálogos SDP',
    'concepto' => 'Espejo local, de solo lectura, de los 12 catálogos de configuración que ServiceDesk Plus usa internamente para clasificar tickets: categorías, niveles, modos, impactos, urgencias, prioridades, matriz de prioridades, tipos de solicitud, tipos de tarea, tipos de bitácora de trabajo, códigos de cierre y tipos de tiempo de inactividad.',
    'resuelve' => 'Permite consultar rápidamente, desde dentro del sitio, cómo está configurada la instancia de ServiceDesk Plus sin necesidad de entrar al portal de administración de SDP — útil para verificar nombres exactos de categoría/prioridad al configurar otras pantallas del módulo (por ejemplo, el catálogo de SLA usa el nombre exacto de prioridad).',
    'proceso' => [
        'Elige una pestaña para ver el catálogo correspondiente.',
        'Da clic en "Sincronizar catálogos" para traer la versión más reciente de los 12 catálogos desde ServiceDesk Plus — puede tardar unos segundos.',
    ],
    'campos' => [
        ['nombre' => 'Nombre', 'explicacion' => 'El nombre del registro tal como está configurado en ServiceDesk Plus.'],
        ['nombre' => 'Descripción', 'explicacion' => 'Descripción opcional del registro, si SDP la trae.'],
        ['nombre' => 'Color', 'explicacion' => 'Color asociado al registro en SDP (solo aplica a prioridades y tipos de tarea) — se usa como referencia visual, no afecta ninguna otra pantalla del módulo.'],
        ['nombre' => 'Activo', 'explicacion' => 'Si el registro está activo o fue desactivado/eliminado en ServiceDesk Plus.'],
        ['nombre' => 'Matriz de prioridades', 'explicacion' => 'Catálogo especial: cada fila muestra qué prioridad resulta de combinar una urgencia con un impacto (ej. "Alta + Alto → Urgente"), tal como lo calcula SDP automáticamente.'],
        ['nombre' => 'Códigos de cierre', 'explicacion' => 'Los códigos de cierre de SDP pueden aplicar a solicitudes, problemas o cambios — cada uno muestra a qué módulo de SDP pertenece.'],
    ],
];
