<?php

return [
    'titulo' => 'Categorías que van a Compra',
    'concepto' => 'Decide, de las 11 categorías del catálogo de Artículo (Celulares, Telefonía fija, Laptops/Desktops, Multifuncionales, Redes, Comunicación, Internet, Infraestructura, VPN, Ciberseguridad, Antivirus), cuáles representan una compra real de equipo físico que debe pasar por "Solicitud a Proveedores". Es una pantalla de configuración pura, sin listado ni historial — un solo interruptor por categoría, siempre sobre el mismo registro de configuración.',
    'resuelve' => 'Cuando una SIC queda autorizada, según el producto solicitado se despacha a Compras (laptops, PCs, impresoras, etc. — equipo físico real) o se reporta a otra área sin pasar por Compras (telefonía fija/celular, licencias de Office, licencias de programas en general, correo 365 — servicios, no compras de equipo). Esta pantalla es la que decide esa división: el selector de "SICs autorizadas y disponibles" de "Solicitud a Proveedores" solo ofrece SICs cuyo Artículo vinculado pertenezca a una categoría marcada aquí. Una categoría sin marcar no desaparece del catálogo de Artículo ni deja de poder usarse en otras pantallas — simplemente sus SICs nunca se ofrecen en ese selector, siguen su seguimiento por otra vía. El default al sembrar el módulo es vacío a propósito: mientras nadie configure nada aquí, ninguna SIC aparece en el selector (sigue disponible solo por captura manual de folio), para no asumir una división de categorías que nadie confirmó todavía.',
    'proceso' => [
        'Marca la casilla de cada categoría de Artículo que represente una compra real de equipo físico y deba pasar por Compras.',
        'Da clic en "Guardar".',
        'Los cambios aplican de inmediato: la próxima vez que se abra "Solicitud a Proveedores", el selector de SICs disponibles ya refleja la configuración nueva.',
    ],
    'campos' => [
        ['nombre' => 'Celulares', 'explicacion' => 'Marca esta casilla si la compra de celulares debe generar una Solicitud a Proveedores.'],
        ['nombre' => 'Telefonía fija', 'explicacion' => 'Típicamente un servicio, no una compra de equipo — normalmente se deja sin marcar.'],
        ['nombre' => 'Laptops/Desktops', 'explicacion' => 'Equipo de cómputo físico — normalmente se marca.'],
        ['nombre' => 'Multifuncionales', 'explicacion' => 'Impresoras/escáneres físicos — normalmente se marca.'],
        ['nombre' => 'Redes', 'explicacion' => 'Equipo de red físico (switches, access points, etc.) si aplica a un flujo de compra.'],
        ['nombre' => 'Comunicación', 'explicacion' => 'Depende de si el equipo/servicio específico requiere compra formal a proveedor.'],
        ['nombre' => 'Internet', 'explicacion' => 'Típicamente un servicio, no una compra de equipo — normalmente se deja sin marcar.'],
        ['nombre' => 'Infraestructura', 'explicacion' => 'Equipo de infraestructura física si aplica a un flujo de compra.'],
        ['nombre' => 'VPN', 'explicacion' => 'Típicamente licenciamiento/servicio — normalmente se deja sin marcar.'],
        ['nombre' => 'Ciberseguridad', 'explicacion' => 'Depende de si el producto específico es equipo físico o licenciamiento.'],
        ['nombre' => 'Antivirus', 'explicacion' => 'Típicamente licenciamiento — normalmente se deja sin marcar.'],
    ],
];
