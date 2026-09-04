<?php

return [
    'titulo' => 'Solicitud de SIC',
    'concepto' => 'Una Solicitud de SIC (Solicitud Interna de Compra) es el trámite formal con el que un Ticket de Mesa de Servicio se convierte en una necesidad de compra concreta: qué se necesita, para quién, con cargo a qué centro de costo y con qué urgencia. Siempre nace de un Ticket ya existente — no se puede capturar una SIC sin elegir primero el ticket que la originó. Mientras se captura y avanza aquí, la SIC vive como un "borrador" local; la SIC real y su folio oficial se generan del lado de Oracle EBS.',
    'resuelve' => 'Antes de que la requisición exista formalmente en Oracle EBS, alguien tiene que reunir y dejar registrados los datos de la necesidad (empleado, equipo, motivo, centro de costo, urgencia) — esta pantalla es ese punto de captura, y a la vez el lugar donde se le da seguimiento a su ciclo de vida completo: Capturado → SIC creada (folio de EBS) → Autorizada o Rechazada. La vinculación con "SIC en EBS" evita duplicar trabajo: en cuanto Oracle EBS confirma la requisición (la sincronizan automáticamente 2 comandos programados todas las madrugadas), esta pantalla puede vincularse a ese registro con solo elegirlo de una lista — ya sea al momento de capturar el folio a mano, o después desde la pantalla "SIC en EBS" si la vinculación automática por folio no aplicó. Una vez vinculada, el estatus de esta pantalla se mantiene sincronizado con lo que reporte EBS (aprobada/rechazada), sin perder la posibilidad de seguir avanzándola a mano como respaldo si el API de EBS no está disponible.',
    'proceso' => [
        'Da clic en "Nuevo".',
        'Elige el Ticket de origen (debe existir previamente en la pantalla "Tickets").',
        'Selecciona el empleado solicitante, el tipo de equipo, el centro de costo y, si aplica, la unidad de negocio.',
        'Captura el motivo de la solicitud y, si hace falta, las especificaciones técnicas requeridas.',
        'Elige la urgencia (baja, media o alta) y la fecha de solicitud.',
        'Adjunta el documento de la SIC si ya lo tienes a la mano (opcional, se puede agregar después).',
        'Guarda — la solicitud queda en estatus "Capturado".',
        'Cuando la requisición ya exista en Oracle EBS, usa "Marcar SIC creada": puedes escribir el folio a mano, o buscar y elegir la requisición ya sincronizada en la lista (esto autocompleta el folio y la vincula automáticamente) — la solicitud pasa a "SIC creada".',
        'Una vez creada la SIC, autorízala o recházala desde esta misma pantalla (cada acción envía un aviso al empleado solicitante) — si ya está vinculada a una requisición de EBS, este paso también puede resolverse solo con la sincronización automática diaria, sin tocar nada aquí.',
        'En cualquier momento puedes generar el PDF de respaldo de la solicitud con el botón "Generar PDF".',
    ],
    'campos' => [
        ['nombre' => 'Ticket', 'explicacion' => 'El ticket de Mesa de Servicio que dio origen a esta necesidad de compra. Obligatorio — debe existir previamente en "Tickets".'],
        ['nombre' => 'Solicitante', 'explicacion' => 'El empleado para quien es el equipo o servicio solicitado. Obligatorio, se elige de los empleados activos.'],
        ['nombre' => 'Tipo de equipo', 'explicacion' => 'Qué clase de equipo se solicita (laptop, monitor, impresora, etc.), del catálogo de tipos de equipo. Obligatorio.'],
        ['nombre' => 'Motivo', 'explicacion' => 'Explicación de por qué se necesita el equipo o servicio. Obligatorio.'],
        ['nombre' => 'Especificaciones requeridas', 'explicacion' => 'Detalles técnicos específicos que debe cumplir lo que se compre (marca, capacidad, características). Opcional.'],
        ['nombre' => 'Centro de costo', 'explicacion' => 'A qué centro de costo se carga la compra. Obligatorio.'],
        ['nombre' => 'Unidad de negocio', 'explicacion' => 'Unidad de negocio a la que pertenece la solicitud, si aplica distinguirla del centro de costo. Opcional.'],
        ['nombre' => 'Urgencia', 'explicacion' => 'Qué tan pronto se necesita resolver la solicitud: Baja, Media o Alta. Obligatorio.'],
        ['nombre' => 'Fecha de solicitud', 'explicacion' => 'Fecha en que se capturó la necesidad. Obligatorio.'],
        ['nombre' => 'Adjunto (SIC)', 'explicacion' => 'Archivo de respaldo de la solicitud (PDF o imagen), por ejemplo una cotización o el propio formato firmado. Opcional.'],
        ['nombre' => 'Estatus', 'explicacion' => '"Capturado" (recién creada), "SIC creada" (ya tiene folio de Oracle EBS), "Autorizada" o "Rechazada" (resolución final, dispara un aviso al solicitante). Solo se puede autorizar/rechazar una solicitud que ya esté en "SIC creada".'],
        ['nombre' => 'Requisición de EBS (en "Marcar SIC creada")', 'explicacion' => 'Buscador opcional para elegir, de las requisiciones ya sincronizadas desde Oracle EBS, la que corresponde a esta SIC — al elegir una se autocompleta el folio y queda vinculada. Si no eliges ninguna, puedes seguir escribiendo el folio a mano, exactamente igual que antes de que existiera esta integración.'],
        ['nombre' => 'Folio SIC (EBS)', 'explicacion' => 'El folio de la requisición tal como quedó en Oracle EBS. Se autocompleta al elegir una requisición de la lista, o se puede escribir a mano como respaldo si el API de EBS no está disponible.'],
    ],
];
