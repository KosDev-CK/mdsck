<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-1 dark:text-gray-100">Iniciar sesión</h1>
    <p class="text-sm text-gray-500 mb-6 dark:text-gray-400">Ingresa tu correo y te enviaremos un código de acceso.</p>

    @if (session('status'))
        <x-ui.alert variant="warning" class="mb-4">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    <form wire:submit="sendCode" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo electrónico</label>
            <input
                wire:model="email"
                type="email"
                id="email"
                autofocus
                autocomplete="username"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                placeholder="tu@empresa.com"
            >
            @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <button
            type="submit"
            class="w-full inline-flex justify-center items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            wire:loading.attr="disabled"
            wire:target="sendCode"
        >
            <span wire:loading.remove wire:target="sendCode">Enviar código</span>
            <span wire:loading wire:target="sendCode">Enviando…</span>
        </button>
    </form>
</div>
