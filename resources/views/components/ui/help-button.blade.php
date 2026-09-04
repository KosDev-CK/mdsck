@props(['event' => 'open-help'])

{{--
    No usa wire:click a propósito: este botón se renderiza vía
    @push('page-actions') dentro del topbar (partials/topbar.blade.php),
    FUERA del <div> que envuelve el componente Livewire de la pantalla —
    wire:click no funciona ahí porque Livewire solo enlaza directivas
    dentro del árbol DOM de su propio componente. Se dispara un evento de
    navegador (window), que sí cruza esa frontera, y <x-ui.help-modal>
    lo escucha con Alpine puro, sin ida y vuelta al servidor.
--}}
<button
    type="button"
    onclick="window.dispatchEvent(new CustomEvent('{{ $event }}'))"
    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
    title="Ayuda"
>
    <x-heroicon-o-question-mark-circle class="h-6 w-6" />
</button>
