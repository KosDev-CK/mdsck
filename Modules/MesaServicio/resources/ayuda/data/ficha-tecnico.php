<?php

return [
    'titulo' => 'Ficha de técnico',
    'concepto' => 'Vista de detalle de un técnico específico: sus tickets pendientes y atendidos, y su promedio de calificación de satisfacción con el conteo de encuestas respondidas (Fase 6).',
    'resuelve' => 'Da una vista enfocada del desempeño de un técnico en particular sin tener que filtrar manualmente el listado general de tickets del Dashboard.',
    'proceso' => [
        'Se llega aquí dando clic en el nombre de un técnico desde el Dashboard (ej. en la tabla de SLA vencido) o desde el catálogo de Técnicos.',
    ],
    'campos' => [
        ['nombre' => 'Pendientes', 'explicacion' => 'Hasta 100 tickets más recientes de este técnico en un estado de tipo "en curso".'],
        ['nombre' => 'Atendidos', 'explicacion' => 'Hasta 100 tickets más recientes de este técnico en un estado de tipo "completado", ordenados por fecha de finalización.'],
        ['nombre' => 'Satisfacción', 'explicacion' => 'Promedio de la calificación 1-5 de la primera pregunta de la encuesta configurada, y cuántas encuestas de este técnico se han respondido. Si no hay formulario de encuesta configurado, o todavía no hay respuestas, se muestra un estado vacío en vez de un promedio.'],
        ['nombre' => 'SLA', 'explicacion' => 'Estado de vencimiento tal cual lo calcula ServiceDesk Plus con su propia configuración interna de SLA — no usa el catálogo editable de la pantalla "Cumplimiento de SLA".'],
    ],
];
