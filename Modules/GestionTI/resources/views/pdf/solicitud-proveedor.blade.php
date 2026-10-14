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

    @php
        // Una hoja (o más, si no caben) por lugar de entrega: cada lugar recibe
        // su propia impresión. El número de línea se conserva (es el que va en el
        // código de barras), aunque se reordenen las líneas por lugar.
        $grupos = $solicitud->lineas->sortBy('id')->values()
            ->map(fn ($linea, $i) => ['linea' => $linea, 'ordinal' => $i + 1])
            ->groupBy(fn ($item) => $item['linea']->lugar_entrega_id ?? 0)
            ->sortBy(fn ($grupo, $clave) => $clave === 0 ? 'zzz' : mb_strtolower($grupo->first()['linea']->lugarEntrega?->nombre ?? ''))
            ->values();

        if ($grupos->isEmpty()) {
            $grupos = collect([collect()]);
        }
    @endphp

    @foreach ($grupos as $grupo)
        <div @if (! $loop->first) style="page-break-before: always;" @endif>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: top;">
                        <h1>Solicitud a Proveedor</h1>
                        <p class="meta">Folio {{ $solicitud->folio }} &middot; Generado el {{ now()->format('d/m/Y H:i') }}</p>
                    </td>
                    <td style="vertical-align: top; text-align: right; width: 260px;">
                        {{-- QR del folio: se escanea en Recepción de Proveedor para abrir esta solicitud (en cada hoja). --}}
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

            <div class="section-label">
                Entrega en: {{ $grupo->first()['linea']->lugarEntrega?->nombre ?? 'Sin lugar de entrega' }}
                @if ($grupos->count() > 1)
                    &middot; Hoja {{ $loop->iteration }} de {{ $grupos->count() }} lugares de entrega
                @endif
            </div>
            <table class="lineas">
                <thead>
                    <tr>
                        <th>SIC</th>
                        <th>Artículo</th>
                        <th>Cantidad</th>
                        <th>Precio unitario</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($grupo as $item)
                        <tr>
                            <td>{{ $item['linea']->folioSicDisplay() ?? '—' }}</td>
                            <td>{{ $item['linea']->articulo?->descripcion ?? $item['linea']->descripcion_libre ?? '—' }}</td>
                            <td>{{ $item['linea']->cantidad_solicitada }}</td>
                            <td>{{ $item['linea']->precio_unitario_cotizado ?? '—' }}</td>
                            <td>{{ $item['linea']->observaciones_especificaciones ?? '—' }}</td>
                        </tr>
                        <tr>
                            {{-- Los códigos se alternan (impar a la izquierda, par a la derecha) para que no queden uno sobre otro y la pistola lea sin confundirse. --}}
                            <td colspan="5" style="padding: 2px 4px 8px; border-bottom: 1px solid #e5e7eb; text-align: {{ $loop->odd ? 'left' : 'right' }};">
                                {{-- Se escanea en Recepción para ir a esta línea; luego se escanea el número de serie de cada equipo. --}}
                                @if ($loop->odd)
                                    <img src="{{ \Modules\GestionTI\Support\Codigos\CodigoDeBarras::dataUri($solicitud->codigoLinea($item['ordinal']), 2, 36) }}" alt="{{ $solicitud->codigoLinea($item['ordinal']) }}" style="width: 150px; height: 28px;">
                                    <span style="font-size: 8px; color: #6b7280;">Línea {{ $item['ordinal'] }} &middot; {{ $solicitud->codigoLinea($item['ordinal']) }}</span>
                                @else
                                    <span style="font-size: 8px; color: #6b7280;">Línea {{ $item['ordinal'] }} &middot; {{ $solicitud->codigoLinea($item['ordinal']) }}</span>
                                    <img src="{{ \Modules\GestionTI\Support\Codigos\CodigoDeBarras::dataUri($solicitud->codigoLinea($item['ordinal']), 2, 36) }}" alt="{{ $solicitud->codigoLinea($item['ordinal']) }}" style="width: 150px; height: 28px;">
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Sin líneas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($loop->last)
                <p class="pie">Generado desde el sistema el {{ now()->format('d/m/Y H:i') }}</p>
            @endif
        </div>
    @endforeach
</body>
</html>
