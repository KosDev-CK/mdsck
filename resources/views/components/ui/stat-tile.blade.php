@props(['label', 'value', 'icon' => null, 'color' => 'primary', 'hint' => null])

@php
$colors = [
    'primary' => 'bg-primary/10 text-primary',
    'success' => 'bg-success/10 text-success',
    'danger' => 'bg-danger/10 text-danger',
    'warning' => 'bg-warning/10 text-warning',
    'info' => 'bg-info/10 text-info',
];
@endphp

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
