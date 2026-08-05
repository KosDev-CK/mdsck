@props(['color' => 'gray'])

@php
$colors = [
    'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700/50 dark:text-gray-300',
    'indigo' => 'bg-primary/10 text-primary',
    'emerald' => 'bg-success/10 text-success',
    'amber' => 'bg-warning/10 text-warning',
    'red' => 'bg-danger/10 text-danger',
    'info' => 'bg-info/10 text-info',
];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full text-xs font-medium', $colors[$color] ?? $colors['gray']])->merge(['style' => 'padding: 5px 10px;']) }}>
    {{ $slot }}
</span>
