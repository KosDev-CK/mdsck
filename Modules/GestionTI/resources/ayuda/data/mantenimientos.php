<?php

return [
    'titulo' => 'Mantenimiento',
    'concepto' => 'Un Mantenimiento es el registro del servicio (preventivo o correctivo) que se le da a un Activo específico — desde que se programa hasta que se completa o se cancela. No es solo una bitácora: es una máquina de estados (Programado → En proceso → Realizado, con Reprogramado y Cancelado como salidas alternas) que controla qué se puede hacer con cada registro en cada momento, y cada movimiento queda visible después en la línea de tiempo de "Ficha de Activo" del equipo correspondiente.',
    'resuelve' => 'Sin este registro no habría forma de saber qué equipos necesitan servicio próximamente, quién lo realizó (un proveedor externo o personal interno), cuánto costó, ni de conservar el respaldo (orden de servicio o reporte) de que efectivamente se hizo. La sugerencia automática de fecha programada para mantenimiento preventivo (según la periodicidad configurada por tipo de equipo en "Catálogos > Periodicidad de Mantenimiento") ayuda a no perder de vista los servicios recurrentes. El Dashboard de TI también usa este registro para avisar qué mantenimientos vencen en los próximos 7 días.',
    'proceso' => [
        'Da clic en "Nuevo" y selecciona el Activo al que se le dará mantenimiento.',
        'Elige el tipo: Preventivo (servicio programado de rutina) o Correctivo (por falla o reporte).',
        'Si el mantenimiento viene de un reporte de Mesa de Servicio, vincula el Ticket correspondiente (opcional).',
        'Indica el origen de ejecución: Interno (lo realiza personal propio) o Externo (lo realiza un proveedor) — si eliges Externo, debes seleccionar el proveedor.',
        'Revisa la fecha programada: si elegiste Preventivo y el tipo de equipo tiene una periodicidad activa configurada, el sistema la sugiere automáticamente (hoy + los meses configurados); siempre puedes cambiarla.',
        'Guarda — el mantenimiento queda en estatus "Programado", esperando ejecución.',
        'Mientras esté "Programado" o "Reprogramado", puedes usar "Reprogramar" para moverle la fecha (debe ser distinta a la actual) dejando constancia del motivo, o "Iniciar" para pasarlo a "En proceso" cuando el servicio arranca.',
        'Estando "En proceso", usa "Completar" para cerrarlo: captura la fecha real, el diagnóstico, y según el origen — el costo (si es externo) o quién lo realizó de la lista de Validadores (si es interno) — puedes adjuntar la orden de servicio o el reporte del proveedor. Al guardar, el mantenimiento pasa a "Realizado".',
        'En cualquier punto antes de "Realizado" o "Cancelado" puedes usar "Cancelar" para cerrar el registro sin completarlo.',
    ],
    'campos' => [
        ['nombre' => 'Activo', 'explicacion' => 'El equipo al que se le da mantenimiento — se elige del catálogo de Activos existentes. Obligatorio.'],
        ['nombre' => 'Tipo', 'explicacion' => '"Preventivo" (servicio de rutina programado con anticipación) o "Correctivo" (para resolver una falla ya detectada). Obligatorio.'],
        ['nombre' => 'Ticket relacionado', 'explicacion' => 'El Ticket de Mesa de Servicio que originó este mantenimiento, si aplica. Opcional — no todos los mantenimientos (sobre todo los preventivos programados) nacen de un reporte.'],
        ['nombre' => 'Origen de ejecución', 'explicacion' => '"Interno" si lo realiza personal propio de TI, o "Externo" si lo realiza un proveedor. Obligatorio, y determina qué se pide al completar el mantenimiento (costo si es externo, responsable interno si es interno).'],
        ['nombre' => 'Proveedor', 'explicacion' => 'Solo aparece y es obligatorio cuando el origen de ejecución es "Externo" — el proveedor que realizará el servicio, de los proveedores activos.'],
        ['nombre' => 'Fecha programada', 'explicacion' => 'Fecha en que se planea realizar el mantenimiento. Se sugiere automáticamente para mantenimientos preventivos si el tipo de equipo tiene una periodicidad activa configurada, pero siempre es editable.'],
        ['nombre' => 'Nueva fecha programada / Motivo (al reprogramar)', 'explicacion' => 'Al reprogramar, la nueva fecha debe ser distinta a la actual; el motivo es opcional pero recomendable para dejar constancia de por qué se movió.'],
        ['nombre' => 'Fecha realizada', 'explicacion' => 'Se captura al completar el mantenimiento — la fecha real en que se llevó a cabo el servicio. Obligatoria en ese momento.'],
        ['nombre' => 'Diagnóstico', 'explicacion' => 'Descripción de lo que se encontró y/o se hizo durante el mantenimiento. Obligatorio al completar.'],
        ['nombre' => 'Costo', 'explicacion' => 'Solo se pide al completar un mantenimiento con origen "Externo" — el monto cobrado por el proveedor. Obligatorio en ese caso.'],
        ['nombre' => 'Realizado por', 'explicacion' => 'Solo se pide al completar un mantenimiento con origen "Interno" — la persona (del catálogo de Validadores) que ejecutó el servicio. Obligatorio en ese caso.'],
        ['nombre' => 'Orden de servicio / reporte', 'explicacion' => 'Adjunto opcional (PDF o imagen, máximo 5 MB) con la evidencia del servicio realizado — orden de servicio del proveedor o reporte interno.'],
        ['nombre' => 'Estatus', 'explicacion' => 'Refleja en qué punto del ciclo de vida está el mantenimiento: Programado (esperando su fecha), Reprogramado (se le movió la fecha al menos una vez), En proceso (el servicio ya inició), Realizado (completado) o Cancelado (cerrado sin completarse). Cada estatus habilita solo las acciones que le corresponden.'],
    ],
];
