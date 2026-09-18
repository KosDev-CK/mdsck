<?php

return [
    'titulo' => 'Reportes',
    'concepto' => 'Historial navegable de los cierres diario y mensual generados automáticamente por el sistema (Excel + resumen de métricas), con descarga directa desde aquí.',
    'resuelve' => 'Antes de esta pantalla, los cierres solo se recibían por correo — si alguien lo borraba o no estaba en la lista de destinatarios en el momento del envío, se perdía el acceso al archivo. Aquí queda un historial siempre consultable.',
    'proceso' => [
        'Da clic en "Descargar" junto al cierre que quieras revisar.',
    ],
    'campos' => [
        ['nombre' => 'Periodo', 'explicacion' => 'El día (cierre diario) o mes (cierre mensual) que cubre ese reporte.'],
        ['nombre' => 'Tipo', 'explicacion' => '"Diario" o "Mensual". Ambos incluyen un resumen por estado (con subtotales) y por técnico (completados, en curso y escalados a proveedor por separado). El cierre mensual además agrega la métrica estimada de tickets combinados (folios que ServiceDesk Plus retira del listado al fusionar tickets) y el backlog histórico: todos los tickets aún pendientes al cierre, sin importar en qué mes o año se crearon, por técnico y por categoría.'],
        ['nombre' => 'Generado el', 'explicacion' => 'Fecha y hora exacta en que el sistema generó ese reporte.'],
        ['nombre' => 'Descargar', 'explicacion' => 'Descarga el Excel completo con el resumen y el detalle de tickets del periodo.'],
    ],
];
