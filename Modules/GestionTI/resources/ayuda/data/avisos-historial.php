<?php

return [
    'titulo' => 'Historial de Avisos',
    'concepto' => 'Bitácora de auditoría de todos los avisos automáticos que el sistema ha intentado enviar (vía `AvisoDispatcher`, ver "Configuración de Avisos"), cruzando ambos canales posibles: correo y notificación dentro de la aplicación. No es la campanita de notificaciones del usuario (esa muestra solo lo tuyo) — esta pantalla es administrativa y muestra el envío completo a todos los destinatarios, con su resultado.',
    'resuelve' => 'Cuando alguien reporta "no me llegó el aviso de que mi SIC fue autorizada", esta pantalla permite confirmar en segundos si el sistema realmente lo intentó enviar, a quién, por qué canal, y si el envío falló o tuvo éxito — sin tener que revisar logs del servidor ni preguntar a soporte técnico.',
    'proceso' => [],
    'campos' => [
        ['nombre' => 'Tipo de aviso', 'explicacion' => 'Filtra por el tipo de aviso configurado en "Configuración de Avisos" (ej. SIC_AUTORIZADA). Si el tipo de aviso original fue borrado, el registro histórico se conserva y se muestra como "Tipo eliminado".'],
        ['nombre' => 'Canal', 'explicacion' => 'Filtra por el medio de entrega: "Correo" (enviado por email) o "En la aplicación" (notificación interna, visible en la campanita del usuario destinatario).'],
        ['nombre' => 'Estatus', 'explicacion' => 'Filtra por el resultado del envío: "Enviado" (se entregó sin error) o "Fallido" (el sistema intentó enviarlo pero ocurrió un error, por ejemplo un problema de conexión con el proveedor de correo).'],
        ['nombre' => 'Desde / Hasta', 'explicacion' => 'Acotan el listado a un rango de fechas de envío. Ambos son opcionales y se pueden usar por separado.'],
        ['nombre' => 'Destinatario', 'explicacion' => 'El usuario del sistema al que se le envió ese aviso en particular — recuerda que un mismo tipo de aviso puede tener varios destinatarios configurados (por ejemplo, un rol fijo completo), así que un solo evento puede generar varias filas en este historial, una por cada persona que lo recibió.'],
        ['nombre' => 'Leído', 'explicacion' => 'Solo aplica al canal "En la aplicación": indica si el destinatario ya marcó esa notificación como leída en su campanita. Para el canal "Correo" siempre se muestra "—", porque el sistema no tiene forma de saber si un correo fue leído.'],
    ],
];
