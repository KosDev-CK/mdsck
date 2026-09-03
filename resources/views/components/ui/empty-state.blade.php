@props(['title' => 'Sin registros', 'description' => null, 'icon' => 'inbox'])

<div {{ $attributes->class(['flex flex-col items-center justify-center text-center py-8']) }}>
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="h-8 w-8 text-gray-300 dark:text-gray-600 mb-2" />
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
    @if ($description)
        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $description }}</p>
    @endif
</div>
