<?php

return [
    'titulo' => 'Grupos Analíticos',
    'concepto' => 'Catálogo propio y 100% manual (no viene de ServiceDesk Plus) para agrupar técnicos con fines de reporte y analítica interna. Es un catálogo deliberadamente genérico — pensado para reutilizarse más adelante con otras entidades (por ejemplo empresas o geografía), aunque hoy solo se asigna a técnicos desde la pantalla "Técnicos".',
    'resuelve' => 'ServiceDesk Plus permite que un técnico pertenezca a varios grupos de trabajo a la vez, sin que exista un único grupo "principal" o canónico por técnico en los datos que trae su API — eso hace imposible reportar por grupo directamente desde SDP. Este catálogo resuelve el problema con una clasificación propia y simple: un solo grupo analítico por técnico, asignado a mano, que no depende de la configuración de grupos de SDP ni se ve afectado si esa configuración cambia.',
    'proceso' => [
        'Da de alta un grupo con un nombre (único) y, opcionalmente, una descripción.',
        'Para editar un grupo existente, da clic en "Editar", ajusta los datos y guarda.',
        'Desactiva (sin borrar) un grupo que ya no se use con su interruptor de "Activo" — un grupo inactivo deja de aparecer como opción para asignar a nuevos técnicos, pero los técnicos que ya lo tenían asignado lo conservan.',
        'Da clic en el ícono de bote de basura para eliminar un grupo por completo — a diferencia de desactivar, esto lo borra del catálogo. Los técnicos que lo tenían asignado simplemente quedan sin grupo (no se borran ni se les afecta de otra forma). Pide confirmación antes de borrar porque no se puede deshacer.',
        'La asignación de cada técnico a un grupo analítico se hace desde la pantalla "Técnicos", no aquí — esta pantalla solo administra el catálogo de grupos disponibles.',
    ],
    'campos' => [
        ['nombre' => 'Nombre', 'explicacion' => 'Nombre libre del grupo, único, ej. "Infraestructura" o "Mesa de Ayuda Nivel 2".'],
        ['nombre' => 'Descripción', 'explicacion' => 'Texto libre opcional para aclarar el criterio o alcance del grupo.'],
        ['nombre' => 'Activo', 'explicacion' => 'Solo los grupos activos aparecen como opción al asignar un técnico. Desactivar no borra el grupo ni desasigna a los técnicos que ya lo tenían.'],
        ['nombre' => 'Eliminar', 'explicacion' => 'Borra el grupo por completo del catálogo. A diferencia de desactivar, esto no se puede deshacer — los técnicos que lo tenían asignado quedan sin grupo.'],
    ],
];
