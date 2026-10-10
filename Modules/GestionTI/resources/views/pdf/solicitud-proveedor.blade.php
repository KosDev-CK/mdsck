<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud a Proveedor — {{ $solicitud->folio }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 30px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.meta { font-size: 9px; color: #9ca3af; margin: 0 0 20px; }
        div.section-label { background: #f3f4f6; padding: 6px 8px; font-weight: bold; margin: 16px 0 4px; }
        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.fields td { padding: 6px 4px; vertical-align: top; }
        td.label { font-weight: bold; width: 220px; border-bottom: 1px solid #d1d5db; }
        td.value { border-bottom: 1px solid #d1d5db; }
        table.lineas { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.lineas th { text-align: left; background: #f3f4f6; padding: 6px 4px; font-size: 10px; }
        table.lineas td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        p.pie { margin: 24px 0 0; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    @php
        $estatusLabels = [
            'solicitada' => 'Solicitada',
            'parcialmente_recibida' => 'Parcialmente recibida',
            'recibida' => 'Recibida',
            'facturada' => 'Facturada',
            'cancelada' => 'Cancelada',
        ];
    @endphp

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: top;">
                <h1>Solicitud a Proveedor</h1>
                <p class="meta">Folio {{ $solicitud->folio }} &middot; Generado el {{ now()->format('d/m/Y H:i') }}</p>
            </td>
            <td style="vertical-align: top; text-align: right; width: 260px;">
                {{-- QR del folio: se escanea en Recepción de Proveedor para abrir esta solicitud. --}}
                <img src="{{ \Modules\GestionTI\Support\Codigos\CodigoQr::dataUri($solicitud->folio) }}" alt="{{ $solicitud->folio }}" style="width: 84px; height: 84px;">
                <div style="font-size: 9px; color: #6b7280; margin-top: 2px;">{{ $solicitud->folio }}</div>
            </td>
        </tr>
    </table>

    <div class="section-label">Datos de la solicitud</div>
    <table class="fields">
        <tr>
            <td class="label">Folio</td>
            <td class="value">{{ $solicitud->folio }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td class="value">{{ $solicitud->vendor?->nombre_comercial ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de solicitud</td>
            <td class="value">{{ optional($solicitud->fecha_solicitud)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de entrega comprometida</td>
            <td class="value">{{ optional($solicitud->fecha_entrega_prometida)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Solicitante</td>
            <td class="value">{{ $solicitud->creadoPor?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Estatus</td>
            <td class="value">{{ $estatusLabels[$solicitud->estatus] ?? $solicitud->estatus }}</td>
        </tr>
        <tr>
            <td class="label">Ticket relacionado</td>
            <td class="value">{{ $solicitud->ticket?->sdp_display_id ?? '—' }}</td>
        </tr>
    </table>

    <div class="section-label">Líneas</div>
    <table class="lineas">
        <thead>
            <tr>
                <th>SIC</th>
                <th>Artículo</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Entrega en</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($solicitud->lineas->sortBy('id')->values() as $linea)
                <tr>
                    <td>{{ $linea->folioSicDisplay() ?? '—' }}</td>
                    <td>{{ $linea->articulo?->descripcion ?? $linea->descripcion_libre ?? '—' }}</td>
                    <td>{{ $linea->cantidad_solicitada }}</td>
                    <td>{{ $linea->precio_unitario_cotizado ?? '—' }}</td>
                    <td>{{ $linea->lugarEntrega?->nombre ?? '—' }}</td>
                    <td>{{ $linea->observaciones_especificaciones ?? '—' }}</td>
                </tr>
                <tr>
                    <td colspan="6" style="padding: 2px 4px 8px; border-bottom: 1px solid #e5e7eb;">
                        {{-- Se escanea en Recepción para ir a esta línea; luego se escanea el número de serie de cada equipo. --}}
                        <img src="{{ \Modules\GestionTI\Support\Codigos\CodigoDeBarras::dataUri($solicitud->codigoLinea($loop->iteration), 2, 36) }}" alt="{{ $solicitud->codigoLinea($loop->iteration) }}" style="width: 150px; height: 28px;">
                        <span style="font-size: 8px; color: #6b7280;">Línea {{ $loop->iteration }} &middot; {{ $solicitud->codigoLinea($loop->iteration) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Sin líneas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="pie">Generado desde el sistema el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
