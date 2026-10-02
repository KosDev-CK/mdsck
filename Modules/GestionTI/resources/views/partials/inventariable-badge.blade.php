{{--
    Indicador informativo de "Inventariable": no se captura, sale del atributo
    `es_inventariable` del artículo elegido (`$articuloOptions`, que ya trae
    todos los artículos activos). Sin artículo (p. ej. descripción libre) se
    muestra "No". Props: $articuloId (?int).
--}}
@php
    $esInventariable = $articuloId ? (bool) ($articuloOptions->firstWhere('id', (int) $articuloId)?->es_inventariable) : false;
@endphp
<x-ui.badge :color="$esInventariable ? 'emerald' : 'gray'">{{ $esInventariable ? 'Sí' : 'No' }}</x-ui.badge>
