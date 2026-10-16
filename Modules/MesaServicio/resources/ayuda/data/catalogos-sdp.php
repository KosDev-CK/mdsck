<?php

return [
    'titulo' => 'Catálogos SDP',
    'concepto' => 'Espejo local, de solo lectura, de los catálogos de configuración que ServiceDesk Plus usa internamente para clasificar tickets: categorías, subcategorías, artículos, niveles, modos, impactos, urgencias, prioridades, matriz de prioridades, tipos de solicitud, tipos de tarea, tipos de bitácora de trabajo, códigos de cierre, tipos de tiempo de inactividad y las reglas SLA definidas en SDP.',
    'resuelve' => 'Permite consultar rápidamente, desde dentro del sitio, cómo está configurada la instancia de ServiceDesk Plus sin necesidad de entrar al portal de administración de SDP — útil para verificar nombres exactos de categoría/prioridad al configurar otras pantallas del módulo (por ejemplo, el catálogo de SLA usa el nombre exacto de prioridad).',
    'proceso' => [
        'Elige una pestaña para ver el catálogo correspondiente.',
        'Da clic en "Sincronizar catálogos" para traer la versión más reciente de los 12 catálogos planos desde ServiceDesk Plus — puede tardar unos segundos.',
        'Da clic en "Exportar a Excel" para descargar el catálogo de la pestaña activa en un archivo .xlsx (incluye el ID de SDP; subcategorías y artículos traen categoría y subcategoría en columnas separadas).',
        'Subcategorías y Artículos se sincronizan desde consola (php artisan sdp:sync-catalogos subcategories, luego items), porque SDP los entrega anidados y toma más de un minuto.',
    ],
    'campos' => [
        ['nombre' => 'Nombre', 'explicacion' => 'El nombre del registro tal como está configurado en ServiceDesk Plus.'],
        ['nombre' => 'Descripción', 'explicacion' => 'Descripción opcional del registro, si SDP la trae.'],
        ['nombre' => 'Color', 'explicacion' => 'Color asociado al registro en SDP (solo aplica a prioridades y tipos de tarea) — se usa como referencia visual, no afecta ninguna otra pantalla del módulo.'],
        ['nombre' => 'Activo', 'explicacion' => 'Si el registro está activo o fue desactivado/eliminado en ServiceDesk Plus.'],
        ['nombre' => 'Pertenece a', 'explicacion' => 'Solo en Subcategorías y Artículos: la categoría (y, en artículos, la subcategoría) a la que pertenece el registro.'],
        ['nombre' => 'SLAs', 'explicacion' => 'Reglas de acuerdo de nivel de servicio tal como están definidas en SDP: tiempo de primera respuesta y de resolución, los criterios que las activan (categoría, prioridad…) y si ignoran fines de semana, feriados u horario operativo. Es una consulta de lo que hay en SDP; no reemplaza ni modifica la pantalla "SLA" propia del módulo, que se edita aparte.'],
        ['nombre' => 'Matriz de prioridades', 'explicacion' => 'Catálogo especial: cada fila muestra qué prioridad resulta de combinar una urgencia con un impacto (ej. "Alta + Alto → Urgente"), tal como lo calcula SDP automáticamente.'],
        ['nombre' => 'Códigos de cierre', 'explicacion' => 'Los códigos de cierre de SDP pueden aplicar a solicitudes, problemas o cambios — cada uno muestra a qué módulo de SDP pertenece.'],
    ],
];
