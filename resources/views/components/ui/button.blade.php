@props(['variant' => 'primary', 'size' => 'md'])

@php
$base = 'inline-flex items-center justify-center gap-2 rounded-md font-medium transition disabled:opacity-50 disabled:cursor-not-allowed';

$sizes = [
    'sm' => 'px-2.5 py-1.5 text-xs',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-5 py-2.5 text-base',
];

$variants = [
    'primary' => 'bg-primary text-white hover:brightness-90',
    'secondary' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700',
    'danger' => 'bg-danger/10 text-danger hover:bg-danger/20',
    'ghost' => 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
];
@endphp

<button {{ $attributes->merge(['type' => 'button'])->class([$base, $sizes[$size] ?? $sizes['md'], $variants[$variant] ?? $variants['primary']]) }}>
    {{ $slot }}
</button>
