<x-mail::message>
# {{ $solicitud->vendor?->es_interno ? 'Solicitud interna' : 'Solicitud de compra' }} {{ $solicitud->folio }}

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

@if ($solicitud->vendor?->es_interno)
Se autorizó la siguiente solicitud y corresponde a su área surtirla:
@else
Le compartimos la siguiente solicitud de compra:
@endif

- **Folio:** {{ $solicitud->folio }}
- **Fecha de solicitud:** {{ $solicitud->fecha_solicitud?->format('d/m/Y') }}
@if ($solicitud->fecha_entrega_prometida)
- **Fecha de entrega comprometida:** {{ $solicitud->fecha_entrega_prometida->format('d/m/Y') }}
@endif
- **Solicitante:** {{ $solicitud->creadoPor?->name ?? 'No especificado' }}
- **Estatus:** {{ $estatusLabels[$solicitud->estatus] ?? $solicitud->estatus }}

<x-mail::table>
| SIC | Artículo | Cantidad | Entrega en | Observaciones |
| :-- | :------- | :------: | :--------- | :------------ |
@foreach ($solicitud->lineas as $linea)
| {{ $linea->folioSicDisplay() ?? '—' }} | {{ $linea->articulo?->descripcion ?? $linea->descripcion_libre ?? '—' }} | {{ $linea->cantidad_solicitada }} | {{ $linea->lugarEntrega?->nombre ?? '—' }} | {{ $linea->observaciones_especificaciones ?? '—' }} |
@endforeach
</x-mail::table>

Adjuntamos el PDF de esta solicitud para impresión/entrega.

@if ($solicitud->vendor?->es_interno)
Cuando entreguen lo solicitado, **confirmen la entrega en el sistema** (pantalla "Entregas internas") para que TI haga el ingreso.
@else
Quedamos al pendiente de su confirmación.
@endif

Saludos.
</x-mail::message>
