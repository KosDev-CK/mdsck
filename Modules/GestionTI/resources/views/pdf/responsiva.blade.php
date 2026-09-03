<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carta responsiva — {{ $assignment->asset->codigo }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 30px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.meta { font-size: 9px; color: #9ca3af; margin: 0 0 20px; }
        div.section-label { background: #f3f4f6; padding: 6px 8px; font-weight: bold; margin: 16px 0 4px; }
        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.fields td { padding: 6px 4px; vertical-align: top; }
        td.label { font-weight: bold; width: 220px; border-bottom: 1px solid #d1d5db; }
        td.value { border-bottom: 1px solid #d1d5db; }
        div.acuse { margin: 16px 0; font-size: 10px; color: #374151; }
        div.clausula { margin: 20px 0; font-size: 10px; color: #374151; text-align: justify; }
        div.clausula p { margin: 0 0 10px; }
        div.firmas { margin-top: 40px; }
        table.firmas { width: 100%; }
        table.firmas td { width: 50%; text-align: center; padding-top: 40px; font-size: 10px; }
        table.firmas td .linea { border-top: 1px solid #1f2937; padding-top: 4px; display: inline-block; width: 80%; }
        p.duplicado { margin: 24px 0 0; font-size: 9px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <h1>Carta responsiva de resguardo de activo</h1>
    <p class="meta">Activo {{ $assignment->asset->codigo }} &middot; Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="section-label">Datos de la solicitud</div>
    <table class="fields">
        <tr>
            <td class="label"># de Requerimiento</td>
            <td class="value">{{ $assignment->ticket?->sdp_display_id ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Fecha</td>
            <td class="value">{{ optional($assignment->fecha_asignacion)->format('d/m/Y') ?? ($assignment->fecha_asignacion ?? '—') }}</td>
        </tr>
        <tr>
            <td class="label">Responsable de Soporte</td>
            <td class="value">{{ $assignment->responsableEntrega?->nombre ?? '—' }}</td>
        </tr>
    </table>

    <div class="section-label">Datos del empleado que recibe</div>
    <table class="fields">
        <tr>
            <td class="label">Nombre</td>
            <td class="value">{{ $assignment->empleado?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Empresa</td>
            <td class="value">{{ $assignment->empleado?->empresa?->nombre_comercial ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Puesto</td>
            <td class="value">{{ $assignment->empleado?->puesto?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Depto/Área</td>
            <td class="value">{{ $assignment->empleado?->area?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">RFC</td>
            <td class="value">{{ $assignment->empleado?->rfc ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Correo electrónico</td>
            <td class="value">{{ $assignment->empleado?->correo ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Localidad</td>
            <td class="value">{{ $assignment->empleado?->ubicacion?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Jefe inmediato</td>
            <td class="value">{{ $assignment->empleado?->jefeInmediato?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Director de Área</td>
            <td class="value">{{ $assignment->empleado?->director?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Director Ejecutivo</td>
            <td class="value">{{ $assignment->empleado?->directorEjecutivo?->nombre ?? '—' }}</td>
        </tr>
    </table>

    <div class="section-label">Datos del equipo</div>
    <table class="fields">
        <tr>
            <td class="label">Código</td>
            <td class="value">{{ $assignment->asset->codigo }}</td>
        </tr>
        <tr>
            <td class="label">Tipo de equipo</td>
            <td class="value">{{ $assignment->asset->tipoEquipo?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Marca</td>
            <td class="value">{{ $assignment->asset->marca?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Modelo</td>
            <td class="value">{{ $assignment->asset->modelo?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Número de serie</td>
            <td class="value">{{ $assignment->asset->numero_serie ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Service tag</td>
            <td class="value">{{ $assignment->asset->service_tag ?? '—' }}</td>
        </tr>
    </table>

    <div class="section-label">Datos de la entrega</div>
    <table class="fields">
        <tr>
            <td class="label">Fecha de entrega</td>
            <td class="value">{{ optional($assignment->fecha_asignacion)->format('d/m/Y') ?? $assignment->fecha_asignacion }}</td>
        </tr>
        <tr>
            <td class="label">Estado del equipo</td>
            <td class="value">{{ ucfirst($assignment->estado_equipo_entrega ?? '—') }}</td>
        </tr>
        <tr>
            <td class="label">Accesorios entregados</td>
            <td class="value">{{ $assignment->accesorios_entregados ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Entregado por</td>
            <td class="value">{{ $assignment->responsableEntrega?->nombre ?? '—' }}</td>
        </tr>
    </table>

    @php
        // La sección completa se omite si ninguno de los 9 campos técnicos
        // fue capturado — no todos los tipos de equipo tienen esta
        // información (ej. un Access Point), y una sección de puros "—" es
        // ruido visual en el PDF.
        $tieneConfigTecnica = collect([
            $assignment->ip, $assignment->mac_wifi, $assignment->mac_ethernet,
            $assignment->sistema_operativo_id, $assignment->version_office,
            $assignment->antivirus, $assignment->dominio, $assignment->usuario_dominio,
            $assignment->id_producto_so, $assignment->libra_cloud, $assignment->oracle_ebs,
        ])->contains(fn ($v) => $v !== null && $v !== '');
    @endphp

    @if ($tieneConfigTecnica)
        <div class="section-label">Configuración de software/red</div>
        <table class="fields">
            <tr>
                <td class="label">Software S.O.</td>
                <td class="value">{{ $assignment->sistemaOperativo?->nombre ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Office</td>
                <td class="value">{{ $assignment->version_office ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">ID de Producto S.O.</td>
                <td class="value">{{ $assignment->id_producto_so ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Antivirus</td>
                <td class="value">{{ $assignment->antivirus ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">IP</td>
                <td class="value">{{ $assignment->ip ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">MAC Wi-Fi</td>
                <td class="value">{{ $assignment->mac_wifi ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">MAC Ethernet</td>
                <td class="value">{{ $assignment->mac_ethernet ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Dominio</td>
                <td class="value">{{ $assignment->dominio ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Usuario de dominio</td>
                <td class="value">{{ $assignment->usuario_dominio ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Libra Cloud</td>
                <td class="value">{{ $assignment->libra_cloud === null ? '—' : ($assignment->libra_cloud ? 'Sí' : 'No') }}</td>
            </tr>
            <tr>
                <td class="label">Oracle/EBS</td>
                <td class="value">{{ $assignment->oracle_ebs === null ? '—' : ($assignment->oracle_ebs ? 'Sí' : 'No') }}</td>
            </tr>
        </table>
    @endif

    <div class="section-label">Acuse del empleado</div>
    <div class="acuse">
        He leído, Entiendo y Comprendo, los puntos establecidos y escritos los cuales son correctos y exactos.
    </div>
    <table class="fields">
        <tr>
            <td class="label">Nombre</td>
            <td class="value">{{ $assignment->empleado?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">RFC</td>
            <td class="value">{{ $assignment->empleado?->rfc ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label"># de Empleado</td>
            <td class="value">{{ $assignment->empleado?->numero_empleado ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Fecha</td>
            <td class="value">{{ optional($assignment->fecha_asignacion)->format('d/m/Y') ?? ($assignment->fecha_asignacion ?? '—') }}</td>
        </tr>
        <tr>
            <td class="label">Ubicación</td>
            <td class="value">{{ $assignment->empleado?->ubicacion?->nombre ?? '—' }}</td>
        </tr>
    </table>

    <div class="clausula">
        <p>Estoy de acuerdo que:</p>

        <p>Con motivo del puesto y funciones que desempeño en la Empresa, se me ha asignado como herramientas de trabajo el equipo de cómputo, software, periféricos y accesorios descritos. A partir de esta fecha me responsabilizo de su correcto uso y custodia, de acuerdo con las políticas que establece la Empresa.</p>

        <p>Estoy enterado que está prohibido (de forma enunciativa mas no limitativa) instalar juegos, programas de cómputo, visitar páginas no autorizadas, chats, descargar música, videos, programas, escuchar estaciones de radio, instalar y/o modificar software sin la autorización de la Empresa, instalar o cambiar protectores de pantalla y realizar cualquier otra actividad que ponga en peligro la seguridad de la red de la Empresa y que difiera con mis actividades laborales cuales quiera que sea su clase; no instalar y/o modificar software, cualquier cambio o modificación que se deba realizar deberá ser solicitado a la Gerencia de Sistemas y/o estar autorizado por escrito por parte de la Empresa, debiendo cumplir con las Leyes Nacionales e internacionales de Derechos de Autor y propiedad intelectual que apliquen a las herramientas descritas.</p>

        <p>Toda la información generada, será propiedad intelectual de la Empresa y no podrá ser extraída, difundida o publicada, por cualquier medio, sin contar con la aprobación por escrito de la dirección de la Empresa.</p>

        <p>Me comprometo a utilizar el internet y/o correo electrónico sólo para el desarrollo de mi trabajo y devolver todas estas herramientas antes señaladas en buenas condiciones al momento de la terminación laboral con la Empresa, o por cambiar las funciones que desempeño y no requieran de dichas herramientas, así como a solicitud de la Gerencia de Sistemas o directivos de la empresa.</p>

        <p><strong>Así mismo acepto cualquier responsabilidad que se genere por mal uso del equipo a mi cargo por su falta de custodia, por descuido y/o negligencia de mi parte, pagando por la reparación o por el equipo.</strong></p>

        <p><strong>Toda la información que se recabe o genere por el desarrollo de mis actividades es considerada como propiedad intelectual de la Empresa siendo responsable de cualquier indiscreción imputable al suscrito.</strong></p>

        <p>El personal del área de Sistemas podrá realizar auditorías sin previo aviso y desinstalar cualquier programa, borrar toda información, música, videos, etc. No necesario para cumplir con mis labores.</p>

        <p>Por este medio deslindo a la Empresa, de cualquier responsabilidad por el mal uso o instalación de cualquier software sin licencia.</p>

        <p>En caso de siniestro o robo, acepto pagar el monto correspondiente al equipo, el deducible o el costo contable del mismo, además de realizar los trámites ante las autoridades competentes, según sean necesarios o a pagar el sustituto del equipo con las mismas o similares características.</p>

        <p>Cualquier transgresión a lo antes señalado, será sancionado de acuerdo con las políticas y lineamientos que establezca la Empresa.</p>

        <p>He leído, Entiendo y Comprendo, los puntos establecidos en los escritos en el presente documento y que apliquen según sea el caso.</p>
    </div>

    <div class="firmas">
        <table class="firmas">
            <tr>
                <td>
                    <span class="linea">Firma del empleado</span><br>
                    {{ $assignment->empleado?->nombre ?? '—' }}<br>
                    {{ optional($assignment->fecha_asignacion)->format('d/m/Y') ?? ($assignment->fecha_asignacion ?? '—') }}
                </td>
                <td></td>
            </tr>
        </table>

        <p class="duplicado">El presente documento se firma por duplicado para: Expediente / Empleado</p>

        <table class="firmas">
            <tr>
                <td><span class="linea">Firma IT</span></td>
                <td><span class="linea">Firma Dirección</span></td>
            </tr>
        </table>
    </div>
</body>
</html>
