<?php

return [
    'titulo' => 'Cumplimiento de SLA',
    'concepto' => 'Catálogo propio y editable de acuerdos de nivel de servicio (SLA) — tiempos máximos de primera respuesta y de resolución por prioridad — y las métricas de qué tan bien se están cumpliendo, por técnico y por categoría.',
    'resuelve' => 'ServiceDesk Plus ya marca cada ticket como vencido o no según su propia configuración interna de SLA, pero esa configuración no siempre es visible ni ajustable desde aquí. Esta pantalla da un catálogo propio, completamente editable, para medir el cumplimiento contra tiempos objetivo definidos por el equipo, sin depender de la configuración interna de SDP.',
    'proceso' => [
        'En "Definiciones de SLA", agrega o edita una definición: nombre, la prioridad a la que aplica (debe coincidir exactamente con el nombre de prioridad que usa SDP, por ejemplo "Alta"), y el tiempo máximo de primera respuesta y de resolución en minutos.',
        'Una definición sin prioridad asignada aplica como "por defecto" a cualquier ticket cuya prioridad no tenga una definición específica activa.',
        'Desactiva (sin borrar) una definición que ya no aplique, con su interruptor de "Activo" — una definición inactiva no se usa para calcular cumplimiento.',
        'Ajusta el rango de fechas para ver el cumplimiento de un periodo distinto — por defecto se muestra desde el primer día del mes en curso hasta hoy.',
        'Las tablas de "Cumplimiento por técnico" y "Cumplimiento por categoría" muestran, solo para los tickets del rango que ya tienen el dato necesario (respondidos o resueltos), el porcentaje que cumplió cada tiempo objetivo.',
    ],
    'campos' => [
        ['nombre' => 'Nombre', 'explicacion' => 'Nombre libre de la definición, único, ej. "SLA Alta".'],
        ['nombre' => 'Prioridad', 'explicacion' => 'Debe coincidir exactamente con el nombre de prioridad tal cual lo usa SDP. Vacío = definición por defecto para cualquier prioridad sin una definición específica.'],
        ['nombre' => 'Tiempo primera respuesta', 'explicacion' => 'Minutos máximos entre la creación del ticket y su primera respuesta.'],
        ['nombre' => 'Tiempo de resolución', 'explicacion' => 'Minutos máximos entre la creación del ticket y su resolución/finalización.'],
        ['nombre' => 'Activo', 'explicacion' => 'Solo las definiciones activas se usan para calcular cumplimiento.'],
    ],
];
