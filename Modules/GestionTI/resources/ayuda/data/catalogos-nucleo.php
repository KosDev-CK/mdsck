<?php

return [
    'titulo' => 'Catálogos Núcleo',
    'concepto' => 'Agrupa, en una sola pantalla con pestañas, los catálogos base que comparte todo el módulo: Empresas, Ubicaciones, Áreas, Unidades de Negocio, Puestos y Centros de Costo. Son los datos de referencia que se usan en Empleados, Activos, Proyectos de presupuesto y otras pantallas — en vez de capturarlos como texto libre en cada lugar.',
    'resuelve' => 'Sin este catálogo, cada pantalla del módulo tendría que capturar "Contabilidad", "Piso 3", etc. como texto libre, lo que produce variantes del mismo dato (mayúsculas distintas, espacios de más) que rompen reportes y filtros. Aquí se captura una sola vez y el resto del módulo lo referencia por catálogo — con la opción de "Fusionar duplicados" para corregir el caso en que, aun así, alguien haya dado de alta el mismo registro dos veces.',
    'proceso' => [
        'Elige la pestaña del catálogo que quieres administrar (Empresas, Ubicaciones, Áreas, Unidades de Negocio, Puestos o Centros de Costo).',
        'Da clic en "Nuevo" para crear un registro, o en "Editar" sobre uno existente.',
        'Completa el formulario — los campos varían un poco según la pestaña, ver la explicación de cada uno más abajo — y guarda.',
        'Usa "Desactivar" en vez de borrar cuando un registro ya no debe usarse: los selects del resto del módulo solo muestran registros activos, pero el historial que ya lo referencia no se pierde.',
        'Si detectas que el mismo registro quedó capturado dos veces (por ejemplo, "Recursos Humanos" y "RRHH" como la misma área), usa "Fusionar duplicados" en vez de editar o borrar a mano.',
        'Usa "Exportar a Excel" en cualquier momento para descargar el catálogo de la pestaña activa.',
    ],
    'campos' => [
        ['nombre' => 'Nombre / Razón social', 'explicacion' => 'Nombre formal del registro. En la pestaña Empresas se llama "Razón social" (el nombre fiscal); en las demás pestañas es simplemente "Nombre". Obligatorio en todos los casos.'],
        ['nombre' => 'Nombre comercial (solo Empresas)', 'explicacion' => 'Nombre comercial de la empresa, distinto de la razón social. Obligatorio.'],
        ['nombre' => 'Nombre conocido (Ubicaciones, Áreas, Unidades de Negocio, Puestos)', 'explicacion' => 'Cómo se le conoce internamente si es distinto del nombre formal (por ejemplo, el apodo interno de una ubicación). Opcional.'],
        ['nombre' => 'RFC (solo Empresas)', 'explicacion' => 'RFC fiscal de la empresa. Opcional.'],
        ['nombre' => 'Código (solo Centros de Costo)', 'explicacion' => 'Clave corta del centro de costo, tal como se usa en EBS/contabilidad. Obligatoria.'],
        ['nombre' => 'Empresa (solo Centros de Costo)', 'explicacion' => 'Empresa (de la pestaña Empresas) a la que pertenece el centro de costo. Obligatoria — primero debe existir la empresa.'],
        ['nombre' => 'Estatus (Activo/Inactivo)', 'explicacion' => 'Activa o desactiva el registro sin borrarlo. Los selects de otras pantallas (por ejemplo, "Empresa" al capturar un Empleado) solo listan registros activos.'],
        ['nombre' => 'Fusionar duplicados', 'explicacion' => 'Selecciona el "registro a eliminar" y el "registro que se conserva": el sistema repunta automáticamente todas las referencias del primero hacia el segundo (por ejemplo, todos los Empleados que apuntaban a la Ubicación duplicada) y después borra el duplicado. La acción no se puede deshacer. En Centros de Costo, hoy ningún otro catálogo lo referencia, así que fusionar solo elimina el duplicado sin repuntar nada.'],
    ],
];
