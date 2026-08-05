@props(['padding' => 'p-6'])

<div {{ $attributes->class(['bg-white rounded-xl border border-gray-200 dark:bg-gray-900 dark:border-gray-800', $padding]) }}>
    {{ $slot }}
</div>
