<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-1">Iniciar sesión</h1>
    <p class="text-sm text-gray-500 mb-6">Ingresa tu correo y te enviaremos un código de acceso.</p>

    @if (session('status'))
        <div class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="sendCode" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
            <input
                wire:model="email"
                type="email"
                id="email"
                autofocus
                autocomplete="username"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                placeholder="tu@empresa.com"
            >
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
