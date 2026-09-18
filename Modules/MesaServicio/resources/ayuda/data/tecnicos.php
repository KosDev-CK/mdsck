<?php

return [
    'titulo' => 'Técnicos',
    'concepto' => 'Catálogo local de técnicos de Mesa de Servicio, sincronizado desde el recurso /users de ServiceDesk Plus (usuarios marcados como técnico — is_technician=true), no desde los tickets. Aquí se marcan manualmente dos clasificaciones propias: si el técnico cuenta como "Nivel 1" y a qué grupo analítico pertenece.',
    'resuelve' => 'ServiceDesk Plus no tiene un concepto de "Nivel 1" igual al que se necesita para reportar internamente, y su noción de "grupo" de técnico es muchos-a-muchos (un técnico puede estar en varios grupos SDP a la vez, sin uno solo "principal"). Este catálogo permite clasificar a cada técnico aparte, sin depender de ningún campo de SDP — y un futuro sync nunca sobreescribe estas clasificaciones, solo actualiza nombre/correo/puesto/estatus activo.',
    'proceso' => [
        'Usa los filtros de activo/inactivo, Nivel 1 y grupo analítico para acotar la lista si es muy larga.',
        'Activa o desactiva el toggle "Nivel 1" del técnico que corresponda.',
        'Elige el grupo analítico del técnico en el selector de esa columna — "Sin asignar" lo deja sin clasificar. Los grupos disponibles se administran desde la pantalla "Grupos Analíticos".',
    ],
    'campos' => [
        ['nombre' => 'Nombre / Correo / Puesto', 'explicacion' => 'Vienen tal cual de ServiceDesk Plus, no son editables aquí.'],
        ['nombre' => 'Estatus', 'explicacion' => 'Activo/Inactivo — lo decide automáticamente el proceso de sincronización (un usuario que deja de estar marcado como técnico en ServiceDesk Plus se marca inactivo aquí), no se edita a mano.'],
        ['nombre' => 'Nivel 1', 'explicacion' => 'Se edita manualmente en esta pantalla. Determina qué técnicos se cuentan al activar el filtro "Solo técnicos Nivel 1" en el Dashboard.'],
        ['nombre' => 'Grupo analítico', 'explicacion' => 'Se edita manualmente en esta pantalla, eligiendo entre los grupos activos del catálogo "Grupos Analíticos". Independiente de los grupos de trabajo que maneja ServiceDesk Plus.'],
    ],
];
