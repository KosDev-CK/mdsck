<?php

return [
    'titulo' => 'SIC en EBS',
    'concepto' => 'Esta pantalla es un espejo, 100% de solo lectura, de las requisiciones (SIC) reales que existen en Oracle EBS — no captura nada nuevo ni llama al API de EBS en el momento en que la consultas. La información que ves aquí la trae, todas las madrugadas, un par de procesos programados que se conectan directamente a Oracle EBS: uno trae todas las requisiciones creadas ese día (sin importar su estatus) y otro trae únicamente las que ya se aprobaron o rechazaron ese día. Un tercer proceso, de uso puntual, permite traer historial de fechas pasadas.',
    'resuelve' => 'Sin esta pantalla, la única forma de confirmar si una SIC ya existe formalmente en Oracle EBS — y en qué estatus real va (en proceso, aprobada, rechazada) — sería entrar directamente a Oracle EBS. Aquí se puede consultar esa misma información sin salir del sistema, y además vincularla con la Solicitud de SIC correspondiente. La vinculación ocurre sola cuando el folio que se capturó a mano en "Solicitud de SIC" coincide exactamente con el código de la requisición en EBS; cuando esa coincidencia automática no aplicó (folio nunca capturado, capturado con un error de dedo, o capturado antes de que la sincronización existiera), esta pantalla permite resolverlo a mano sin tener que volver a capturar nada.',
    'proceso' => [
        'No hay nada que llenar aquí para que la información aparezca — llega sola cada madrugada desde Oracle EBS. Si una requisición que ya existe en EBS todavía no aparece, es cuestión de esperar a la siguiente sincronización, no de un dato faltante en esta pantalla.',
        'Usa los filtros (código, estatus, vinculada/no vinculada, rango de fechas) para encontrar una requisición específica.',
        'Si una fila aparece como "No vinculada" y tú sabes a qué Solicitud de SIC corresponde, da clic en "Vincular".',
        'En el buscador del modal, escribe el folio de SIC, el nombre del solicitante o el folio del ticket para encontrar la Solicitud de SIC correcta.',
        'Selecciónala con el botón de opción y confirma con "Vincular" — el estatus local de esa Solicitud de SIC se actualiza automáticamente para reflejar lo que reporta Oracle EBS.',
    ],
    'campos' => [
        ['nombre' => 'Código', 'explicacion' => 'El folio de la requisición tal como existe en Oracle EBS (por ejemplo, "6489").'],
        ['nombre' => 'Descripción', 'explicacion' => 'Descripción de la requisición, tal como se capturó en Oracle EBS.'],
        ['nombre' => 'Estatus', 'explicacion' => 'El estatus real que reporta Oracle EBS (por ejemplo "IN PROCESS", "APPROVED", "REJECTED"). No es editable desde aquí — cambia únicamente cuando la sincronización automática trae un valor nuevo.'],
        ['nombre' => 'Fecha', 'explicacion' => 'Fecha de creación de la requisición en Oracle EBS.'],
        ['nombre' => 'Vinculada', 'explicacion' => 'Muestra a qué Solicitud de SIC (y a qué Ticket) quedó asociada esta requisición, o "No vinculada" si ninguna Solicitud de SIC coincide todavía con este folio.'],
        ['nombre' => 'Filtro de código', 'explicacion' => 'Busca por coincidencia parcial del folio de la requisición.'],
        ['nombre' => 'Filtro de estatus', 'explicacion' => 'Muestra únicamente los estatus que de verdad existen hoy en la réplica local (no es una lista fija) — filtra por el valor exacto que reportó Oracle EBS.'],
        ['nombre' => 'Filtro de vinculación', 'explicacion' => 'Permite ver solo las requisiciones ya vinculadas a una Solicitud de SIC, o solo las que todavía no tienen ninguna vinculación.'],
        ['nombre' => 'Desde / Hasta', 'explicacion' => 'Acota la lista a un rango de fechas de creación en Oracle EBS.'],
        ['nombre' => 'Vincular (acción)', 'explicacion' => 'Solo aparece en requisiciones sin vincular. Abre un buscador para asociar a mano esta requisición con la Solicitud de SIC correspondiente, cuando la vinculación automática por folio no ocurrió.'],
    ],
];
