@props(['label' => null, 'name' => null])

<label class="inline-flex items-center gap-2 cursor-pointer select-none has-[:checked]:[&_.ui-toggle-dot]:translate-x-5 has-[:checked]:[&_.ui-toggle-track]:bg-primary">
    <input type="checkbox" @if($name) id="{{ $name }}" @endif {{ $attributes->class(['sr-only']) }}>

    <span class="ui-toggle-track relative h-6 w-11 shrink-0 rounded-full bg-gray-200 transition dark:bg-gray-700">
        <span class="ui-toggle-dot absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition"></span>
    </span>

    @if ($label)
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
    @endif
</label>
