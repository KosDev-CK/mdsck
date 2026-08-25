@props(['model', 'title' => null, 'maxWidth' => 'max-w-lg'])

<div
    x-data="{ open: $wire.entangle('{{ $model }}') }"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
>
    <div x-show="open" x-transition.opacity x-on:click="open = false" class="fixed inset-0 bg-gray-900/50"></div>

    <div
        x-show="open"
        x-transition
        {{ $attributes->class(['relative w-full rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 dark:border dark:border-gray-800 max-h-[90vh] overflow-y-auto', $maxWidth]) }}
    >
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            <button type="button" x-on:click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        {{ $slot }}
    </div>
</div>
