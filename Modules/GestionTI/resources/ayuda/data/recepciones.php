<?php

return [
    'titulo' => 'Recepción de Proveedor',
    'concepto' => 'La Recepción de Proveedor registra la llegada física de la mercancía de una Solicitud a Proveedores ya existente. Es la pantalla donde el inventario se vuelve real: por cada unidad de una línea marcada como "activo inventariable" que se reciba, el sistema da de alta un Activo nuevo con su propio folio de inventario, listo para asignarse después a un empleado. Una recepción, una vez guardada, no se puede editar ni cancelar — si falta capturar algo más de la misma solicitud (un embarque parcial), se registra como una recepción nueva.',
    'resuelve' => 'Sin este paso, una Solicitud a Proveedores nunca se traduciría en inventario real: sería solo un pedido en papel. Aquí es donde se confirma cuánto llegó de verdad (que puede ser menos de lo pedido, o llegar en varios embarques), se capturan los datos que identifican cada equipo físico (número de serie, marca, modelo, garantía) y se resguarda el documento de la remisión del proveedor. Si la solicitud de origen viene de una SIC, los activos que se den de alta quedan reservados contra esa SIC en vez de libres en almacén — así el sistema recuerda para quién es ese equipo específico incluso antes de asignárselo formalmente.',
    'proceso' => [
        'Da clic en "Nuevo".',
        'Elige la Solicitud a Proveedor correspondiente — solo aparecen las que siguen en estatus "Solicitada" o "Parcialmente recibida".',
        'Captura el folio de la remisión del proveedor, la fecha de recepción, quién recibe y la ubicación donde queda físicamente la mercancía.',
        'Si tienes la remisión digitalizada, adjúntala (subir un archivo nuevo, o buscar uno ya existente en SharePoint) — es opcional.',
        'Para cada línea, ajusta "Cantidad a recibir ahora" según lo que realmente llegó (no puede ser mayor a lo pendiente).',
        'Si la línea es un activo inventariable y la cantidad es mayor a 0, se abre un sub-formulario: captura la marca (obligatoria), el modelo (opcional), el tipo de equipo (solo si el artículo no lo trae ya definido) y las fechas de garantía si aplican.',
        'Para cada unidad física dentro de esa línea, captura su número de serie (obligatorio) y, si aplica, su service tag (opcional).',
        'Guarda — el sistema da de alta un Activo real por cada unidad inventariable recibida (con folio de inventario propio y estatus "Reservado" si la solicitud viene de una SIC, o "En stock" si no), registra la recepción de las líneas no inventariables por cantidad, y actualiza el estatus de la Solicitud a Proveedor a "Recibida" (si ya se completó todo lo pedido) o "Parcialmente recibida".',
        'Genera el Acta de Entrega-Recepción en PDF con el botón "Generar PDF", disponible para cualquier recepción ya guardada.',
    ],
    'campos' => [
        ['nombre' => 'Solicitud a proveedor', 'explicacion' => 'El pedido contra el que se registra esta llegada de mercancía. Obligatorio — solo se listan solicitudes en estatus "Solicitada" o "Parcialmente recibida".'],
        ['nombre' => 'Folio de remisión', 'explicacion' => 'El folio de la remisión o guía tal como la emitió el proveedor (no es un folio propio del sistema). Obligatorio.'],
        ['nombre' => 'Fecha de recepción', 'explicacion' => 'Fecha en que llegó físicamente la mercancía. Obligatorio.'],
        ['nombre' => 'Recibido por', 'explicacion' => 'Quién de la organización recibió la mercancía, del catálogo de validadores. Obligatorio.'],
        ['nombre' => 'Ubicación destino', 'explicacion' => 'Dónde queda físicamente resguardada la mercancía recibida. Obligatorio.'],
        ['nombre' => 'Observaciones', 'explicacion' => 'Cualquier nota adicional sobre esta recepción. Opcional.'],
        ['nombre' => 'Remisión digitalizada', 'explicacion' => 'El comprobante de entrega del proveedor, ya sea subiendo un archivo nuevo o eligiendo uno ya existente en SharePoint. Opcional.'],
        ['nombre' => 'Cantidad a recibir ahora (por línea)', 'explicacion' => 'Cuánto de esa línea llegó en este embarque — por defecto se propone la cantidad pendiente completa, pero se puede reducir si el embarque viene incompleto. No puede exceder lo pendiente.'],
        ['nombre' => 'Marca (por línea inventariable)', 'explicacion' => 'La marca del equipo que se está dando de alta. Obligatoria para toda línea inventariable con cantidad mayor a 0.'],
        ['nombre' => 'Modelo (por línea inventariable)', 'explicacion' => 'El modelo específico del equipo. Opcional.'],
        ['nombre' => 'Tipo de equipo (por línea inventariable)', 'explicacion' => 'Solo se pide aquí cuando el artículo de la línea no tiene ya definido un tipo de equipo propio en el catálogo — de lo contrario se toma automáticamente del artículo.'],
        ['nombre' => 'Inicio / Fin de garantía (por línea inventariable)', 'explicacion' => 'Vigencia de la garantía del fabricante o proveedor, si aplica. Opcionales, y se comparten por todas las unidades de esa línea en esta recepción.'],
        ['nombre' => 'Número de serie (por unidad)', 'explicacion' => 'El número de serie físico de esa unidad específica. Obligatorio para cada unidad de una línea inventariable.'],
        ['nombre' => 'Service tag (por unidad)', 'explicacion' => 'Identificador adicional del fabricante (por ejemplo, el Service Tag de Dell), si el equipo lo tiene. Opcional.'],
    ],
];
