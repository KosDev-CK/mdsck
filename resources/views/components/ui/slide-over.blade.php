@props(['title', 'event' => 'open-slide-over', 'closeEvent' => null, 'widthClass' => 'max-w-md'])

{{--
    Panel lateral (off-canvas/drawer) genérico — mismo espíritu que
    <x-ui.modal>/<x-ui.help-modal> (backdrop + Escape + botón "X"), pero
    deslizando desde la derecha en vez de centrado, y pensado para vivir
    fuera del árbol DOM del componente Livewire que lo dispara (por eso se
    abre vía evento de navegador, no wire:click — ver el comentario en
    help-button.blade.php sobre por qué un botón en @push('page-actions')
    no puede usar wire:click directo).

    Abrir/cerrar es 100% Alpine, sin ningún request a Livewire. El
    contenido del slot sí puede tener wire:model/wire:click normales —
    "closeEvent" es opcional, para cuando una acción de servidor (ej. un
    botón "Aplicar" dentro del panel) necesita cerrarlo tras completarse.
--}}
<div
    x-data="{ open: false }"
    x-on:{{ $event }}.window="open = true"
    @if ($closeEvent)
        x-on:{{ $closeEvent }}.window="open = false"
    @endif
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50"
>
    <div x-show="open" x-transition.opacity x-on:click="open = false" class="fixed inset-0 bg-gray-900/50"></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        {{ $attributes->class(['fixed inset-y-0 right-0 flex w-full flex-col bg-white shadow-xl dark:bg-gray-900 dark:border-l dark:border-gray-800', $widthClass]) }}
    >
        <div class="flex items-center justify-between border-b border-gray-100 p-5 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            <button type="button" x-on:click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto p-5 text-sm text-gray-700 dark:text-gray-300">
            {{ $slot }}
        </div>
    </div>
</div>
