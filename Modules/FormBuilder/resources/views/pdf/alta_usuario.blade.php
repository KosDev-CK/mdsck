<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $form->name }} - {{ $link->ticket_number }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111827; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        .title { font-size: 20px; font-weight: bold; text-align: center; padding-bottom: 10px; }
        .top-row td { padding: 4px 0; font-size: 10px; }
        .top-row .value { border-bottom: 1px solid #000; padding-left: 6px; }
        .section td { background: #F36522; color: #ffffff; font-weight: bold; font-size: 11px; padding: 5px 8px; }
        .row td { padding: 5px 4px; font-size: 10px; vertical-align: bottom; }
        .row .label { width: 45%; }
        .row .value { border-bottom: 1px solid #000; }
        .required-note { font-size: 8px; color: #6b7280; text-align: right; padding: 2px 4px; }
        .yn-box { border: 1px solid #000; width: 10px; height: 10px; display: inline-block; text-align: center; font-size: 9px; line-height: 10px; }
        .signatures { margin-top: 40px; }
        .signatures td { text-align: center; padding-top: 40px; }
        .sig-line { border-top: 1px solid #000; padding-top: 4px !important; font-size: 9px; }
        .sig-role { font-weight: bold; font-size: 9px; }
        .footer { margin-top: 16px; font-size: 7px; color: #9ca3af; }
        .footer td:last-child { text-align: right; }
    </style>
</head>
<body>
    @php
        $fieldsByKey = $form->fields->keyBy('field_key');
        $answersByField = $link->submission?->answers->keyBy('form_field_id') ?? collect();

        $val = function (string $key) use ($fieldsByKey, $answersByField) {
            $field = $fieldsByKey[$key] ?? null;

            return $field ? ($answersByField[$field->id]?->displayValue() ?: '') : '';
        };

        $raw = function (string $key) use ($fieldsByKey, $answersByField) {
            $field = $fieldsByKey[$key] ?? null;

            return $field ? $answersByField[$field->id]?->value : null;
        };
    @endphp

    <div class="title">Multi formato Solicitud</div>

    <table class="top-row">
        <tr>
            <td style="width: 15%;">Solicitante:</td>
            <td class="value" style="width: 45%;">{{ $val('nombre_del_empleado') }}</td>
            <td style="width: 10%;">Fecha:</td>
            <td class="value" style="width: 30%;">{{ $link->submission?->submitted_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Datos del Usuario (Alta/Solicitante)</td></tr>
    </table>
    <table>
        <tr class="row"><td class="label">Nombre del Empleado</td><td class="value">{{ $val('nombre_del_empleado') }}</td></tr>
        <tr class="row"><td class="label">Número de Empleado</td><td class="value">{{ $val('numero_de_empleado') }}</td></tr>
        <tr class="row"><td class="label">RFC</td><td class="value">{{ $val('rfc') }}</td></tr>
        <tr class="row"><td class="label">Empleadora (Empresa que prestan Servicio)</td><td class="value">{{ $val('empleadora_empresa_que_prestan_servicio') }}</td></tr>
        <tr class="row"><td class="label">Área o Departamento</td><td class="value">{{ $val('area_o_departamento') }}</td></tr>
        <tr class="row"><td class="label">Puesto</td><td class="value">{{ $val('puesto') }}</td></tr>
        <tr class="row"><td class="label">Dirección Completa del centro de trabajo</td><td class="value">{{ $val('direccion_completa_del_centro_de_trabajo') }}</td></tr>
        <tr class="row"><td class="label">Teléfono (Oficina/Cel. Empresa)</td><td class="value">{{ $val('telefono_oficinacel_empresa') }}</td></tr>
        <tr class="row"><td class="label">Correo Electrónico (Contacto)</td><td class="value">{{ $val('correo_electronico_contacto') }}</td></tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Datos de aplicativos</td></tr>
    </table>
    <table>
        <tr class="row"><td class="label">Aplicación o Sistema al que requiere Acceso</td><td class="value">{{ $val('aplicacion_o_sistema_al_que_requiere_acceso') }}</td></tr>
        <tr class="row"><td class="label">Funciones o Accesos que requiere</td><td class="value">{{ $val('funciones_o_accesos_que_requiere') }}</td></tr>
        <tr class="row"><td class="label">Motivo de solicitud</td><td class="value">{{ $val('motivo_de_solicitud') }}</td></tr>
        <tr class="row">
            <td class="label">
                Usuario Nuevo:
                <span class="yn-box">{{ $raw('usuario_nuevo') === 'si' ? 'X' : '' }}</span> Sí
                &nbsp;&nbsp;
                <span class="yn-box">{{ $raw('usuario_nuevo') === 'no' ? 'X' : '' }}</span> No
            </td>
            <td class="value">Sustituye a: {{ $val('sustituye_a') }}</td>
        </tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Información de Estructura</td></tr>
    </table>
    <table>
        <tr class="row"><td class="label">Nombre de Jefe Directo</td><td class="value">{{ $val('nombre_de_jefe_directo') }}</td></tr>
        <tr class="row"><td class="label">Nombre del Gerente</td><td class="value">{{ $val('nombre_del_gerente') }}</td></tr>
        <tr class="row"><td class="label">Nombre del Director Operativo</td><td class="value">{{ $val('nombre_del_director_operativo') }}</td></tr>
        <tr class="row"><td class="label">Nombre del Director Ejecutivo</td><td class="value">{{ $val('nombre_del_director_ejecutivo') }}</td></tr>
    </table>

    <table class="signatures">
        <tr>
            <td style="width: 33%;" class="sig-line">Firma</td>
            <td style="width: 33%;" class="sig-line">Firma</td>
            <td style="width: 33%;" class="sig-line">Firma</td>
        </tr>
        <tr>
            <td class="sig-role">Solicitante</td>
            <td class="sig-role">Jefe inmediato</td>
            <td class="sig-role">Director Operativo</td>
        </tr>
    </table>

    <table class="footer">
        <tr>
            <td>Ticket {{ $link->ticket_number }}</td>
            <td>mdsLandIT FMSOL001-MultiFormatoSolicitud</td>
        </tr>
    </table>
</body>
</html>
