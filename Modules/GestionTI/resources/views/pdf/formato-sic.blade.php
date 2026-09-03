<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Formato de SIC — {{ $solicitud->folio_sic ?? 'Sin folio asignado' }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 30px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.meta { font-size: 9px; color: #9ca3af; margin: 0 0 20px; }
        div.section-label { background: #f3f4f6; padding: 6px 8px; font-weight: bold; margin: 16px 0 4px; }
        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.fields td { padding: 6px 4px; vertical-align: top; }
        td.label { font-weight: bold; width: 220px; border-bottom: 1px solid #d1d5db; }
        td.value { border-bottom: 1px solid #d1d5db; }
        p.pie { margin: 24px 0 0; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>Formato de Solicitud Interna de Compra (SIC)</h1>
    <p class="meta">Folio {{ $solicitud->folio_sic ?? 'Sin folio asignado' }} &middot; Generado el {{ now()->format('d/m/Y H:i') }}</p>

    @php
        $estatusLabels = [
            'capturado' => 'Capturado',
            'sic_creada' => 'SIC creada',
            'autorizada' => 'Autorizada',
            'rechazada' => 'Rechazada',
        ];
    @endphp

    <div class="section-label">Datos de la solicitud</div>
    <table class="fields">
        <tr>
            <td class="label">Folio SIC</td>
            <td class="value">{{ $solicitud->folio_sic ?? 'Sin folio asignado' }}</td>
        </tr>
        <tr>
            <td class="label">Estatus</td>
            <td class="value">{{ $estatusLabels[$solicitud->estatus] ?? $solicitud->estatus }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de solicitud</td>
            <td class="value">{{ optional($solicitud->fecha_solicitud)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Urgencia</td>
            <td class="value">{{ ucfirst($solicitud->urgencia ?? '—') }}</td>
        </tr>
        <tr>
            <td class="label">Ticket relacionado</td>
            <td class="value">{{ $solicitud->ticket?->sdp_display_id ?? '—' }}</td>
        </tr>
    </table>

    <div class="section-label">Datos del solicitante</div>
    <table class="fields">
        <tr>
            <td class="label">Nombre</td>
            <td class="value">{{ $solicitud->empleado?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label"># de Empleado</td>
            <td class="value">{{ $solicitud->empleado?->numero_empleado ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Puesto</td>
            <td class="value">{{ $solicitud->empleado?->puesto?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Área</td>
            <td class="value">{{ $solicitud->empleado?->area?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Ubicación</td>
            <td class="value">{{ $solicitud->empleado?->ubicacion?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Empresa</td>
            <td class="value">{{ $solicitud->empleado?->empresa?->nombre_comercial ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Jefe inmediato</td>
            <td class="value">{{ $solicitud->empleado?->jefeInmediato?->nombre ?? '—' }}</td>
        </tr>
    </table>

    <div class="section-label">Datos del equipo solicitado</div>
    <table class="fields">
        <tr>
            <td class="label">Tipo de equipo</td>
            <td class="value">{{ $solicitud->tipoEquipo?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Centro de costo</td>
            <td class="value">{{ $solicitud->centroCosto?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Unidad de negocio</td>
            <td class="value">{{ $solicitud->unidadNegocio?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Motivo</td>
            <td class="value">{{ $solicitud->motivo ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Especificaciones requeridas</td>
            <td class="value">{{ $solicitud->especificaciones_requeridas ?: '—' }}</td>
        </tr>
    </table>

    <p class="pie">Generado desde el sistema el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
