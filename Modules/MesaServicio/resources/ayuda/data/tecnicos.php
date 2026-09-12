<?php

return [
    'titulo' => 'Técnicos',
    'concepto' => 'Catálogo local de técnicos de Mesa de Servicio, sincronizado automáticamente a partir de los tickets de ServiceDesk Plus (la API de SDP no tiene un endpoint propio de técnicos — se derivan y deduplican del objeto "technician" que trae cada ticket). Aquí se marca manualmente cuáles cuentan como "Nivel 1".',
    'resuelve' => 'ServiceDesk Plus no tiene un concepto de "Nivel 1" igual al que se necesita para reportar internamente. Este catálogo permite clasificar a cada técnico aparte, sin depender de ningún campo de SDP — y un futuro sync nunca sobreescribe esta clasificación, solo actualiza nombre/correo/puesto/estatus activo.',
    'proceso' => [
        'Usa los filtros de activo/inactivo y de Nivel 1 para acotar la lista si es muy larga.',
        'Activa o desactiva el toggle "Nivel 1" del técnico que corresponda — es el único campo editable de esta pantalla, todo lo demás viene de SDP.',
    ],
    'campos' => [
        ['nombre' => 'Nombre / Correo / Puesto', 'explicacion' => 'Vienen tal cual de ServiceDesk Plus, no son editables aquí.'],
        ['nombre' => 'Estatus', 'explicacion' => 'Activo/Inactivo — lo decide automáticamente el proceso de sincronización (un técnico que deja de aparecer en tickets nuevos se marca inactivo), no se edita a mano.'],
        ['nombre' => 'Nivel 1', 'explicacion' => 'Único campo que se edita manualmente en esta pantalla. Determina qué técnicos se cuentan al activar el filtro "Solo técnicos Nivel 1" en el Dashboard.'],
    ],
];
