<?php

return [
    'titulo' => 'Catálogos de Compras',
    'concepto' => 'Agrupa, en pestañas, dos catálogos usados en el proceso de compras: Proveedor (las empresas externas a las que se les compra equipo, refacciones o servicio) y Artículo de Solicitud (el catálogo con ficha técnica completa — marca, modelo, procesador, RAM, almacenamiento — que se puede pedir dentro de una Solicitud de SIC, una Solicitud a Proveedor, una Recepción de Proveedor o un Registro Manual de Activo).',
    'resuelve' => 'Estandariza los datos de contacto de cada proveedor (para poder ubicarlo rápido al levantar una solicitud o dar seguimiento a una factura) y evita que cada compra o alta de inventario tenga que volver a capturar a mano el código, la descripción, la categoría y la ficha técnica de un artículo — define un "Artículo" completo (ej. "Laptop Core i7 16GB 512GB SSD") una sola vez, y ese mismo catálogo se reutiliza en Compras (Solicitud de SIC, Solicitud a Proveedor, Recepción de Proveedor) y en Inventario (Registro Manual de Activo), reduciendo errores de captura y permitiendo reportar por categoría o por tipo de equipo relacionado.',
    'proceso' => [
        'Elige la pestaña (Proveedor o Artículo de Solicitud).',
        'Da clic en "Nuevo" o en "Editar" sobre un registro existente.',
        'Completa el formulario según el catálogo (ver el detalle de cada campo más abajo) y guarda.',
        'Usa "Desactivar" en vez de borrar cuando un proveedor o artículo ya no se debe seguir usando — así no se pierde el historial de solicitudes o facturas que ya lo referencian.',
        'Si detectas que el mismo proveedor o artículo quedó capturado más de una vez, usa "Fusionar duplicados" en vez de editar o borrar a mano.',
        'Usa "Exportar a Excel" en cualquier momento para descargar el catálogo de la pestaña activa.',
    ],
    'campos' => [
        ['nombre' => 'Nombre comercial (Proveedor)', 'explicacion' => 'Nombre con el que se conoce comercialmente al proveedor. Obligatorio.'],
        ['nombre' => 'Razón social (Proveedor)', 'explicacion' => 'Nombre fiscal completo del proveedor. Obligatorio, aunque coincida con el nombre comercial.'],
        ['nombre' => 'RFC (Proveedor)', 'explicacion' => 'RFC fiscal del proveedor. Opcional.'],
        ['nombre' => 'Contacto / Teléfono de contacto / Correo de contacto (Proveedor)', 'explicacion' => 'Datos de la persona de contacto en el proveedor — útiles para dar seguimiento a una solicitud o aclarar una factura. Los tres son opcionales.'],
        ['nombre' => 'Código (Artículo de Solicitud)', 'explicacion' => 'Clave interna con la que se identifica el artículo. Obligatoria.'],
        ['nombre' => 'Descripción (Artículo de Solicitud)', 'explicacion' => 'Descripción del artículo o servicio. Obligatoria.'],
        ['nombre' => 'Unidad de medida (Artículo de Solicitud)', 'explicacion' => 'Cómo se cuantifica el artículo, por ejemplo "pieza" o "caja". Obligatoria.'],
        ['nombre' => 'Categoría (Artículo de Solicitud)', 'explicacion' => 'Una de las 11 categorías fijas del sistema (Celulares, Telefonía fija, Laptops/Desktops, Multifuncionales, Redes, Comunicación, Internet, Infraestructura, VPN, Ciberseguridad, Antivirus) — las mismas que usa "Presupuesto por Proyecto", para mantener una sola clasificación en todo el módulo. Opcional.'],
        ['nombre' => 'Tipo de equipo (Artículo de Solicitud)', 'explicacion' => 'Vínculo opcional al catálogo "Tipo de Equipo" (de Catálogos de Inventario), útil cuando el artículo representa directamente un tipo de equipo — por ejemplo, para relacionar una partida de compra con el inventario de Laptops.'],
        ['nombre' => 'Marca / Modelo (Artículo de Solicitud)', 'explicacion' => 'Ficha técnica del artículo, opcional. El select de Modelo muestra todos los modelos activos etiquetados "Marca — Modelo" (no se filtra dinámicamente al elegir la Marca) — se puede corregir si la combinación no coincide.'],
        ['nombre' => 'Procesador / RAM / Almacenamiento (Artículo de Solicitud)', 'explicacion' => 'Especificaciones técnicas del artículo, opcionales — seleccionables desde los catálogos "Procesador", "RAM" y "Almacenamiento" (Catálogos de Inventario), no texto libre. Si el valor que necesitas no existe todavía, créalo primero ahí.'],
        ['nombre' => 'Es inventariable (Artículo de Solicitud)', 'explicacion' => 'Marca si este artículo representa una unidad física que se puede dar de alta como Activo real (con folio de inventario propio) — solo los artículos marcados así aparecen como opción en Solicitud de SIC, Recepción de Proveedor y Registro Manual de Activo. Los artículos no inventariables (por ejemplo, telefonía o licencias) se usan solo para el trámite de compra, sin generar un Activo.'],
        ['nombre' => 'Estatus (Activo/Inactivo)', 'explicacion' => 'Activa o desactiva el registro. Solo los proveedores y artículos activos aparecen como opción al capturar una solicitud nueva.'],
        ['nombre' => 'Fusionar duplicados', 'explicacion' => 'Selecciona el "registro a eliminar" y el "registro que se conserva": el sistema repunta automáticamente las referencias del primero hacia el segundo (por ejemplo, Activos o Mantenimientos que apuntaban al Proveedor duplicado) y borra el duplicado. No se puede deshacer. Para Artículo de Solicitud, hoy ninguna otra tabla lo referencia, así que fusionar solo elimina el duplicado.'],
    ],
];
