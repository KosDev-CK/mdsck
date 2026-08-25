@switch($field->type)
    @case('long_text')
        <textarea wire:model="{{ $modelPath }}" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"></textarea>
        @break

    @case('number')
        <input wire:model="{{ $modelPath }}" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
        @break

    @case('date')
        <input wire:model="{{ $modelPath }}" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
        @break

    @case('email')
        <input wire:model="{{ $modelPath }}" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
        @break

    @case('single_choice')
        <select wire:model="{{ $modelPath }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Selecciona una opción</option>
            @foreach ($field->options ?? [] as $option)
                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
            @endforeach
        </select>
        @break

    @case('multiple_choice')
        <div class="mt-1 space-y-1">
            @foreach ($field->options ?? [] as $option)
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" value="{{ $option['value'] }}" wire:model="{{ $modelPath }}" class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-800">
                    {{ $option['label'] }}
                </label>
            @endforeach
        </div>
        @break

    @case('checkbox')
        <div class="mt-1">
            <input type="checkbox" wire:model="{{ $modelPath }}" class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-800">
        </div>
        @break

    @default
        <input wire:model="{{ $modelPath }}" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
@endswitch
