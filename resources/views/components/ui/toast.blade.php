@props(['variant' => 'info', 'timeout' => 5000])

@php
// Fondo sólido (no translúcido, a diferencia de x-ui.alert) porque este
// componente flota sobre contenido arbitrario del workspace — un fondo tenue
// se vuelve ilegible según lo que haya detrás. El color semántico vive en el
// acento izquierdo y el ícono; el texto queda neutro para máxima legibilidad.
$variants = [
    'success' => ['accent' => 'border-l-success', 'icon' => 'text-success'],
    'error' => ['accent' => 'border-l-danger', 'icon' => 'text-danger'],
    'warning' => ['accent' => 'border-l-warning', 'icon' => 'text-warning'],
    'info' => ['accent' => 'border-l-info', 'icon' => 'text-info'],
];
$colors = $variants[$variant] ?? $variants['info'];
@endphp

{{--
    wire:key único en cada render: el flash de sesión (session('status')/'error')
    sigue siendo verdadero en la petición Livewire INMEDIATA SIGUIENTE a la que lo
    generó (Laravel solo "envejece" los datos flash una petición después), así que
    si el usuario dispara otra acción justo después, este @if vuelve a ser true y
    Livewire, al ver el mismo elemento en la misma posición, lo actualiza in-place
    en vez de reemplazarlo — Alpine nunca vuelve a correr x-init sobre ese nodo, y
    el toast queda con show=false (oculto) para siempre tras el primer ciclo. Una
    key nueva en cada render fuerza a Livewire a tratarlo siempre como un elemento
    nuevo (remove+insert), así Alpine se re-inicializa y el toast se anima/temporiza
    de nuevo cada vez que aparece.
--}}
<div
    wire:key="toast-{{ uniqid() }}"
    x-data="{ show: false }"
    x-init="show = true; setTimeout(() => show = false, {{ (int) $timeout }})"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-2"
    x-on:click="show = false"
    {{--
        Los 3 lados neutros usan utilidades direccionales propias (border-t-*/
        border-r-*/border-b-*) en vez del shorthand `border-gray-*`: ese
        shorthand fija border-*-color en los 4 lados a la vez, incluido el
        izquierdo, y competía por especificidad con `border-l-{color}` del
        acento — en la práctica el shorthand ganaba y el acento nunca se veía.
        Con utilidades por lado no hay dos reglas tocando la misma propiedad.
    --}}
    {{ $attributes->class([
        'pointer-events-auto flex w-80 max-w-full items-start gap-2 rounded-md bg-white px-3 py-2 text-sm text-gray-900 shadow-lg cursor-pointer dark:bg-gray-800 dark:text-gray-100',
        'border-t border-r border-b border-t-gray-200 border-r-gray-200 border-b-gray-200 dark:border-t-gray-700 dark:border-r-gray-700 dark:border-b-gray-700',
        'border-l-4 '.$colors['accent'],
    ]) }}
>
    <div class="flex-1">{{ $slot }}</div>

    <button
        type="button"
        aria-label="Cerrar"
        x-on:click.stop="show = false"
        class="shrink-0 rounded-full p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 {{ $colors['icon'] }}"
    >
        <x-heroicon-o-x-mark class="h-4 w-4" />
    </button>
</div>
