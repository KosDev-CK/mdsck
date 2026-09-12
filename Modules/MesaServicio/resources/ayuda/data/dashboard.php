<?php

return [
    'titulo' => 'Dashboard de Mesa de Servicio',
    'concepto' => 'Resumen del día para atención de 1er nivel: conteos de tickets creados, atendidos y pendientes hoy, alerta de tickets con el SLA de primera respuesta vencido, y "Hallazgos del día" (categorías más frecuentes y picos de volumen detectados por reglas simples). Todo se calcula sobre el espejo local de tickets sincronizado desde ServiceDesk Plus Cloud (sdp_tickets), no contra la API en vivo en cada carga.',
    'resuelve' => 'Antes de este módulo, el conteo de atención de 1er nivel se llevaba a mano y no reflejaba el trabajo real: ServiceDesk Plus quita del listado los tickets que se fusionan con otro, aunque sí representaron trabajo del técnico. Este dashboard da una vista unificada y siempre consistente con el permiso de cada usuario, filtrable a solo técnicos clasificados como "Nivel 1", y señala patrones de volumen sin tener que revisar ticket por ticket.',
    'proceso' => [
        'Da clic en "Sincronizar ahora" para traer los tickets más recientes de ServiceDesk Plus antes de revisar las métricas — la sincronización automática se desactivó (ver nota en docs/mesaservicio-progreso.md sobre el incidente de disco lleno), así que los datos reflejan la última vez que alguien sincronizó a mano.',
        'Activa "Solo técnicos Nivel 1" para acotar los conteos a los técnicos marcados como tal en el catálogo de Técnicos.',
        'Revisa la sección "Hallazgos del día" para ver si algo salió de lo normal hoy.',
    ],
    'campos' => [
        ['nombre' => 'Creados hoy', 'explicacion' => 'Tickets cuya fecha de creación es el día de hoy.'],
        ['nombre' => 'Atendidos hoy', 'explicacion' => 'Tickets en un estado de tipo "completado" cuya fecha de finalización (o, si no la trae, la fecha en que el sync detectó el cambio) es hoy.'],
        ['nombre' => 'Pendientes', 'explicacion' => 'Tickets en un estado de tipo "en curso" en este momento, sin importar cuándo se crearon.'],
        ['nombre' => 'SLA de 1ª respuesta vencido', 'explicacion' => 'Tickets creados hace más de 10 minutos que todavía no tienen una primera respuesta registrada en SDP.'],
        ['nombre' => 'Sincronizar ahora', 'explicacion' => 'Trae los tickets más recientes de ServiceDesk Plus Cloud de forma manual (ya no corre automático cada 5 minutos).'],
        ['nombre' => 'Hallazgos del día', 'explicacion' => 'Top de categorías con más tickets hoy, y alertas cuando una categoría supera 1.5 veces su promedio histórico diario (con un mínimo de 7 días de historial y 3 tickets hoy para no generar ruido). Detección por reglas simples, no machine learning.'],
    ],
];
