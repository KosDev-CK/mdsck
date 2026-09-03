<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $form->name }} - {{ $link->ticket_number }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111827; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        .title { font-size: 16px; font-weight: bold; text-align: center; padding-bottom: 10px; }
        .top-row td { padding: 4px 0; font-size: 10px; text-align: right; }
        .section td { background: #F36522; color: #ffffff; font-weight: bold; font-size: 11px; padding: 5px 8px; }
        .row td { padding: 5px 4px; font-size: 10px; vertical-align: bottom; }
        .row .label { width: 45%; }
        .row .value { border-bottom: 1px solid #000; }
        .signatures { margin-top: 50px; }
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
    @endphp

    <div class="title">SOLICITUD DE ALTA PARA FLUJO DE APROBACIÓN</div>

    <table class="top-row">
        <tr>
            <td>Fecha: {{ $link->submission?->submitted_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Datos del Solicitante</td></tr>
    </table>
    <table>
        <tr class="row"><td class="label">Nombre Completo</td><td class="value">{{ $val('nombre_completo') }}</td></tr>
        <tr class="row"><td class="label">Número de Empleado</td><td class="value">{{ $val('numero_empleado') }}</td></tr>
        <tr class="row"><td class="label">Empleadora</td><td class="value">{{ $val('empleadora') }}</td></tr>
        <tr class="row"><td class="label">Área o Departamento</td><td class="value">{{ $val('area_departamento') }}</td></tr>
        <tr class="row"><td class="label">Puesto</td><td class="value">{{ $val('puesto') }}</td></tr>
        <tr class="row"><td class="label">Localidad</td><td class="value">{{ $val('localidad') }}</td></tr>
        <tr class="row"><td class="label">Teléfono</td><td class="value">{{ $val('telefono') }}</td></tr>
        <tr class="row"><td class="label">Correo Electrónico (Contacto)</td><td class="value">{{ $val('correo_contacto') }}</td></tr>
        <tr class="row"><td class="label">Jefe Inmediato</td><td class="value">{{ $val('jefe_inmediato') }}</td></tr>
        <tr class="row"><td class="label">Director Operativo</td><td class="value">{{ $val('director_operativo') }}</td></tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Datos de Aprobaciones</td></tr>
    </table>
    <table>
        <tr class="row"><td class="label">Circuito de Aprobación</td><td class="value">{{ $val('circuito_aprobacion') }}</td></tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Autorizadores</td></tr>
    </table>
    <table>
        <tr class="row"><td class="label">Autorizador 1</td><td class="value">{{ $val('autorizador_1') }}</td></tr>
        <tr class="row"><td class="label">Autorizador 2</td><td class="value">{{ $val('autorizador_2') }}</td></tr>
        <tr class="row"><td class="label">Autorizador 3</td><td class="value">{{ $val('autorizador_3') }}</td></tr>
        <tr class="row"><td class="label">Autorizador 4</td><td class="value">{{ $val('autorizador_4') }}</td></tr>
    </table>

    <table class="signatures">
        <tr>
            <td style="width: 33%;" class="sig-line">Firma</td>
            <td style="width: 33%;" class="sig-line">Firma</td>
            <td style="width: 33%;" class="sig-line">Firma</td>
        </tr>
        <tr>
            <td class="sig-role">Solicitante</td>
            <td class="sig-role">Jefe Inmediato</td>
            <td class="sig-role">Director Operativo</td>
        </tr>
    </table>

    <table class="footer">
        <tr>
            <td>Ticket {{ $link->ticket_number }}</td>
            <td>mdsLandIT FORA001 - Solicitud de Flujo de Aprobación Oracle</td>
        </tr>
    </table>
</body>
</html>
