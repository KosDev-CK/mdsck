<x-mail::message>
# Solicitud de compra {{ $solicitud->folio }}

@php
    $estatusLabels = [
        'solicitada' => 'Solicitada',
        'parcialmente_recibida' => 'Parcialmente recibida',
        'recibida' => 'Recibida',
        'facturada' => 'Facturada',
        'cancelada' => 'Cancelada',
    ];
@endphp

Estimado(a) {{ $solicitud->vendor?->contacto_nombre ?? $solicitud->vendor?->nombre_comercial }},

Le compartimos la siguiente solicitud de compra:

- **Folio:** {{ $solicitud->folio }}
- **Fecha de solicitud:** {{ $solicitud->fecha_solicitud?->format('d/m/Y') }}
- **Solicitante:** {{ $solicitud->creadoPor?->name ?? 'No especificado' }}
- **Estatus:** {{ $estatusLabels[$solicitud->estatus] ?? $solicitud->estatus }}

<x-mail::table>
| SIC | Artículo | Cantidad | Observaciones |
| :-- | :------- | :------: | :------------ |
@foreach ($solicitud->lineas as $linea)
| {{ $linea->folioSicDisplay() ?? '—' }} | {{ $linea->articulo?->descripcion ?? $linea->descripcion_libre ?? '—' }} | {{ $linea->cantidad_solicitada }} | {{ $linea->observaciones_especificaciones ?? '—' }} |
@endforeach
</x-mail::table>

Adjuntamos el PDF de esta solicitud para impresión/entrega.

Quedamos al pendiente de su confirmación.

Saludos.
</x-mail::message>
