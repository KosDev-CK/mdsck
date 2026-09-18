@props(['icon', 'title', 'variant' => 'default', 'tag' => 'button', 'href' => null])

@php
// Botón de acción de solo ícono para filas de tabla: sin borde ni fondo en
// reposo (el fondo/borde solo aparece sutilmente en :hover, como feedback de
// que es clickeable) — el objetivo es que se vean solo los íconos, agrupados,
// sin el ruido visual de varios botones con relleno uno junto a otro.
//
// variant="danger" tiñe el ícono de un rojo sutil incluso en reposo (no solo
// al pasar el mouse) para que "Eliminar" se distinga del resto sin depender
// solo de la forma del ícono — usa los tokens de danger del tema, nunca un
// red-* hardcodeado.
$variants = [
    'default' => 'text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-700/50',
    'danger' => 'text-danger/70 hover:text-danger hover:bg-danger/10 dark:text-danger/70 dark:hover:bg-danger/20',
];

$base = 'inline-flex items-center justify-center rounded p-1.5 transition disabled:opacity-50 disabled:cursor-not-allowed';
$classes = $base.' '.($variants[$variant] ?? $variants['default']);
@endphp

@if ($tag === 'a')
    <a
        href="{{ $href }}"
        title="{{ $title }}"
        aria-label="{{ $title }}"
        {{ $attributes->class($classes) }}
    >
        <x-dynamic-component :component="$icon" class="h-4 w-4" />
    </a>
@else
    <button
        title="{{ $title }}"
        aria-label="{{ $title }}"
        {{ $attributes->merge(['type' => 'button'])->class($classes) }}
    >
        <x-dynamic-component :component="$icon" class="h-4 w-4" />
    </button>
@endif
