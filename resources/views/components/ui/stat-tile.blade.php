@props(['label', 'value', 'icon' => null, 'color' => 'primary', 'hint' => null, 'variant' => 'icon'])

@php
$colors = [
    'primary' => 'bg-primary/10 text-primary',
    'success' => 'bg-success/10 text-success',
    'danger' => 'bg-danger/10 text-danger',
    'warning' => 'bg-warning/10 text-warning',
    'info' => 'bg-info/10 text-info',
];

// Tailwind escanea el código fuente buscando literales de clase — una
// interpolación tipo "text-{{ $color }}" nunca generaría la utilidad en el
// build de producción, por eso cada variante de color necesita su propio
// mapa de clases completas (mismo patrón que $colors arriba y que
// x-ui.badge/x-ui.alert).
$textColors = [
    'primary' => 'text-primary',
    'success' => 'text-success',
    'danger' => 'text-danger',
    'warning' => 'text-warning',
    'info' => 'text-info',
];

$topBorderColors = [
    'primary' => 'border-t-primary',
    'success' => 'border-t-success',
    'danger' => 'border-t-danger',
    'warning' => 'border-t-warning',
    'info' => 'border-t-info',
];
@endphp

@if ($variant === 'accent')
    {{--
        Variante "reporte ejecutivo" (borde superior de color + valor grande
        del mismo color + etiqueta en mayúsculas) — pensada para los KPIs
        principales de un dashboard tipo Dashboard Ejecutivo. `$hint` sigue
        siendo texto plano gris (igual que la variante `icon`); el slot por
        defecto es para contenido extra opcional debajo (ej. un
        <x-ui.badge> de tendencia), ya que no todo KPI de esta variante
        necesita una píldora.
    --}}
    <x-ui.card padding="p-5" {{ $attributes->class(['border-t-4', $topBorderColors[$color] ?? $topBorderColors['primary']]) }}>
        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $label }}</p>
        <p class="mt-1.5 text-3xl font-bold {{ $textColors[$color] ?? $textColors['primary'] }}">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div class="mt-2">{{ $slot }}</div>
        @endif
    </x-ui.card>
@else
    <x-ui.card padding="p-5" {{ $attributes }}>
        <div class="flex items-center gap-4">
            @if ($icon)
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $colors[$color] ?? $colors['primary'] }}">
                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="h-5 w-5" />
                </div>
            @endif
            <div class="min-w-0">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $value }}</p>
                @if ($hint)
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $hint }}</p>
                @endif
            </div>
        </div>
    </x-ui.card>
@endif
