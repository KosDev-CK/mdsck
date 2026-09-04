<?php

return [
    'titulo' => 'Facturación',
    'concepto' => 'Esta pantalla registra manualmente las facturas que un proveedor entrega junto con la mercancía o remisión de un pedido de TI. No genera ninguna Orden de Compra formal (eso vive en el ERP externo de la empresa, no en este módulo) — es solo el registro y seguimiento interno del ciclo recibida → registrada → autorizada → pagada, y el punto donde se vincula la factura con las Remisiones (Recepciones) que realmente ampara.',
    'resuelve' => 'Sin esta pantalla no había forma de saber, dentro del módulo, qué facturas siguen pendientes de pago ni de detectar cuando el monto facturado por el proveedor no coincide con lo que realmente se cotizó y recibió. Al vincular una factura con sus remisiones, el sistema también marca automáticamente los activos correspondientes como facturados y, cuando una Solicitud a Proveedor completa ya tiene todas sus remisiones facturadas, la marca como "facturada".',
    'proceso' => [
        'Da clic en "Nuevo".',
        'Captura el folio de la factura, elige el proveedor, la fecha de recepción, el monto total y la moneda (MXN o USD).',
        'Opcionalmente captura la partida presupuestal y el ejercicio fiscal si ya se conocen.',
        'Si tienes la factura digitalizada a la mano, adjúntala; si no, puedes hacerlo después editando el registro.',
        'Selecciona, de la lista de remisiones (Recepciones) del proveedor elegido, cuáles quedan amparadas por esta factura. Puedes dejarlo vacío si la factura llegó antes de encontrar/confirmar sus remisiones, y vincularlas después editando el registro.',
        'Revisa el "Total cotizado de las remisiones seleccionadas" y la "Diferencia" que el sistema calcula automáticamente antes de guardar.',
        'Guarda. La factura queda en estatus "Recibida" y, desde el listado, se avanza manualmente por "Registrar", "Autorizar" y "Marcar pagada" en ese orden — no se puede saltar un paso.',
    ],
    'campos' => [
        ['nombre' => 'Folio de factura', 'explicacion' => 'Número de factura que asigna el proveedor. Obligatorio y único por proveedor — dos proveedores distintos sí pueden compartir el mismo folio, pero el mismo proveedor no puede repetirlo.'],
        ['nombre' => 'Proveedor', 'explicacion' => 'El proveedor que emitió la factura, de los proveedores activos del catálogo. Obligatorio — al cambiarlo se recalcula la lista de remisiones disponibles para vincular.'],
        ['nombre' => 'Fecha de recepción', 'explicacion' => 'Fecha en que se recibió la factura. Obligatoria.'],
        ['nombre' => 'Monto total', 'explicacion' => 'El importe total que indica la factura del proveedor. Obligatorio — es el valor que se compara contra el total cotizado de las remisiones vinculadas para calcular la diferencia a revisar.'],
        ['nombre' => 'Moneda', 'explicacion' => 'MXN o USD. Obligatoria.'],
        ['nombre' => 'Partida presupuestal / Ejercicio fiscal', 'explicacion' => 'Datos de referencia contable, opcionales — se capturan si ya se conocen; no accionan ninguna lógica del sistema todavía.'],
        ['nombre' => 'Adjunto (factura)', 'explicacion' => 'La factura digitalizada en PDF o imagen (máximo 5 MB). Opcional al crear — puede subirse ahora o después editando el registro.'],
        ['nombre' => 'Remisiones a vincular', 'explicacion' => 'Lista de casillas con las remisiones (Recepciones) del proveedor seleccionado que esta factura ampara. Solo aparecen las remisiones del proveedor elegido; si no hay proveedor seleccionado, la lista está vacía. No es obligatorio marcar ninguna al crear.'],
        ['nombre' => 'Diferencia a revisar', 'explicacion' => 'Se calcula automáticamente, no se captura a mano: es la comparación exacta (a 2 decimales) entre el "Monto total" de la factura y la suma de lo realmente recibido en las remisiones vinculadas, valorizado al precio unitario cotizado en la Solicitud a Proveedor original. Si no coinciden, la factura queda marcada con el badge "Diferencia a revisar" en el listado, para que alguien la revise manualmente — el sistema no bloquea el avance de estatus por esto, solo lo señala.'],
        ['nombre' => 'Estatus (Recibida / Registrada / Autorizada / Pagada)', 'explicacion' => 'Flujo de 4 pasos, secuencial y sin retroceso, que se avanza con los botones "Registrar", "Autorizar" y "Marcar pagada" del listado. No se puede saltar un paso (por ejemplo, de "Recibida" directo a "Pagada"). "Autorizar" y "Marcar pagada" registran también la fecha en que ocurrió esa transición.'],
        ['nombre' => 'Buscador y filtros del listado', 'explicacion' => 'El buscador encuentra facturas por folio o proveedor. El filtro de estatus acota el listado a un paso específico del flujo. El interruptor "Solo con diferencia a revisar" muestra únicamente las facturas cuyo monto no coincide con lo realmente recibido.'],
    ],
];
