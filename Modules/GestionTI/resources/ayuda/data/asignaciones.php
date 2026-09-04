<?php

return [
    'titulo' => 'Asignación de Activo',
    'concepto' => 'Esta pantalla formaliza la entrega física de un equipo (Activo) a un empleado, a partir de una Solicitud Interna de Compra (SIC) ya autorizada. Al guardar, el Activo elegido cambia de estatus a "asignado" y queda ligado a ese empleado — es el paso que cierra el ciclo de "necesito un equipo" (Ticket/SIC) → "aquí está, firmalo de recibido" (esta pantalla).',
    'resuelve' => 'Antes de esta pantalla no había un registro formal de qué equipo específico se entregó, en qué condición, con qué accesorios y quién lo entregó — información necesaria tanto para trazabilidad de inventario como para generar la carta responsiva que el empleado firma al recibir el equipo. Una vez guardada la asignación, la pantalla también permite generar esa carta responsiva en PDF y, más adelante, adjuntar el documento ya firmado (subido directamente o vinculado desde SharePoint), cerrando el expediente físico de la entrega.',
    'proceso' => [
        'Da clic en "Nuevo".',
        'Selecciona la "SIC autorizada pendiente" — solo aparecen SICs ya autorizadas que todavía no tienen una asignación. Al elegirla, el empleado destinatario se muestra automáticamente (no se puede asignar el equipo a alguien distinto del solicitante original).',
        'Selecciona el "Activo" a entregar. Las opciones son los equipos en stock, más cualquier equipo ya reservado específicamente para esta SIC.',
        'Captura la fecha de asignación y el estado del equipo entregado (Nuevo, Usado o Reacondicionado).',
        'Agrega accesorios entregados y observaciones si aplica, y elige el responsable de entrega.',
        'Si el tipo de equipo lo requiere, llena la sección "Configuración técnica" (IP, MACs, sistema operativo, etc.) — es opcional, deja en blanco lo que no aplique (por ejemplo, un Access Point no tiene usuario de dominio).',
        'Puedes adjuntar el documento firmado en este mismo paso, o dejarlo pendiente e imprimir primero el PDF en blanco.',
        'Guarda. Desde el listado, usa "Generar PDF" para obtener la carta responsiva en blanco para imprimir y firmar en papel, y luego "Adjuntar responsiva firmada" para subir el escaneo (o vincular uno ya existente en SharePoint) una vez firmada.',
    ],
    'campos' => [
        ['nombre' => 'SIC autorizada pendiente', 'explicacion' => 'La Solicitud Interna de Compra que originó esta entrega. Obligatoria; solo se listan SICs en estatus autorizada que aún no tienen ninguna asignación creada. El empleado destinatario se deriva de esta SIC y no puede cambiarse en el formulario.'],
        ['nombre' => 'Activo', 'explicacion' => 'El equipo físico a entregar. Obligatorio. Las opciones son los activos "en stock" más los que estén "reservados" específicamente para la SIC elegida — un activo reservado para otra SIC no aparece.'],
        ['nombre' => 'Fecha de asignación', 'explicacion' => 'Fecha en que se hace la entrega. Obligatoria, por defecto hoy.'],
        ['nombre' => 'Estado del equipo entregado', 'explicacion' => 'Nuevo, Usado o Reacondicionado. Obligatorio.'],
        ['nombre' => 'Accesorios entregados', 'explicacion' => 'Texto libre para listar lo que se entrega junto con el equipo (cargador, mochila, mouse, etc.). Opcional.'],
        ['nombre' => 'Responsable de entrega', 'explicacion' => 'La persona (del catálogo de Validadores) que hace físicamente la entrega. Obligatorio.'],
        ['nombre' => 'Observaciones', 'explicacion' => 'Texto libre para cualquier contexto adicional. Opcional.'],
        ['nombre' => 'Configuración técnica (IP, MAC Wi-Fi, MAC Ethernet, Sistema operativo, Versión de Office, ID de producto del S.O., Antivirus, Dominio, Usuario de dominio, Libra Cloud, Oracle/EBS)', 'explicacion' => 'Datos técnicos del equipo configurado, todos opcionales — no todos los tipos de equipo tienen esta información (por ejemplo, un Access Point no tiene usuario de dominio). Libra Cloud y Oracle/EBS son preguntas de Sí/No/Sin capturar sobre si el equipo tiene acceso a esos sistemas.'],
        ['nombre' => 'Documento firmado', 'explicacion' => 'La carta responsiva ya firmada por el empleado, en PDF o imagen (máximo 5 MB). Opcional al crear la asignación — puede adjuntarse en ese momento o después desde el listado, ya que el flujo real es: se genera el PDF en blanco, se imprime, se firma en papel, y solo entonces existe un escaneo que subir.'],
        ['nombre' => 'Buscar en SharePoint', 'explicacion' => 'Alternativa a subir un archivo nuevo: abre un buscador de archivos ya existentes en la carpeta de responsivas de SharePoint y permite vincular uno sin volver a subirlo. Elegir un archivo de SharePoint y subir un archivo nuevo son excluyentes entre sí — usar uno limpia la elección del otro.'],
        ['nombre' => 'Generar PDF (en el listado)', 'explicacion' => 'Genera y descarga la carta responsiva en blanco, lista para imprimir y firmar — disponible sin importar si ya existe o no un documento firmado, porque es justamente el paso previo a conseguirlo.'],
        ['nombre' => 'Adjuntar responsiva firmada (en el listado)', 'explicacion' => 'Solo visible en asignaciones que todavía no tienen un documento firmado registrado. Abre un modal para subir el escaneo o vincular uno de SharePoint; una vez adjuntado, esta opción desaparece para esa asignación (no se puede reemplazar el documento firmado desde aquí).'],
        ['nombre' => 'Estado (columna del listado)', 'explicacion' => 'Badge "Activa" o "Devuelta" según si la asignación ya registra una fecha de devolución del equipo o no.'],
    ],
];
