<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $form->name }} - {{ $link->ticket_number }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111827; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        .title { font-size: 18px; font-weight: bold; text-align: center; padding-bottom: 10px; }
        .top-row td { padding: 4px 0; font-size: 10px; }
        .top-row .value { border-bottom: 1px solid #000; padding-left: 6px; }
        .section td { background: #F36522; color: #ffffff; font-weight: bold; font-size: 11px; padding: 5px 8px; }
        .row td { padding: 5px 4px; font-size: 10px; vertical-align: bottom; }
        .row .label { width: 45%; }
        .row .value { border-bottom: 1px solid #000; }
        table.responsabilidades { margin-top: 6px; }
        table.responsabilidades th, table.responsabilidades td { border: 1px solid #000; padding: 4px 6px; font-size: 10px; text-align: left; }
        .signatures { margin-top: 50px; }
        .signatures td { text-align: center; padding-top: 40px; }
        .sig-line { border-top: 1px solid #000; padding-top: 4px !important; font-size: 9px; }
        .sig-role { font-weight: bold; font-size: 9px; }
        .note { margin-top: 16px; font-size: 8px; font-weight: bold; color: #111827; }
        .footer { margin-top: 8px; font-size: 7px; color: #9ca3af; }
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

        $responsabilidadesField = $fieldsByKey['responsabilidades'] ?? null;
        $responsabilidadesRows = $responsabilidadesField
            ? (array) ($answersByField[$responsabilidadesField->id]->value ?? [])
            : [];
    @endphp

    <div class="title">Solicitud Responsabilidades Oracle</div>

    <table class="top-row">
        <tr>
            <td style="width: 15%;">Solicitante:</td>
            <td class="value" style="width: 45%;">{{ $val('nombre') }} {{ $val('apellidos') }}</td>
            <td style="width: 10%;">Fecha:</td>
            <td class="value" style="width: 30%;">{{ $link->submission?->submitted_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Datos del Usuario</td></tr>
    </table>
    <table>
        <tr class="row"><td class="label">Nombre</td><td class="value">{{ $val('nombre') }}</td></tr>
        <tr class="row"><td class="label">Apellidos</td><td class="value">{{ $val('apellidos') }}</td></tr>
        <tr class="row"><td class="label">Correo Electrónico Corporativo</td><td class="value">{{ $val('correo_corporativo') }}</td></tr>
        <tr class="row"><td class="label">¿Es modificación de un usuario ya existente?</td><td class="value">{{ $val('es_modificacion_usuario_existente') }}</td></tr>
        <tr class="row"><td class="label">Tipo de modificación</td><td class="value">{{ $val('tipo_modificacion') }}</td></tr>
        <tr class="row"><td class="label">Alta / Baja / Cambio</td><td class="value">{{ $val('alta_baja_cambio') }}</td></tr>
        <tr class="row"><td class="label">Empresa a la que pertenece el usuario</td><td class="value">{{ $val('empresa') }}</td></tr>
        <tr class="row"><td class="label">Área</td><td class="value">{{ $val('area') }}</td></tr>
    </table>

    <table>
        <tr class="section"><td colspan="2">Funcionalidad a realizar en el sistema / Responsabilidades requeridas</td></tr>
    </table>
    <table class="responsabilidades">
        <thead>
            <tr><th>#</th><th>Responsabilidad</th></tr>
        </thead>
        <tbody>
            @forelse ($responsabilidadesRows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['responsabilidad'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="2">Sin responsabilidades capturadas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td style="width: 50%;" class="sig-line">Firma</td>
            <td style="width: 50%;" class="sig-line">Firma</td>
        </tr>
        <tr>
            <td class="sig-role">Nombre y firma del solicitante</td>
            <td class="sig-role">Nombre y firma del autorizador</td>
        </tr>
    </table>

    <div class="note">NOTA: NO SE PROCEDERÁ CON ALTAS DE USUARIO, SI NO SE CUENTA CON CORREO CORPORATIVO.</div>

    <table class="footer">
        <tr>
            <td>Ticket {{ $link->ticket_number }}</td>
        </tr>
    </table>
</body>
</html>
