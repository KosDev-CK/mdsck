<?php

return [
    'titulo' => 'SIC en EBS',
    'concepto' => 'Esta pantalla es un espejo de las requisiciones (SIC) reales de Oracle EBS: no captura datos nuevos ni llama a EBS cuando la abres. La información llega sola, todas las madrugadas, con procesos programados que se conectan a Oracle EBS. Además de consultar, es uno de los dos puntos de partida para armar una Solicitud a Proveedores (marcas las requisiciones elegibles y se navega al formulario con ellas precargadas) y el lugar donde se marcan como "atendidas" las líneas que ya se resolvieron fuera del sistema. La ayuda está dividida en pestañas: cómo llega la información, vinculación con la SIC local, qué es elegible para compra, atención de líneas y filtros.',
    'resuelve' => 'Permite confirmar si una SIC ya existe formalmente en Oracle EBS y en qué estatus real va (en proceso, aprobada, rechazada) sin entrar a EBS, vincularla con la Solicitud de SIC local correspondiente, y convertir las requisiciones aprobadas en pedidos a proveedor sin volver a capturarlas.',
    'reglas' => [
        'La pantalla es de solo lectura sobre lo sincronizado: el estatus, las fechas, las líneas y las notas cambian únicamente cuando la sincronización trae datos nuevos.',
        'Una requisición se ofrece para compra solo si está APROBADA en EBS y sus líneas cumplen las reglas de la pestaña "Elegibles para compra".',
        'Una requisición vinculada a una SIC local se trata como esa SIC; una sin SIC local se trata directamente, línea por línea.',
    ],
    'secciones' => [
        [
            'titulo' => 'Cómo llega la información',
            'intro' => 'No hay nada que llenar para que aparezcan las requisiciones: se sincronizan solas.',
            'reglas' => [
                'Todos los días a las 04:00 corren dos sincronizaciones, primero "creadas" y luego "aprobadas": la primera trae todas las requisiciones creadas el día anterior (sin importar su estatus) con su cabecera, SUS LÍNEAS y SUS NOTAS; la segunda trae las que se aprobaron o rechazaron ese día (actualiza estatus, fecha y autorizador, y las notas).',
                'A las 07:00 corre un reintento automático de los días que hubieran fallado (por ejemplo, si EBS aún no tenía listo el día a las 04:00).',
                'Un día SIN requisiciones creadas o aprobadas no es un error: EBS responde "sin información" y el sistema lo toma como día vacío (fines de semana, feriados).',
                'Las notas (solicitante, a quién se asigna, justificación, área, directores, lugar de entrega, etc.) llegan desde la primera importación, aunque la requisición aún no esté aprobada.',
                'Si una requisición de EBS todavía no aparece, normalmente basta esperar a la siguiente sincronización; no es un dato faltante en esta pantalla.',
                'Existe un proceso de uso puntual (lo corre un administrador en el servidor) para traer historial de fechas pasadas.',
            ],
        ],
        [
            'titulo' => 'Vincular con la SIC local',
            'intro' => 'Cada requisición de EBS puede ligarse a una Solicitud de SIC local (la que capturaste en "Solicitud de SIC"). Esa liga es la que permite que el equipo se reserve para el empleado correcto y se asigne después.',
            'reglas' => [
                'La vinculación es automática cuando el "Folio SIC (EBS)" capturado en la Solicitud de SIC coincide exactamente con el código de la requisición.',
                'Cuando no coincidió (folio nunca capturado, con error de dedo, o capturado antes de la integración) usa "Vincular": busca por folio de SIC, nombre del solicitante o folio del ticket, elige la SIC y confirma. El estatus local de la SIC se actualiza con lo que reporta EBS.',
                'Una Solicitud de SIC ya vinculada a otra requisición no se puede reasignar.',
                'Una SIC vinculada deja de autorizarse o rechazarse a mano: su estatus lo maneja la sincronización.',
            ],
            'proceso' => [
                'Si una fila dice "No vinculada" y sabes a qué Solicitud de SIC corresponde, da clic en el ícono de liga.',
                'Escribe en el buscador del modal para encontrar la Solicitud de SIC correcta.',
                'Elígela y confirma con "Vincular".',
            ],
        ],
        [
            'titulo' => 'Elegibles para compra',
            'intro' => 'El checkbox de la primera columna aparece solo en filas que se pueden usar para armar una Solicitud a Proveedores. Es el mismo criterio de la tabla de "Solicitud a Proveedores".',
            'reglas' => [
                'CAMINO 1 — SIC LOCAL: la SIC vinculada está "Autorizada", tiene un artículo asignado de una categoría marcada "Va a Compras", y ninguna solicitud la ha recogido.',
                'CAMINO 2 — SIN SIC LOCAL: la requisición está APPROVED en EBS, no está vinculada a ninguna SIC local, y tiene al menos UNA línea disponible: su ítem está mapeado en Catálogos de Compras → Artículos EBS a un artículo de categoría "Va a Compras", y la línea no la tomó otra solicitud ni se marcó como atendida.',
                'Si una requisición tiene varias líneas, basta con que una esté disponible para que aparezca el checkbox; al crear la solicitud llegan TODAS sus líneas disponibles como filas separadas.',
                'Una requisición cuyos ítems son licencias, telefonía u otras categorías sin "Va a Compras" no se ofrece; se marca desde Catálogos de Compras → Categoría si algún día debe ofrecerse.',
                'Los ítems nuevos de EBS aparecen en Artículos EBS "Sin mapear" y sus requisiciones no se ofrecen hasta mapearlos.',
            ],
            'proceso' => [
                'Marca de 1 a N filas con checkbox, o usa "Seleccionar todas" (solo las elegibles de la página visible) y "Deseleccionar todas".',
                'Da clic en "Crear Solicitud a Proveedor": el botón muestra cuántas llevas y abre el formulario con esas filas ya seleccionadas.',
                'Usa el filtro "Solo SICs autorizadas y seleccionables" para ver solo las que se pueden elegir.',
            ],
        ],
        [
            'titulo' => 'Atención de líneas',
            'intro' => 'A veces una requisición ya se atendió por fuera: se compró y recibió antes de usar el sistema, o no necesita ingreso al inventario. Para que deje de estorbar en Solicitud a Proveedores se marca como atendida.',
            'reglas' => [
                'El ícono de palomita ("Atención de líneas") aparece solo en requisiciones APROBADAS sin SIC local.',
                'Marcar una línea como atendida exige un MOTIVO y la saca de la tabla de Solicitud a Proveedores; queda registrado quién lo marcó y cuándo, y se ve en el detalle de la requisición.',
                'Una línea ya tomada por una solicitud no se puede marcar (ya está relacionada). Una línea atendida se puede volver a ofrecer con "Volver a ofrecerla".',
                'Marcar como atendida NO da de alta equipos en el inventario. Si esas piezas deben entrar al inventario con su artículo real, crea una Solicitud a Proveedores con el proveedor real, GUÁRDALA SIN ENVIAR y haz su Recepción: así quedan con trazabilidad completa y salen del pool. Usa "atendida" para lo que no necesita ingreso (licencias, servicios o equipos ya registrados por otra vía).',
            ],
            'proceso' => [
                'Da clic en el ícono de palomita de la requisición.',
                'Marca las líneas disponibles que ya se atendieron.',
                'Escribe el motivo (obligatorio, por ejemplo "ya se compró y recibió en septiembre").',
                'Confirma con "Marcar como atendidas".',
            ],
        ],
        [
            'titulo' => 'Filtros y columnas',
            'campos' => [
                ['nombre' => 'Botón "Filtros"', 'explicacion' => 'Abre un panel lateral con todos los filtros: búsqueda general, estatus, vinculación, asignación, "Solo SICs autorizadas y seleccionables" y los rangos de fechas. Se cierra con "Ocultar filtros".'],
                ['nombre' => 'Código', 'explicacion' => 'El folio de la requisición tal como existe en Oracle EBS (por ejemplo "6489").'],
                ['nombre' => 'Descripción', 'explicacion' => 'Descripción de la requisición tal como se capturó en EBS.'],
                ['nombre' => 'Estatus', 'explicacion' => 'El estatus real que reporta EBS (por ejemplo IN PROCESS, APPROVED, REJECTED, INCOMPLETE). No es editable; cambia cuando la sincronización trae un valor nuevo.'],
                ['nombre' => 'Fecha / Fecha de autorización', 'explicacion' => 'Fecha de creación y fecha en que EBS aprobó o rechazó la requisición. La segunda muestra "—" si aún no se resuelve.'],
                ['nombre' => 'Vinculada', 'explicacion' => 'A qué Solicitud de SIC (y ticket) está asociada, o "No vinculada". Solo refleja la liga con la SIC local; para saber si ya se usó en una solicitud de compra revisa "Id solicitud prov.".'],
                ['nombre' => 'Id solicitud prov.', 'explicacion' => 'El folio de la Solicitud a Proveedores que ya recogió la requisición (por su SIC local o directamente), como botón que abre su detalle. Si la requisición trae varias líneas y solo algunas se tomaron, muestra la primera solicitud; "—" si ninguna la ha tomado.'],
                ['nombre' => 'Checkbox / Seleccionar todas / Crear Solicitud a Proveedor', 'explicacion' => 'Ver la pestaña "Elegibles para compra".'],
                ['nombre' => 'Filtro de búsqueda general', 'explicacion' => 'Busca por coincidencia parcial en folio, descripción, quién creó, quién autorizó y el contenido de sus notas.'],
                ['nombre' => 'Filtro de estatus', 'explicacion' => 'Muestra solo los estatus que existen hoy en la réplica local.'],
                ['nombre' => 'Filtro de vinculación', 'explicacion' => 'Solo las ya vinculadas a una SIC local o solo las que no (no mira si ya se usaron en una solicitud de compra).'],
                ['nombre' => 'Filtro "Asignación a Solicitud de compra"', 'explicacion' => 'Solo las requisiciones ya recogidas por alguna Solicitud a Proveedores, o solo las que no.'],
                ['nombre' => 'Filtro "Solo SICs autorizadas y seleccionables"', 'explicacion' => 'Acota a las filas que tienen checkbox de selección disponible.'],
                ['nombre' => 'Desde / Hasta y Autorizada desde / hasta', 'explicacion' => 'Rangos de fechas de creación y de autorización (aprobación o rechazo) en EBS.'],
                ['nombre' => 'Detalle (clic en código o descripción)', 'explicacion' => 'Ventana de solo lectura con fechas, quién creó y quién autorizó, la SIC vinculada, las líneas (artículo, cantidad, precio, y si fueron atendidas con su motivo) y las notas tal como las trajo EBS. "Copiar notas" las pasa al portapapeles.'],
                ['nombre' => 'Descargar Excel', 'explicacion' => 'Genera un .xlsx con el listado completo (sin paginar) respetando búsqueda, estatus, vinculación y fechas; no considera el filtro de asignación ni "Solo seleccionables".'],
            ],
        ],
    ],
];
