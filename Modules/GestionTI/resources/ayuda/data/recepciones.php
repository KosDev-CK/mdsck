<?php

return [
    'titulo' => 'Recepción de Proveedor',
    'concepto' => 'La Recepción de Proveedor registra la llegada física de la mercancía de una Solicitud a Proveedores ya existente (de un proveedor externo o de un área interna). Es la pantalla donde el inventario se vuelve real: por cada unidad de un artículo inventariable que se reciba, el sistema da de alta un Activo con su propio folio, listo para asignarse. No hay botón "Nuevo": toda recepción se registra abriendo su Solicitud a Proveedores desde el listado. Una recepción guardada no se puede editar ni cancelar; si falta algo, se registra como una recepción nueva (la única excepción es la remisión digitalizada, que puede adjuntarse o corregirse después). La ayuda está dividida en pestañas: preparación, recibir, artículo real y series, qué pasa al guardar y los campos.',
    'resuelve' => 'Sin este paso una Solicitud a Proveedores sería solo un pedido en papel. Aquí se confirma cuánto llegó de verdad (puede ser menos de lo pedido o llegar en varios embarques), se capturan los datos que identifican cada equipo físico (serie, marca, modelo, garantía) y se resguarda la remisión del proveedor. Además decide si cada equipo queda reservado para una SIC o libre en stock.',
    'reglas' => [
        'La solicitud se hace casi siempre con un artículo GENÉRICO no inventariable (por ejemplo "Laptop Ejecutiva (estándar EBS)"); aquí se elige el artículo REAL que llegó, pieza por pieza, y de él depende si se da de alta un Activo.',
        'Cada técnico recibe solo lo de su sitio de entrega (su sede de trabajo); una recepción cubre un solo sitio.',
        'Una recepción guardada es definitiva: cantidades, folio, fechas y líneas no se editan ni se cancelan.',
        'RESERVA: un equipo recibido de una línea que viene de una SIC LOCAL queda "Reservado" para esa SIC; el de una línea de requisición de EBS directa, de folio manual o de proyecto queda "En stock".',
        'Solicitudes a un proveedor INTERNO: el listado y el encabezado muestran "Entrega confirmada" o "Área sin confirmar". Puedes recibir aunque el área no haya confirmado; es un control de seguimiento, no un candado.',
    ],
    'secciones' => [
        [
            'titulo' => 'Preparación',
            'intro' => 'Se hace una sola vez, por un administrador.',
            'proceso' => [
                'Catálogos Núcleo → Ubicaciones: asigna a cada ubicación su "Lugar de entrega (Compras)" (Zurich, CEDA o Sotelo).',
                'Catálogos de Inventario → Validador: vincula a cada técnico que recibe con su "Usuario del sistema" y, si trabaja en un solo sitio, su "Sede de trabajo".',
                'Catálogos de Compras → Artículo de Solicitud: captura el "Tipo de equipo" de los artículos genéricos y de los reales, para que el buscador ofrezca solo laptops cuando se pidió una laptop, PCs cuando se pidió una PC, etc.',
                'Catálogos de Compras → Artículos EBS: ítems mapeados al artículo estándar correcto (si no, la requisición no llega a Solicitud a Proveedores).',
            ],
        ],
        [
            'titulo' => 'Recibir',
            'proceso' => [
                'ANTES DE RECIBIR: la solicitud se envía al proveedor por correo (o se imprime su PDF). El PDF trae un QR del folio y, por línea, el lugar de entrega y un código de barras corto (L12-1, L12-2...). El proveedor puede entregar en varias remisiones; cada entrega es una recepción aparte.',
                'ABRIR LA SOLICITUD: el listado muestra las solicitudes (las pendientes primero, con "Vencida" si pasó la fecha comprometida). Busca por folio o proveedor, o escanea el QR en el campo de búsqueda y se abre sola. Si sigue "Solicitada" o "Parcialmente recibida" verás "Registrar nueva recepción"; si ya está "Recibida", "Facturada" o "Cancelada" solo se consulta.',
                'QUIÉN RECIBE Y DÓNDE: recibe el técnico que tiene la sesión iniciada (validado contra el catálogo de técnicos). Un Administrador puede capturar por un técnico eligiéndolo en "Recibido por", y queda registrado a nombre del técnico con el dato de quién lo capturó. Con sede de trabajo el sitio queda fijo; sin ella, lo eliges. Elige la "Ubicación destino" entre las de ese sitio (si solo tiene una se toma sola).',
                'DATOS DE LA ENTREGA: captura el folio de la remisión que te firman en ESA entrega y la fecha real de recepción (no puede ser futura ni anterior a la solicitud). Adjunta la remisión digitalizada si ya la tienes (opcional, se puede adjuntar después).',
                'QUÉ LLEGÓ: "Cantidad a recibir ahora" arranca en 0; captura solo lo que llegó (no puede exceder lo pendiente), usa "Recibir todo lo pendiente de este sitio" o escanea los equipos (cada serie suma una pieza). Lo que no llegó queda pendiente para la siguiente remisión.',
                'SIGUIENTES ENTREGAS Y DOCUMENTOS: repite desde "Abrir la solicitud". En el historial puedes generar el Acta de Entrega-Recepción en PDF, ver la remisión, adjuntarla si faltaba o quitarla si era la equivocada (el archivo no se borra).',
            ],
        ],
        [
            'titulo' => 'Artículo real y series',
            'reglas' => [
                'El buscador de artículo real acepta las palabras que recuerdes en cualquier orden, sin importar mayúsculas, acentos o guiones ("lenovo 20rv" encuentra "Lenovo 20 RV"), muestra 10 resultados y solo ofrece artículos activos del mismo tipo de equipo que el solicitado; los genéricos de EBS no se ofrecen.',
                'El artículo de la línea se aplica a todas sus piezas; si llegan distintas (por ejemplo 2 laptops de modelos diferentes), cambia el artículo de cada pieza en "Piezas a recibir". Con un artículo genérico es obligatorio elegir el real de cada pieza.',
                'La pantalla indica si cada pieza se dará de alta como activo inventariable o solo se registra por cantidad.',
                'Cada pieza inventariable pide número de serie (obligatorio) y service tag si aplica. Marca, modelo y tipo salen del artículo (solo se piden si no los trae) y la garantía es opcional y se comparte por línea.',
                'Un número de serie repetido en la misma recepción se rechaza, y si ya existe en el inventario se avisa. Revisa siempre lo que quedó escrito.',
                'Si una requisición de EBS trae varios artículos, cada uno es una línea del pedido con su folio "línea n de N"; se recibe cada una por separado.',
            ],
            'proceso' => [
                'Escanea el código de barras de la línea (el de debajo de cada línea del PDF): la línea queda activa y resaltada. Si aún no elegías sitio, el de esa línea se elige solo; si eres administrador y aún no elegiste técnico, se activa al elegirlo.',
                'Escanea el código "S/N" de la etiqueta de cada equipo: el número se coloca solo en la siguiente pieza sin serie.',
                'Funciona con lector USB (el cursor puede quedarse siempre en "Escanear") o con el botón "Escanear" que abre la cámara del celular (requiere HTTPS y el permiso de cámara); las etiquetas traen varios códigos, toca el de la serie.',
            ],
        ],
        [
            'titulo' => 'Al guardar',
            'reglas' => [
                'Se da de alta un Activo por cada pieza inventariable, con folio de inventario propio, su artículo, marca, modelo y datos técnicos, en la ubicación destino.',
                'Estatus del activo: "Reservado" si la línea trae una SIC local (queda apartado para ese empleado), "En stock" si no (SIC de EBS sin SIC local, folio manual o proyecto).',
                'Las piezas no inventariables se registran solo por cantidad (telefonía, licencias, consumibles).',
                'La solicitud pasa a "Parcialmente recibida" o a "Recibida" si ya se completó todo lo pedido.',
                'ASIGNACIÓN: "Asignación de Activo" exige una Solicitud de SIC local autorizada para elegir a la persona; un activo en stock puede asignarse a cualquier SIC autorizada sin asignar, y uno reservado solo a la SIC para la que se reservó. Para entregar un equipo de una requisición de EBS sin SIC local, captura su Solicitud de SIC con el folio de EBS (se vincula sola) y asígnalo desde stock.',
                'Una recepción guardada no se edita ni se cancela; si falta algo se registra en una recepción nueva.',
            ],
        ],
        [
            'titulo' => 'Campos',
            'campos' => [
                ['nombre' => 'Solicitud a proveedor', 'explicacion' => 'El pedido contra el que se registra la llegada; es la que abres desde el listado. Solo admite recepciones nuevas en estatus "Solicitada" o "Parcialmente recibida".'],
                ['nombre' => 'Entrega prometida', 'explicacion' => 'Fecha comprometida por el proveedor (se captura en la Solicitud a Proveedores; por defecto 3 días después de la solicitud). En el listado una pendiente con la fecha vencida se marca "Vencida", y en el historial cada recepción dice "A tiempo" o "Con retraso de N d" — base para evaluar al proveedor.'],
                ['nombre' => 'Filtro de estatus', 'explicacion' => 'Todas, Pendientes de recibir (solicitadas + parcialmente recibidas) o un estatus puntual.'],
                ['nombre' => 'Entrega confirmada / Área sin confirmar', 'explicacion' => 'Solo en solicitudes a un proveedor interno: indica si el área ya confirmó en "Entregas internas" que entregó. Es informativo, no bloquea la recepción.'],
                ['nombre' => 'Folio de remisión', 'explicacion' => 'El folio de la remisión o guía tal como la emitió el proveedor (no es un folio del sistema). Obligatorio.'],
                ['nombre' => 'Fecha de recepción', 'explicacion' => 'Fecha en que llegó físicamente la mercancía. Obligatoria.'],
                ['nombre' => 'Recibido por', 'explicacion' => 'El técnico que recibe: quien tiene la sesión iniciada, validado contra el catálogo de técnicos (Validador con "Usuario del sistema" igual a tu usuario). Solo un Administrador elige a mano al técnico por el que captura.'],
                ['nombre' => 'Sitio de entrega que recibes', 'explicacion' => 'Zurich, CEDA o Sotelo (catálogo "Lugar de entrega"), igual a la "sede de trabajo" del técnico si la tiene. Solo se reciben las líneas de ese sitio y los equipos quedan en la "Ubicación destino" elegida entre las que agrupa; si el sitio no tiene ubicaciones, la recepción se bloquea hasta asignarlas en Catálogos Núcleo → Ubicaciones.'],
                ['nombre' => 'Observaciones', 'explicacion' => 'Cualquier nota sobre esta recepción. Opcional.'],
                ['nombre' => 'Remisión digitalizada', 'explicacion' => 'El comprobante de entrega, subiendo un archivo o eligiendo uno de SharePoint (la lista excluye los ya vinculados a otra recepción). Opcional al crear; después aparece "Adjuntar remisión" en el historial, y una vez adjunta, "Ver remisión" y "Quitar" (corrige un archivo equivocado sin borrar el original).'],
                ['nombre' => 'Cantidad a recibir ahora (por línea)', 'explicacion' => 'Cuánto de esa línea llegó en ESTA remisión. Arranca en 0; se captura a mano, con "Recibir todo lo pendiente de este sitio" o escaneando series. No puede exceder lo pendiente.'],
                ['nombre' => 'Artículo recibido (por línea y por pieza)', 'explicacion' => 'El artículo del catálogo que realmente llegó. El de la línea se aplica a sus piezas y cada pieza puede tener el suyo. Obligatorio elegir el real cuando se pidió un genérico. Es este valor (no el solicitado) el que queda vinculado al Activo y a la línea de la recepción; la Solicitud original no se modifica.'],
                ['nombre' => 'Marca / Modelo / Tipo de equipo (por línea inventariable)', 'explicacion' => 'Se precargan del artículo recibido; solo se capturan a mano cuando el artículo no los trae (la marca es obligatoria en ese caso, modelo opcional).'],
                ['nombre' => 'Inicio / Fin de garantía', 'explicacion' => 'Vigencia de la garantía, opcional, compartida por todas las unidades de la línea en esta recepción.'],
                ['nombre' => 'Número de serie (por unidad)', 'explicacion' => 'Serie física de esa unidad. Obligatoria en cada unidad de una línea inventariable y no puede repetirse en la misma recepción. Admite captura manual, lector USB o la cámara del celular.'],
                ['nombre' => 'Service tag (por unidad)', 'explicacion' => 'Identificador adicional del fabricante (por ejemplo el Service Tag de Dell). Opcional.'],
            ],
        ],
    ],
];
