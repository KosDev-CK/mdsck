<?php

return [
    'titulo' => 'Dashboard Ejecutivo',
    'concepto' => 'Pantalla pensada para dirección: un resumen de alto nivel de Mesa de Servicio sobre un periodo seleccionable (por defecto, año a la fecha) — KPIs generales, tendencias mensuales, distribución por categoría/departamento/nivel de atención y hallazgos estratégicos calculados por reglas simples. Sin el detalle operativo del día a día que sí muestra el Dashboard de Operación.',
    'resuelve' => 'Le da a dirección una vista rápida y consistente del desempeño general de Mesa de Servicio en el periodo que elija, sin tener que interpretar el detalle operativo pensado para el equipo técnico ni exportar manualmente reportes de ServiceDesk Plus.',
    'proceso' => [
        'Abre el panel de filtros con el botón de embudo (junto al de ayuda, arriba a la derecha) para acotar el periodo de todas las métricas y gráficas de la pantalla. El texto "Periodo: ..." junto al título siempre muestra qué se está filtrando, incluso con el panel cerrado.',
        'Dentro del panel, elige uno de 3 modos: "Rango de días" (fechas "Desde"/"Hasta" libres, por defecto del 1 de enero del año en curso a hoy), "Mes" (un mes y año específicos) o "Ejercicio" (un año calendario completo, de enero a diciembre) — para comparar mes contra mes o año contra año sin calcular fechas a mano. Presiona "Aplicar" para acotar el dashboard y cerrar el panel.',
        'Revisa los 4 KPI principales (Total tickets, Completados, % SLA cumplido, Tiempo mediano de resolución) y los 4 secundarios (Incidentes, Solicitudes, Requerimientos, Tickets combinados) para un vistazo general del periodo.',
        'Explora las gráficas de tendencia mensual, distribución por categoría, cumplimiento de SLA por mes, áreas con mayor demanda y tipo de solicitud por mes para identificar patrones.',
        'Revisa las tablas de distribución por nivel de atención y de categorías por mes (con celdas coloreadas según intensidad) para el detalle numérico detrás de las gráficas.',
        'Lee la sección "Hallazgos estratégicos" para insights automáticos (categoría/departamento dominante, mes con más volumen, mejor y peor mes de cumplimiento de SLA, % de tickets combinados) — algunos hallazgos no aparecen si el rango seleccionado es muy corto para calcularlos con sentido.',
        'Consulta la tabla "Resumen mensual" al final para el detalle mes a mes con una fila "Total" que recalcula el agregado real del periodo completo (no es la suma de los porcentajes mensuales).',
    ],
    'campos' => [
        ['nombre' => 'Tipo de periodo', 'explicacion' => '"Rango de días" acota por fechas libres; "Mes" acota a un mes calendario completo de un año elegido; "Ejercicio" acota a un año calendario completo (enero a diciembre). Los 3 modos son mutuamente excluyentes — solo uno acota el dashboard a la vez.'],
        ['nombre' => 'Total tickets', 'explicacion' => 'Tickets creados dentro del periodo seleccionado — incluye los tickets fusionados a otro folio (combinados), porque sí representaron trabajo real.'],
        ['nombre' => 'Completados', 'explicacion' => 'Tickets del rango en un estado de tipo "completado".'],
        ['nombre' => '% SLA cumplido', 'explicacion' => 'Porcentaje de tickets del rango (excluyendo combinados, igual que en Cumplimiento de SLA) resueltos dentro del tiempo objetivo definido en el catálogo de SLA para su prioridad. La línea de meta (80%) es un valor de referencia del mockup original, no una meta oficial del sistema.'],
        ['nombre' => 'Tiempo mediano de resolución', 'explicacion' => 'Mediana (no promedio) de horas entre la creación y la resolución/finalización de los tickets del rango con ese dato disponible, excluyendo combinados.'],
        ['nombre' => 'Incidentes / Solicitudes / Requerimientos', 'explicacion' => 'Conteo de tickets del rango por tipo de solicitud (los 3 valores que usa ServiceDesk Plus).'],
        ['nombre' => 'Tickets combinados', 'explicacion' => 'Tickets del rango que SDP fusionó a otro folio — dejan de contarse como folio independiente en SDP, pero sí representaron trabajo real.'],
        ['nombre' => 'Distribución por nivel de atención', 'explicacion' => 'Conteo por nivel (1. Mesa de Ayuda / 2. Soporte Aplicativo y/o en Sitio / 3. Escalado). "Sin nivel" agrupa tickets sin ese dato — esperable en datos recientes, ya que el campo se incorporó a la sincronización el 2026-09-17.'],
        ['nombre' => 'Categorías por mes', 'explicacion' => 'Matriz de categoría (top 8 + "Otras") por mes, con la celda más oscura marcando el mes de mayor volumen de esa categoría dentro del rango.'],
        ['nombre' => 'Hallazgos estratégicos', 'explicacion' => 'Insights calculados por reglas simples (no machine learning) sobre el rango seleccionado: categoría/departamento con más tickets, mes con mayor volumen, mejor/peor mes de cumplimiento de SLA, y % de tickets combinados.'],
    ],
];
