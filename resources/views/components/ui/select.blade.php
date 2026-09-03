@props(['label' => null, 'name' => null, 'hint' => null])

<div>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    @endif

    <select
        @if($name) id="{{ $name }}" @endif
        {{ $attributes->class(['block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100', 'mt-1' => $label]) }}
    >
        {{ $slot }}
    </select>

    @if ($hint)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    @if ($name)
        @error($name)
            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    @endif
</div>
