<?php

return [
    'titulo' => 'Tickets',
    'concepto' => 'Un Ticket representa la referencia a un caso de Mesa de Servicio (ServiceDesk Plus) que dio origen a un trámite dentro de este módulo — por ejemplo, un empleado reporta que necesita una laptop y ese reporte se abre como ticket en Mesa de Servicio. Aquí solo se captura una referencia liviana a ese ticket (folio, fecha y solicitante), no se gestiona el ciclo de vida completo del ticket en sí — eso sigue viviendo en ServiceDesk Plus.',
    'resuelve' => 'Sin este registro, cada Solicitud Interna de Compra (SIC) quedaría sin un origen rastreable. El Ticket es el punto de partida de la trazabilidad completa del módulo: desde aquí se pueden generar una o más Solicitudes de SIC, y desde cualquier pantalla posterior (Solicitud a Proveedor, Recepción, Asignación de Activo) siempre se puede seguir la cadena hacia atrás hasta el ticket original.',
    'proceso' => [
        'Da clic en "Nuevo".',
        'Si ya tienes el folio de ServiceDesk Plus, captúralo en "Folio SDP" (el interno y/o el visible — con el que tengas a mano basta).',
        'Selecciona la fecha del ticket y el empleado solicitante.',
        'Agrega cualquier observación relevante.',
        'Guarda — el ticket queda disponible para vincularle una o más Solicitudes de SIC desde la pantalla "Solicitud de SIC".',
    ],
    'campos' => [
        ['nombre' => 'Folio SDP (ID interno)', 'explicacion' => 'Identificador interno que usa ServiceDesk Plus para este ticket — el que usa el sistema puertas adentro, no siempre el que ve el solicitante. Opcional, se puede completar antes o después.'],
        ['nombre' => 'Folio SDP (visible)', 'explicacion' => 'El número de ticket tal como lo ve el solicitante en el portal de Mesa de Servicio (ejemplo: "SR-1234"). Opcional — es el más útil para buscar el ticket por el número que la gente reconoce.'],
        ['nombre' => 'Fecha', 'explicacion' => 'Fecha en que se levantó el ticket. Obligatorio.'],
        ['nombre' => 'Solicitante', 'explicacion' => 'El empleado que reportó la necesidad — se elige de los empleados activos dados de alta en "Catálogos > Empleados". Obligatorio; si no aparece la persona que buscas, primero debe existir en ese catálogo.'],
        ['nombre' => 'Observaciones', 'explicacion' => 'Texto libre para cualquier contexto adicional del ticket que no encaje en los demás campos. Opcional.'],
    ],
];
