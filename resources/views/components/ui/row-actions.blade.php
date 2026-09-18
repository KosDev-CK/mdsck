{{-- Agrupa los <x-ui.icon-button> de una fila de tabla, alineados a la derecha. --}}
<div {{ $attributes->class(['inline-flex items-center gap-1']) }}>
    {{ $slot }}
</div>
