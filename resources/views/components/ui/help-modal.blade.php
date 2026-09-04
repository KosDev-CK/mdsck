@props(['titulo', 'pdfUrl' => null, 'event' => 'open-help'])

{{--
    Modal de ayuda con estado 100% local en Alpine (no usa $wire.entangle
    como <x-ui.modal>) — se abre vía un evento de navegador porque el
    botón que la dispara (<x-ui.help-button>) vive fuera del árbol DOM
    del componente Livewire de la pantalla (ver el comentario en
    help-button.blade.php). No hay ningún dato de servidor que sincronizar
    para abrir/cerrar esta modal, así que Alpine puro es suficiente y evita
    el problema por completo.
--}}
<div
    x-data="{ open: false }"
    x-on:{{ $event }}.window="open = true"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
>
    <div x-show="open" x-transition.opacity x-on:click="open = false" class="fixed inset-0 bg-gray-900/50"></div>

    <div
        x-show="open"
        x-transition
        class="relative w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 dark:border dark:border-gray-800 max-h-[90vh] overflow-y-auto"
    >
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Ayuda — {{ $titulo }}</h3>
            <button type="button" x-on:click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="space-y-5 text-sm text-gray-700 dark:text-gray-300">
            {{ $slot }}
        </div>

        @if ($pdfUrl)
            <div class="mt-6 flex justify-end border-t border-gray-200 pt-4 dark:border-gray-800">
                <a href="{{ $pdfUrl }}" target="_blank">
                    <x-ui.button type="button" variant="secondary">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                        Descargar PDF
                    </x-ui.button>
                </a>
            </div>
        @endif
    </div>
</div>
