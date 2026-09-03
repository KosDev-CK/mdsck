<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de trazabilidad — {{ $asset->codigo }}</title>
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
    </style>
</head>
<body>
    <h1>Reporte de Trazabilidad del Activo</h1>
    <p class="meta">Activo {{ $asset->codigo }} &middot; Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="section-label">Datos del activo</div>
    <table class="fields">
        <tr>
            <td class="label">Código</td>
            <td class="value">{{ $asset->codigo }}</td>
        </tr>
        <tr>
            <td class="label">Tipo de equipo</td>
            <td class="value">{{ $asset->tipoEquipo?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Marca/Modelo</td>
            <td class="value">{{ trim(($asset->marca?->nombre ?? '').' '.($asset->modelo?->nombre ?? '')) ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Número de serie/Service tag</td>
            <td class="value">{{ $asset->numero_serie ?? '—' }} / {{ $asset->service_tag ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Estatus actual</td>
            <td class="value">{{ $asset->estatus?->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Ubicación actual</td>
            <td class="value">{{ $asset->ubicacionActual?->nombre_conocido ?? $asset->ubicacionActual?->nombre ?? '—' }}</td>
        </tr>
    </table>

    <div class="section-label">Línea de tiempo</div>
    <table class="items">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Evento</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($timeline as $evento)
                <tr>
                    <td>{{ $evento['fecha']?->format('d/m/Y') ?? 'Sin fecha registrada' }}</td>
                    <td>{{ $evento['titulo'] }}</td>
                    <td>{{ $evento['detalle'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Este activo no tiene eventos registrados todavía.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
