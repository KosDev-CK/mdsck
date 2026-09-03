<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acta de entrega-recepción — {{ $recepcion->folio_remision }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 30px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p.meta { font-size: 9px; color: #9ca3af; margin: 0 0 20px; }
        div.section-label { background: #f3f4f6; padding: 6px 8px; font-weight: bold; margin: 16px 0 4px; }
        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.fields td { padding: 6px 4px; vertical-align: top; }
        td.label { font-weight: bold; width: 220px; border-bottom: 1px solid #d1d5db; }
        td.value { border-bottom: 1px solid #d1d5db; }
        table.items { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
        table.items th { background: #f3f4f6; text-align: left; padding: 6px 6px; border: 1px solid #d1d5db; font-size: 10px; }
        table.items td { padding: 6px 6px; border: 1px solid #d1d5db; vertical-align: top; }
        div.firmas { margin-top: 40px; }
        table.firmas { width: 100%; }
        table.firmas td { width: 50%; text-align: center; padding-top: 40px; font-size: 10px; }
        table.firmas td .linea { border-top: 1px solid #1f2937; padding-top: 4px; display: inline-block; width: 80%; }
    </style>
</head>
<body>
    <h1>Acta de Entrega-Recepción de Proveedor</h1>
    <p class="meta">Folio de remisión {{ $recepcion->folio_remision }} &middot; Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="section-label">Datos de la recepción</div>
    <table class="fields">
        <tr>
            <td class="label">Folio de remisión</td>
            <td class="value">{{ $recepcion->folio_remision }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de recepción</td>
            <td class="value">{{ optional($recepcion->fecha_recepcion)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Proveedor</td>
            <td class="value">{{ $recepcion->solicitudProveedor?->vendor?->nombre_comercial ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Folio de solicitud a proveedor</td>
            <td class="value">{{ $recepcion->solicitudProveedor?->folio ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Ubicación de recepción</td>
            <td class="value">{{ $recepcion->ubicacion?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Recibido por</td>
            <td class="value">{{ $recepcion->recibidoPor?->nombre ?? '—' }}</td>
        </tr>
        @if ($recepcion->observaciones)
            <tr>
                <td class="label">Observaciones</td>
                <td class="value">{{ $recepcion->observaciones }}</td>
            </tr>
        @endif
    </table>

    <div class="section-label">Artículos recibidos</div>
    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th>Cantidad recibida</th>
                <th>Código de activo</th>
                <th>Número de serie</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($recepcion->lineas as $linea)
                <tr>
                    <td>{{ $linea->solicitudProveedorLinea?->articulo?->descripcion ?? $linea->solicitudProveedorLinea?->descripcion_libre ?? '—' }}</td>
                    <td>{{ $linea->cantidad_recibida }}</td>
                    <td>{{ $linea->asset?->codigo ?? '—' }}</td>
                    <td>{{ $linea->asset?->numero_serie ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="firmas">
        <table class="firmas">
            <tr>
                <td><span class="linea">Entrega (Proveedor)</span></td>
                <td><span class="linea">Recibe ({{ $recepcion->recibidoPor?->nombre ?? 'Almacén/TI' }})</span></td>
            </tr>
        </table>
    </div>
</body>
</html>
