<?php

return [
    'titulo' => 'Dashboard de TI',
    'concepto' => 'El Dashboard de TI es la pantalla de inicio del módulo: un resumen visual de métricas clave (activos por estatus, stock disponible por tipo, solicitudes pendientes en cada etapa del proceso) más un panel personal de "Mis pendientes" para quien tenga tareas asignadas — autorizaciones de presupuesto por aprobar, costos de proyecto por capturar.',
    'resuelve' => 'Antes de esta pantalla había que entrar pantalla por pantalla para saber qué necesitaba atención. El Dashboard concentra esa vista de un vistazo, y cada tarjeta enlaza directo a la pantalla real donde se resuelve — no duplica funcionalidad, solo la señala. Además, cada tarjeta solo se calcula y se muestra si tu perfil tiene permiso sobre la pantalla que representa, así que lo que ves aquí siempre coincide con lo que puedes hacer en el resto del sistema.',
    'proceso' => [],
    'campos' => [
        ['nombre' => 'Activos por estatus', 'explicacion' => 'Conteo de equipos agrupados por su estatus actual (en stock, asignado, en mantenimiento, etc.). Solo visible si tienes permiso sobre la pantalla "Stock".'],
        ['nombre' => 'Stock disponible por tipo', 'explicacion' => 'Los 8 tipos de equipo con más unidades disponibles en almacén en este momento.'],
        ['nombre' => 'SICs en captura', 'explicacion' => 'Número de Solicitudes Internas de Compra que siguen en borrador, o que ya generaron folio en EBS pero todavía no se envían a un proveedor.'],
        ['nombre' => 'Solicitudes a proveedor pendientes', 'explicacion' => 'Solicitudes ya enviadas a un proveedor que aún no se reciben por completo.'],
        ['nombre' => 'Facturas pendientes de pago / Diferencias a revisar', 'explicacion' => 'Conteo de facturas sin marcar como pagadas, y por separado, facturas cuyo monto no coincide con lo realmente recibido y necesitan revisión manual.'],
        ['nombre' => 'Mantenimientos próximos', 'explicacion' => 'Mantenimientos programados o reprogramados cuya fecha cae dentro de los próximos 7 días.'],
        ['nombre' => 'Mis pendientes', 'explicacion' => 'Solo aparece si tu correo coincide con el de un registro en "Catálogos > Empleados". Muestra tus costos de proyecto por capturar, autorizaciones de presupuesto que te toca aprobar, y tus notificaciones sin leer.'],
    ],
];
