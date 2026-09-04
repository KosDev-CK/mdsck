<?php

return [
    'titulo' => 'Empleados',
    'concepto' => 'Catálogo maestro de las personas de la organización que interactúan con el módulo: quién solicita un ticket, a quién se le asigna un activo, quién es el PM o el aprobador de un proyecto de presupuesto. Incluye, además de los datos personales básicos, su ubicación dentro de la estructura organizacional (puesto, área, empresa) y su línea de mando.',
    'resuelve' => 'Sin este catálogo, Tickets, Solicitudes de SIC, asignación de Activos y Presupuesto de Proyectos no tendrían una referencia confiable a "quién" — cada uno tendría que capturar el nombre a mano, con el riesgo de errores de captura y de no poder saber, por ejemplo, quién es el jefe que debe autorizar un gasto. El campo Correo, en particular, es lo que permite que un usuario del sistema (el que inicia sesión) se vincule automáticamente a su ficha de Empleado y vea su panel personal "Mis pendientes" en el Dashboard.',
    'proceso' => [
        'Da clic en "Nuevo" (o "Editar" sobre un registro existente).',
        'Captura el número de empleado (obligatorio y único — no se puede repetir) y el nombre.',
        'Opcionalmente completa correo, RFC, puesto, área, ubicación, unidad de negocio y empresa.',
        'Si aplica, selecciona su línea de mando: Jefe inmediato o Gerente, Director y Director Ejecutivo (ver el detalle de cada uno más abajo).',
        'Guarda. Cuando alguien deja la organización, usa "Desactivar" en vez de borrar — así se conserva el historial de tickets, activos asignados o proyectos donde ya aparece.',
    ],
    'campos' => [
        ['nombre' => 'Número de empleado', 'explicacion' => 'Identificador único del empleado (el mismo que usa RH/nómina). Obligatorio y no se puede repetir — también es el criterio que usa "Búsqueda Global" para encontrarlo.'],
        ['nombre' => 'Nombre', 'explicacion' => 'Nombre completo del empleado. Obligatorio.'],
        ['nombre' => 'Correo', 'explicacion' => 'Correo del empleado. Opcional, pero importante: si coincide exactamente con el correo de una cuenta de usuario del sistema, esa persona ve automáticamente su panel personalizado "Mis pendientes" en el Dashboard de TI.'],
        ['nombre' => 'RFC', 'explicacion' => 'RFC del empleado. Opcional.'],
        ['nombre' => 'Puesto / Área / Ubicación / Unidad de negocio / Empresa', 'explicacion' => 'Vínculos opcionales a los catálogos dados de alta en "Catálogos Núcleo" — describen dónde encaja el empleado dentro de la organización y sirven para filtrar y generar reportes.'],
        ['nombre' => 'Jefe inmediato o Gerente', 'explicacion' => 'El superior directo de este empleado — primer nivel de su línea de mando. Se elige de la lista de empleados activos (no se puede seleccionar a sí mismo). Opcional.'],
        ['nombre' => 'Director', 'explicacion' => 'Segundo nivel de la línea de mando, por encima del jefe inmediato o gerente. Opcional e independiente del campo anterior — no se valida que exista jerarquía real entre ambos.'],
        ['nombre' => 'Director Ejecutivo', 'explicacion' => 'Tercer y máximo nivel capturado de la línea de mando de este empleado. Opcional.'],
        ['nombre' => 'Estatus (Activo/Inactivo)', 'explicacion' => 'Activa o desactiva el registro. Solo los empleados activos aparecen como opción de "Solicitante", "Jefe inmediato", etc. en otras pantallas.'],
    ],
];
