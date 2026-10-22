<?php

return [
    'titulo' => 'Entregas internas',
    'concepto' => 'Cuando un equipo o servicio no lo surte un proveedor externo sino un ÁREA de la empresa (por ejemplo Telefonía o Infraestructura), TI le envía una Solicitud a Proveedores dirigida a esa área, que se registra en el catálogo como "proveedor interno". Esta pantalla es donde los encargados de esa área ven lo que se les solicitó y CONFIRMAN en el sistema que ya lo entregaron. Cada encargado ve solo las solicitudes de las áreas a las que pertenece; un Administrador ve todas.',
    'resuelve' => 'Los encargados de esas áreas no usan el resto del módulo de compras, pero necesitan enterarse de que una SIC ya fue autorizada y dejar constancia de cuándo entregaron. Con esta pantalla el área se entera por correo (con el PDF de la solicitud), confirma la entrega con un clic y a quien creó la solicitud le llega un aviso en la campanita para hacer la Recepción en TI (con número de serie y artículo real). Así se conserva el mismo rastro que con un proveedor externo — solicitud, entrega prometida, recepción, reserva y asignación — sin meter el equipo "a la mala" al inventario.',
    'reglas' => [
        'Solo aparecen solicitudes dirigidas a un proveedor marcado como INTERNO, que ya se hayan ENVIADO y que no estén canceladas. Una solicitud guardada pero no enviada no se ve aquí.',
        'Cada encargado ve únicamente las solicitudes de las áreas a las que está asignado (se asigna en Catálogos de Compras → Proveedor, al marcarlo como interno). Un Administrador ve todas.',
        'Confirmar la entrega NO hace la Recepción: solo deja constancia (quién, cuándo y notas) y avisa a quien creó la solicitud. La Recepción de Proveedor, con número de serie, artículo real y ubicación, sigue siendo de TI.',
        'La confirmación no se puede deshacer ni repetir: una vez confirmada, la solicitud pasa a la pestaña "Confirmadas" y la Recepción la muestra con la etiqueta "Entrega confirmada".',
        'TI puede recibir aunque el área todavía no confirme (la Recepción solo avisa "El área aún no confirma la entrega"): la confirmación es un control de seguimiento, no un candado.',
        'La fecha de entrega prometida corre desde que se envía la solicitud (+3 días por omisión); si pasa y el área no ha confirmado, la fila se marca "Vencida".',
    ],
    'proceso' => [
        'TI crea la Solicitud a Proveedores con el proveedor interno (el área) y la envía: el área recibe un correo con el folio, las líneas, cantidades, lugar de entrega y el PDF adjunto.',
        'El encargado entra a "Entregas internas" (pestaña "Por confirmar") y abre la solicitud con un clic.',
        'Revisa las líneas (SIC, artículo, cantidad, lugar de entrega y observaciones).',
        'Cuando ya se entregó lo solicitado, escribe una nota si hace falta (por ejemplo "se entregó en almacén el 15/10") y da clic en "Confirmar entrega".',
        'Quien creó la solicitud recibe el aviso en la campanita y hace la Recepción en "Recepción de Proveedor".',
    ],
    'campos' => [
        ['nombre' => 'Por confirmar / Confirmadas', 'explicacion' => 'Las dos vistas de la pantalla: las entregas que el área aún no ha confirmado, y las ya confirmadas (con quién y cuándo).'],
        ['nombre' => 'Folio', 'explicacion' => 'El folio de la Solicitud a Proveedores (SP-AAMMDD-###).'],
        ['nombre' => 'Área', 'explicacion' => 'El proveedor interno al que se dirigió la solicitud.'],
        ['nombre' => 'Solicitó', 'explicacion' => 'Quien creó la solicitud en TI — a esta persona le llega el aviso cuando se confirma la entrega.'],
        ['nombre' => 'Enviada', 'explicacion' => 'Fecha en que se envió el correo al área.'],
        ['nombre' => 'Entrega prometida', 'explicacion' => 'Fecha comprometida para entregar. Se marca "Vencida" si ya pasó y la entrega no está confirmada.'],
        ['nombre' => 'Líneas', 'explicacion' => 'Cuántas líneas (artículos) trae la solicitud; el detalle completo se ve al abrirla.'],
        ['nombre' => 'Notas de la entrega', 'explicacion' => 'Texto libre opcional al confirmar (dónde, cuándo o a quién se entregó, qué faltó). Queda guardado junto con quién confirmó y la fecha y hora.'],
    ],
];
