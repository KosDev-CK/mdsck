<x-mail::message>
# Por recibir — solicitud {{ $solicitud->folio }}

Hola,

Se hizo la siguiente solicitud de compra y a ti te corresponde recibir lo que llegue a **{{ $sitios->implode(', ') ?: 'tu sitio' }}**:

- **Folio:** {{ $solicitud->folio }}
- **Proveedor:** {{ $solicitud->vendor?->nombre_comercial ?? '—' }}@if ($solicitud->vendor?->es_interno) (área interna)@endif

@if ($solicitud->fecha_entrega_prometida)
- **Fecha de entrega comprometida:** {{ $solicitud->fecha_entrega_prometida->format('d/m/Y') }}
@endif
- **Solicitó:** {{ $solicitud->creadoPor?->name ?? 'No especificado' }}

<x-mail::table>
| Línea | SIC | Artículo | Cantidad | Entrega en | Observaciones |
| :---- | :-- | :------- | :------: | :--------- | :------------ |
@foreach ($lineas as $linea)
| {{ $solicitud->folio }}-L{{ $ordinales[$linea->id] ?? '?' }} | {{ $linea->folioSicDisplay() ?? '—' }} | {{ $linea->articulo?->descripcion ?? $linea->descripcion_libre ?? '—' }} | {{ $linea->cantidad_solicitada }} | {{ $linea->lugarEntrega?->nombre ?? '—' }} | {{ $linea->observaciones_especificaciones ?? '—' }} |
@endforeach
</x-mail::table>

Adjuntamos el PDF completo de la solicitud: cada lugar de entrega viene en su propia hoja, con el código de cada línea para escanearlo al recibir.

Este es un aviso informativo; no tienes que responder.

Saludos.
</x-mail::message>
