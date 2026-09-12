<?php

return [
    'titulo' => 'Destinatarios de reporte',
    'concepto' => 'Configuración de quién recibe los avisos de los cierres diario y mensual (rol interno "Supervisor Mesa de Servicio" + una lista de correos sueltos para gente sin cuenta en el sistema), y de cuál formulario de Formularios (FormBuilder) es la encuesta de satisfacción que se envía automáticamente cuando un ticket se completa.',
    'resuelve' => 'Sin esta pantalla no habría forma de decidir quién recibe los avisos de cierre sin tocar código, ni de activar o desactivar el envío automático de encuestas de satisfacción sin un despliegue nuevo.',
    'proceso' => [
        'Agrega o quita correos sueltos con el formulario de la parte superior — son destinatarios adicionales sin cuenta en el sistema.',
        'Para que un usuario con cuenta reciba los avisos como "supervisor", asígnale el rol "Supervisor Mesa de Servicio" desde "Perfiles por usuario" (no se hace desde aquí, esta pantalla solo lo muestra en modo lectura).',
        'Selecciona el formulario de encuesta en el desplegable de la parte inferior. Selecciona "Ninguno" para desactivar por completo el envío automático de encuestas.',
    ],
    'campos' => [
        ['nombre' => 'Correo', 'explicacion' => 'Dirección de correo suelta que debe recibir los avisos de cierre diario/mensual, aunque no tenga cuenta en el sistema.'],
        ['nombre' => 'Nombre', 'explicacion' => 'Opcional, solo para identificar a quién pertenece el correo en la lista.'],
        ['nombre' => 'Supervisores (solo lectura)', 'explicacion' => 'Usuarios activos con el rol "Supervisor Mesa de Servicio" — reciben los avisos de cierre automáticamente por tener ese rol, no por estar en esta lista.'],
        ['nombre' => 'Formulario de encuesta', 'explicacion' => 'El formulario publicado de Formularios que se envía automáticamente al solicitante cuando su ticket se marca como completado. "Ninguno" desactiva el envío por completo — no se manda ningún correo de encuesta mientras no haya un formulario seleccionado aquí.'],
    ],
];
