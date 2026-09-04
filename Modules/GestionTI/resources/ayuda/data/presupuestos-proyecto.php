<?php

return [
    'titulo' => 'Presupuesto por Proyecto',
    'concepto' => 'Un Proyecto de Presupuesto agrupa el gasto de TI de una iniciativa específica (por ejemplo, el equipamiento de un nuevo centro o sucursal) que necesita presupuestarse, capturarse por artículo, y autorizarse por niveles antes de poder comprarse. Esta pantalla es la bandeja de encabezados: aquí se listan todos los proyectos y se da de alta uno nuevo con sus datos generales; todo el trabajo posterior (agregar artículos, capturar costos, enviar a autorización) sucede en el detalle de cada proyecto, al que se llega dando clic en "Ver detalle" o automáticamente después de crear uno nuevo.',
    'resuelve' => 'Antes de este flujo, armar el presupuesto de un proyecto de TI y conseguir la autorización de varios niveles jerárquicos para gastarlo era un proceso disperso, sin registro único de quién capturó cada costo ni de en qué nivel de aprobación se quedó atorado. Este flujo concentra todo el ciclo: arma el proyecto y sus artículos, reparte la captura del costo de cada artículo entre distintos responsables, junta todo automáticamente cuando ya está completo, y lo somete a cuantos niveles de autorización se necesiten — cada aprobador solo puede actuar sobre su nivel, y solo cuando todos los niveles anteriores ya aprobaron. Un proyecto autorizado deja sus artículos de cómputo (laptops/desktops) disponibles para que Compras genere la Solicitud a Proveedor correspondiente.',
    'proceso' => [
        'Da clic en "Nuevo" y captura los datos generales del proyecto: nombre, empresa, centro de costo, dirección del centro, área operativa solicitante, PM responsable, fecha de solicitud y fecha límite de captura.',
        'Al guardar, el proyecto se crea en estatus "Armado" y te lleva directo a su pantalla de detalle.',
        'En el detalle, mientras el proyecto esté "Armado", agrega uno o más artículos: categoría, descripción, cantidad y el empleado responsable de capturar el costo de cada uno (todavía sin costo).',
        'Cuando ya están todos los artículos necesarios, usa "Enviar a captura de costos" — el proyecto pasa a "En captura de costos" y la composición de artículos queda congelada (ya no se pueden agregar/editar/eliminar).',
        'Cada responsable de costo captura el costo unitario de su(s) artículo(s) asignado(s) directamente en la tabla del detalle. Cuando el último artículo pendiente queda capturado, el proyecto pasa automáticamente a "Completo".',
        'Con el proyecto "Completo", usa "Enviar a autorización": define cuántos niveles de aprobación necesita y quién es el aprobador de cada uno — el proyecto pasa a "En autorización".',
        'Cada aprobador solo ve habilitado su nivel cuando le corresponde el turno (todos los niveles anteriores ya deben estar aprobados) y resuelve con "Aprobar" o "Rechazar", con un comentario opcional.',
        'Si se aprueba el último nivel, el proyecto queda "Autorizado" y sus artículos de cómputo (laptops/desktops) quedan disponibles para que Compras los recoja desde "Solicitud a Proveedores". Si cualquier nivel se rechaza, el proyecto pasa de inmediato a "Rechazado" (estado final) sin importar los niveles restantes.',
        'Desde el detalle también se puede exportar el proyecto y sus artículos a Excel en cualquier momento.',
    ],
    'campos' => [
        ['nombre' => 'Nombre del proyecto', 'explicacion' => 'Nombre descriptivo con el que se identifica el proyecto en listados y reportes. Obligatorio.'],
        ['nombre' => 'Empresa', 'explicacion' => 'La empresa del grupo a la que se carga el gasto, del catálogo de empresas activas. Obligatorio.'],
        ['nombre' => 'Centro de costo', 'explicacion' => 'El centro de costo contable donde se registrará el gasto del proyecto. Obligatorio.'],
        ['nombre' => 'Dirección del centro', 'explicacion' => 'Dirección física del centro/sucursal donde aplica el proyecto (por ejemplo, la nueva ubicación a equipar). Obligatorio.'],
        ['nombre' => 'Área operativa solicitante', 'explicacion' => 'El área del negocio que solicita el proyecto, del catálogo de Áreas. Obligatorio.'],
        ['nombre' => 'PM responsable', 'explicacion' => 'El empleado que coordina el armado del proyecto (dato descriptivo, no un candado de permisos — cualquier persona con acceso a esta pantalla puede operar cualquier proyecto). Obligatorio.'],
        ['nombre' => 'Fecha de solicitud', 'explicacion' => 'Fecha en que se solicita/arranca el proyecto. Obligatorio.'],
        ['nombre' => 'Fecha límite de captura', 'explicacion' => 'Fecha objetivo para que todos los responsables terminen de capturar el costo de sus artículos. Obligatorio.'],
        ['nombre' => 'Buscador y filtro de estatus', 'explicacion' => 'La búsqueda encuentra proyectos por nombre, PM responsable o centro de costo; el filtro acota el listado a un estatus específico del ciclo de vida (Armado, En captura de costos, Completo, En autorización, Autorizado, Rechazado).'],
        ['nombre' => 'Estatus', 'explicacion' => 'Refleja en qué etapa del flujo está el proyecto: Armado (capturando artículos), En captura de costos (esperando que cada responsable capture su costo), Completo (todos los costos capturados, listo para autorizar), En autorización (algún nivel de aprobación pendiente), Autorizado (aprobado en todos los niveles, sus artículos ya pueden comprarse) o Rechazado (algún nivel lo rechazó — estado final).'],
        ['nombre' => 'Ver detalle', 'explicacion' => 'Abre la pantalla de trabajo del proyecto: artículos, captura de costos, envío y resolución de autorización por niveles, y exportación a Excel.'],
    ],
];
