<?php

return [
    'titulo' => 'Configuración de Almacenamiento',
    'concepto' => 'Decide, por cada tipo de documento digitalizado que se sube en el módulo (responsiva de asignación, remisión de proveedor, SIC, factura, orden de servicio), si el archivo se guarda en SharePoint (vía Microsoft Graph) o en el disco local del servidor (el comportamiento histórico, que sigue siendo el respaldo para cualquier tipo no marcado). Es una pantalla de configuración pura, sin listado ni historial — un solo interruptor por tipo de documento, siempre sobre el mismo registro de configuración.',
    'resuelve' => 'Permite migrar el almacenamiento de documentos a SharePoint de forma gradual y reversible, tipo por tipo, sin necesidad de otro despliegue: se activa un tipo en cuanto su carpeta en SharePoint ya está lista, y se puede desactivar en cualquier momento para regresar a guardarlo localmente. Los documentos ya subidos antes de un cambio de configuración no se mueven ni se ven afectados — el interruptor solo determina el destino de la PRÓXIMA subida de ese tipo. IMPORTANTE: activar un tipo de documento SIN que su carpeta de SharePoint esté configurada hace que falle la subida — el sistema no lo guarda silenciosamente en ningún lado ni lo fuerza al disco local, deliberadamente no se intenta ese "respaldo silencioso" para que nunca se asuma que un documento quedó en SharePoint cuando en realidad no había dónde ponerlo. Hoy esa falla no se traduce todavía en un mensaje amigable para quien sube el documento — aparece como un error genérico de la aplicación —, así que si activas un tipo, confirma primero con el responsable de la integración que su carpeta ya existe; si alguien reporta ese error al subir un documento, desactiva el tipo aquí mientras se termina de configurar su carpeta.',
    'proceso' => [
        'Marca la casilla de cada tipo de documento que quieras enviar a SharePoint en vez de guardarlo en este servidor.',
        'Da clic en "Guardar".',
        'Antes de marcar un tipo, confirma con el responsable de la integración que su carpeta correspondiente ya está configurada en SharePoint (ver docs/sharepoint-graph-integracion.md) — un tipo activado sin carpeta configurada hace que la subida falle con un error genérico, no con un aviso amigable (ver la advertencia del campo siguiente).',
    ],
    'campos' => [
        ['nombre' => 'Adjunto de Solicitud de SIC (sic)', 'explicacion' => 'Documentos adjuntos a una Solicitud Interna de Compra. Al día de hoy este tipo no tiene una carpeta de SharePoint confirmada — actívalo solo cuando esa carpeta exista.'],
        ['nombre' => 'Responsiva de Asignación de Activo (responsiva)', 'explicacion' => 'El documento firmado cuando se asigna un equipo a un empleado. Es uno de los tipos previstos para activarse en SharePoint en cuanto el permiso de Azure quede concedido.'],
        ['nombre' => 'Remisión de Recepción de Proveedor (remision_proveedor)', 'explicacion' => 'El comprobante de un proveedor al recibir mercancía. Es el otro tipo previsto para activarse en SharePoint junto con "Responsiva".'],
        ['nombre' => 'Factura (factura)', 'explicacion' => 'Documentos de facturación de proveedores. Sin carpeta de SharePoint confirmada todavía — no lo actives hasta que exista.'],
        ['nombre' => 'Orden de Servicio de Mantenimiento (orden_servicio)', 'explicacion' => 'Documento de la orden de trabajo de un mantenimiento. Sin carpeta de SharePoint confirmada todavía — no lo actives hasta que exista.'],
    ],
];
