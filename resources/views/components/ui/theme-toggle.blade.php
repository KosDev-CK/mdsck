@props(['class' => 'rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'])

<button
    type="button"
    onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('mds_theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');"
    {{ $attributes->class($class) }}
    title="Cambiar tema"
>
    <x-heroicon-o-sun class="h-5 w-5 hidden dark:inline" />
    <x-heroicon-o-moon class="h-5 w-5 dark:hidden" />
</button>
