@props(['color' => 'gray'])

@php
$colors = [
    'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700/50 dark:text-gray-300',
    'indigo' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',
    'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    'red' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
];
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $colors[$color] ?? $colors['gray']]) }}>
    {{ $slot }}
</span>
