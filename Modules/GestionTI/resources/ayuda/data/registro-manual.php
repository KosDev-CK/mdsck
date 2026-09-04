<?php

return [
    'titulo' => 'Registro Manual de Activo',
    'concepto' => 'Esta pantalla da de alta un Activo directamente en el inventario, sin pasar por el flujo normal de Ticket → SIC → Solicitud a Proveedor → Recepción. Es una vía alterna para registrar equipos que ya existen físicamente pero nunca entraron al sistema por el flujo estándar de compra: por ejemplo, equipo heredado de una migración de datos histórica no capturada por completo, un ajuste de inventario tras un conteo físico, una donación, o un equipo que se detecta faltante en el sistema aunque ya estaba en uso.',
    'resuelve' => 'Sin esta pantalla, un equipo que llegó al inventario por una vía distinta a una compra formal simplemente no existiría en el sistema, o tendría que forzarse artificialmente por el flujo de Recepción de Proveedor (pensado para compras reales, con Solicitud a Proveedor y remisión). Aquí se captura directamente el equipo con todos sus datos relevantes y, si ya se sabe que va destinado a un empleado en particular, se puede entregar de una vez sin crear una SIC ni un ticket — quedando disponible de inmediato en el listado de "Asignación de Activo" para generar su carta responsiva.',
    'proceso' => [
        'Da clic en "Nuevo".',
        'Captura el tipo de equipo y la ubicación actual (obligatorios), y opcionalmente marca/modelo, número de serie, service tag, costo de adquisición y proveedor.',
        'Captura la fecha de alta a stock (obligatoria) y, si aplica, las fechas de inicio y fin de garantía.',
        'Elige la propiedad del equipo si aplica, y quién da de alta el registro ("Dado de alta por").',
        'Escribe el motivo o justificación del alta manual — es obligatorio y queda como constancia de por qué este equipo no siguió el flujo normal de compra.',
        'Elige el destino: "Enviar a stock" (el equipo queda disponible en almacén) o "Entregar directo a un empleado" (se completa además empleado destinatario, estado del equipo entregado y responsable de entrega).',
        'Guarda. El sistema genera automáticamente el código único del activo, igual que en el flujo de Recepción.',
    ],
    'campos' => [
        ['nombre' => 'Tipo de equipo', 'explicacion' => 'Obligatorio — determina, entre otras cosas, el prefijo del código único que se generará para el activo.'],
        ['nombre' => 'Ubicación actual', 'explicacion' => 'Dónde queda físicamente el equipo. Obligatoria.'],
        ['nombre' => 'Marca / Modelo', 'explicacion' => 'Opcionales, del catálogo correspondiente.'],
        ['nombre' => 'Número de serie / Service tag', 'explicacion' => 'Identificadores del fabricante, opcionales.'],
        ['nombre' => 'Costo de adquisición', 'explicacion' => 'Opcional — útil cuando se conoce el valor del equipo aunque no haya pasado por el flujo de compra formal.'],
        ['nombre' => 'Proveedor', 'explicacion' => 'Opcional, del catálogo de proveedores activos, si se sabe de dónde vino el equipo.'],
        ['nombre' => 'Fecha de alta a stock', 'explicacion' => 'Fecha en la que el equipo entra formalmente al inventario del sistema. Obligatoria, por defecto hoy.'],
        ['nombre' => 'Inicio / Fin de garantía', 'explicacion' => 'Fechas opcionales de vigencia de garantía, si se conocen.'],
        ['nombre' => 'Propiedad', 'explicacion' => 'Opcional, del catálogo de propiedades (por ejemplo, si el equipo es propio o rentado).'],
        ['nombre' => 'Dado de alta por', 'explicacion' => 'La persona (del catálogo de Validadores) responsable de este registro manual. Obligatorio.'],
        ['nombre' => 'Motivo / justificación del alta manual', 'explicacion' => 'Texto obligatorio que explica por qué este equipo se está registrando por esta vía alterna en vez del flujo normal de Recepción — queda como constancia auditable.'],
        ['nombre' => 'Nota de adquisición original', 'explicacion' => 'Texto libre opcional para cualquier referencia a cómo se adquirió originalmente el equipo (por ejemplo, un dato de la migración histórica).'],
        ['nombre' => 'Destino (Enviar a stock / Entregar directo a un empleado)', 'explicacion' => 'Define qué pasa con el equipo al guardar. "Enviar a stock" deja el activo disponible en almacén (estatus "en stock"). "Entregar directo a un empleado" deja el activo como "asignado" y crea de una vez el registro de asignación correspondiente — sin necesidad de una SIC ni un ticket previos —, la cual aparecerá también en el listado de "Asignación de Activo" para generar ahí su carta responsiva.'],
        ['nombre' => 'Empleado destinatario / Estado del equipo entregado / Responsable de entrega', 'explicacion' => 'Solo se piden y son obligatorios cuando el destino elegido es "Entregar directo a un empleado".'],
        ['nombre' => 'Accesorios entregados / Observaciones', 'explicacion' => 'Texto libre opcional, solo aplica cuando el destino es "Entregar directo a un empleado".'],
    ],
];
