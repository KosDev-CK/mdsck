<?php

return [
    'titulo' => 'Catálogos de Compras',
    'concepto' => 'Agrupa, en pestañas, los catálogos del proceso de compras: Proveedor (las empresas externas a las que se compra, y las áreas internas que surten equipo), Artículo de Solicitud (el catálogo con ficha técnica que se puede pedir y recibir), Categoría (la clasificación que decide qué entra al flujo de compras), Lugar de entrega (los sitios donde se entrega) y Artículos EBS (el mapeo entre cada ítem real de Oracle EBS y un artículo estándar del catálogo). Esta ayuda tiene una pestaña por catálogo con sus reglas y campos.',
    'resuelve' => 'Estandariza los datos para no capturar a mano código, descripción, categoría y ficha técnica en cada compra, y define reglas que usan las demás pantallas: qué SICs y requisiciones se ofrecen en "Solicitud a Proveedores" (categoría "Va a Compras"), qué genera Activos al recibirse (artículo "inventariable"), qué artículos reales se ofrecen en Recepción (tipo de equipo), y a quién se le manda cada pedido (proveedor, externo o interno).',
    'reglas' => [
        'Usa "Desactivar" en vez de borrar cuando algo ya no se debe usar: así no se pierde el historial de solicitudes, recepciones o facturas que lo referencian.',
        '"Eliminar" solo funciona con registros que nunca se usaron; si algo los referencia, el sistema lo rechaza y te dice cuántos registros lo usan. En ese caso desactívalo o fusiónalo.',
        '"Fusionar duplicados" repunta todas las referencias del registro a eliminar hacia el que se conserva y borra el duplicado; no se puede deshacer. No aplica en Artículos EBS.',
        '"Exportar a Excel" descarga la pestaña que estás viendo, respetando la búsqueda activa.',
        'Solo los registros ACTIVOS aparecen como opción al capturar una solicitud nueva.',
    ],
    'secciones' => [
        [
            'titulo' => 'Proveedor',
            'intro' => 'Aquí se dan de alta los proveedores externos y las áreas internas (Telefonía, Infraestructura...) que surten equipo y reciben solicitudes de compra.',
            'reglas' => [
                'Para poder enviar o reenviar una Solicitud a Proveedores el proveedor necesita "Correo de contacto" válido; sin él el sistema avisa y no envía.',
                'PROVEEDOR INTERNO: marca "Proveedor interno" cuando quien surte es un área de la empresa. Es obligatorio capturar su correo de contacto y elegir al menos un ENCARGADO (usuario del sistema). Los encargados ven las solicitudes de su área en "Entregas internas" y confirman cuando entregan; la solicitud sale como "Solicitud interna" en el correo y el PDF.',
                'Un proveedor interno se muestra con la etiqueta "Interno" en el listado. Quitar la marca también quita a sus encargados.',
                'Fusionar duplicados de proveedor repunta los Activos y Mantenimientos del duplicado.',
            ],
            'campos' => [
                ['nombre' => 'Nombre comercial', 'explicacion' => 'Nombre con el que se conoce al proveedor. Obligatorio.'],
                ['nombre' => 'Razón social', 'explicacion' => 'Nombre fiscal completo. Obligatorio, aunque coincida con el comercial.'],
                ['nombre' => 'RFC', 'explicacion' => 'RFC fiscal. Opcional.'],
                ['nombre' => 'Contacto / Teléfono / Correo de contacto', 'explicacion' => 'Datos de la persona de contacto. El correo (si se captura, debe ser válido) es indispensable para enviar solicitudes por correo, y obligatorio en un proveedor interno.'],
                ['nombre' => 'Proveedor interno', 'explicacion' => 'Marca que el proveedor es un área de la empresa. Al activarla aparece la lista de encargados.'],
                ['nombre' => 'Encargados que confirman las entregas', 'explicacion' => 'Usuarios activos del sistema responsables del área. Solo ellos (y un Administrador) ven y confirman sus solicitudes en "Entregas internas". Obligatorio elegir al menos uno si el proveedor es interno.'],
            ],
        ],
        [
            'titulo' => 'Artículo de Solicitud',
            'intro' => 'El catálogo de artículos con ficha técnica (marca, modelo, procesador, RAM, almacenamiento). Un mismo artículo se reutiliza en Solicitud de SIC, Solicitud a Proveedores, Recepción de Proveedor y Registro Manual de Activo.',
            'reglas' => [
                'INVENTARIABLE: solo un artículo marcado así genera un Activo con folio propio por cada unidad recibida, y es el único tipo que se ofrece como artículo en Solicitud de SIC, como artículo real en Recepción y en Registro Manual. Uno no inventariable (telefonía, licencias, consumibles) solo se registra por cantidad.',
                'CATEGORÍA: decide si una SIC con este artículo se ofrece en Solicitud a Proveedores (solo si la categoría está marcada "Va a Compras"). Una SIC sin artículo nunca se ofrece.',
                'TIPO DE EQUIPO: en Recepción se ofrecen como artículo real solo los artículos activos del MISMO tipo de equipo que el solicitado (si se pidió una laptop, solo laptops); si el solicitado no tiene tipo, se acota por su categoría. Captúralo en los artículos genéricos y en los reales.',
                'ARTÍCULOS "(ESTÁNDAR EBS)": son los genéricos a los que se mapean los ítems de EBS (por ejemplo "Laptop Ejecutiva (estándar EBS)"). Son NO inventariables a propósito, no se ofrecen como artículo real en Recepción, y el artículo REAL (marca y modelo) se elige al recibir. Si un ítem de EBS necesita un genérico nuevo, créalo aquí y luego mapéalo en Artículos EBS.',
                'FICHA TÉCNICA: Marca, Modelo, Procesador, RAM y Almacenamiento salen de sus catálogos (Catálogos de Inventario) y precargan los datos del Activo al recibir; si el valor no existe, créalo primero ahí. El select de Modelo muestra todos los modelos activos como "Marca — Modelo".',
                'Fusionar duplicados de artículo repunta también activos, SICs, líneas de solicitud y de recepción y los mapeos de Artículos EBS del duplicado.',
            ],
            'campos' => [
                ['nombre' => 'Código', 'explicacion' => 'Clave interna del artículo. Obligatoria.'],
                ['nombre' => 'Descripción', 'explicacion' => 'Descripción del artículo o servicio. Obligatoria.'],
                ['nombre' => 'Unidad de medida', 'explicacion' => 'Cómo se cuantifica (pieza, caja...). Obligatoria.'],
                ['nombre' => 'Categoría', 'explicacion' => 'Vínculo opcional al catálogo de Categoría. Son las mismas que usa "Presupuesto por Proyecto".'],
                ['nombre' => 'Tipo de equipo', 'explicacion' => 'Vínculo opcional al catálogo de Tipo de Equipo, para acotar los artículos reales en Recepción y relacionar la compra con el inventario.'],
                ['nombre' => 'Marca / Modelo', 'explicacion' => 'Ficha técnica opcional.'],
                ['nombre' => 'Procesador / RAM / Almacenamiento', 'explicacion' => 'Especificaciones opcionales, de sus catálogos.'],
                ['nombre' => 'Es inventariable', 'explicacion' => 'Marca si el artículo representa una unidad física que se da de alta como Activo. En Solicitud a Proveedores determina el indicador "Inventariable" de cada línea.'],
                ['nombre' => 'Estatus', 'explicacion' => 'Activo o inactivo. Solo los activos se ofrecen al capturar.'],
            ],
        ],
        [
            'titulo' => 'Categoría',
            'intro' => 'La clasificación que se asigna a un artículo (y a un artículo de Presupuesto por Proyecto) y que decide si va al flujo de compras.',
            'reglas' => [
                '"VA A COMPRAS" ENCENDIDO: las SICs autorizadas cuyo artículo es de esta categoría, y las líneas de requisiciones de EBS cuyo ítem está mapeado a un artículo de esta categoría, se ofrecen en "Solicitud a Proveedores" y con checkbox en "SIC en EBS".',
                '"VA A COMPRAS" APAGADO: la SIC o línea no se ofrece (sigue disponible por captura manual de folio). Típico para telefonía fija, redes, internet, licencias y servicios.',
                'Ejemplo de configuración: el equipo físico (Laptops/Desktops, Celulares, Multifuncionales, Periféricos y equipo de operación, Accesorios y refacciones) con "Va a Compras" encendido; licencias de software, telefonía fija, redes, internet y demás servicios con "Va a Compras" apagado. Puedes cambiarlo en cualquier momento y el efecto es inmediato.',
                'El nombre se puede cambiar libremente sin afectar nada; la clave interna (slug) se genera una sola vez y nunca cambia.',
                'Una categoría en uso no se puede eliminar; fusiónala con la correcta.',
            ],
            'campos' => [
                ['nombre' => 'Nombre', 'explicacion' => 'Etiqueta visible de la categoría.'],
                ['nombre' => 'Va a Compras', 'explicacion' => 'Decide si lo de esta categoría se ofrece para armar una Solicitud a Proveedores.'],
            ],
        ],
        [
            'titulo' => 'Lugar de entrega',
            'intro' => 'Los sitios físicos donde puede entregarse una línea de una Solicitud a Proveedores (Zurich, CEDA, Sotelo). Se pueden agregar más si hace falta.',
            'reglas' => [
                'Un lugar de entrega AGRUPA ubicaciones de inventario: se asignan desde Catálogos Núcleo → Ubicaciones (campo "Lugar de entrega (Compras)"); la columna "Ubicaciones agrupadas" indica cuántas tiene.',
                'Un sitio sin ubicaciones no puede recibir equipos: Recepción de Proveedor se bloquea hasta asignarle al menos una.',
                'Cada técnico recibe solo lo de su sitio (su "sede de trabajo" en el catálogo de Validadores).',
                'Fusionar duplicados repunta las líneas de solicitud del duplicado.',
            ],
            'campos' => [
                ['nombre' => 'Nombre', 'explicacion' => 'Nombre del sitio de entrega.'],
                ['nombre' => 'Estatus', 'explicacion' => 'Solo los activos aparecen al capturar una línea.'],
            ],
        ],
        [
            'titulo' => 'Artículos EBS',
            'intro' => 'El mapeo entre cada ítem real de Oracle EBS y el artículo estándar del catálogo que le corresponde. Es lo que permite que una requisición de EBS (que no trae clasificación propia) pueda usarse para armar una Solicitud a Proveedores.',
            'reglas' => [
                'Las filas se crean SOLAS: la sincronización agrega un ítem la primera vez que lo ve, "Sin mapear". No hay botón "Nuevo" ni "Eliminar".',
                'Lo único manual es el MAPEO: con el ícono de lápiz eliges el artículo estándar que le corresponde. El mapeo aplica para siempre a ese ítem, pasado y futuro, y la sincronización nunca lo pisa.',
                'Mientras un ítem esté "Sin mapear", las líneas de EBS con ese ítem NO se ofrecen en Solicitud a Proveedores.',
                'Para que se ofrezcan, el artículo elegido debe ser de una categoría "Va a Compras".',
                'El artículo debe existir y estar activo antes de mapear. Si no existe un estándar adecuado, créalo en la pestaña "Artículo de Solicitud" (no inventariable; con tipo de equipo si lo tiene) y luego mapea.',
                'Es el artículo GENÉRICO para pedir; el artículo real (marca y modelo) se elige al recibir la mercancía en Recepción de Proveedor.',
                'Revisa de vez en cuando esta pestaña: cualquier ítem nuevo de EBS aparece sin mapear y sus requisiciones no entran al flujo hasta que lo mapees.',
            ],
            'campos' => [
                ['nombre' => 'Item ID (EBS)', 'explicacion' => 'El identificador real del ítem en Oracle EBS; no editable.'],
                ['nombre' => 'Descripción en EBS', 'explicacion' => 'La última descripción vista en EBS para ese ítem, solo de referencia para identificar a qué artículo estándar corresponde.'],
                ['nombre' => 'Artículo mapeado', 'explicacion' => 'El artículo estándar del catálogo de Artículo de Solicitud al que corresponde (por ejemplo "Laptop Ejecutiva", no la marca real). "Sin mapear" mientras no se elija.'],
            ],
        ],
    ],
];
