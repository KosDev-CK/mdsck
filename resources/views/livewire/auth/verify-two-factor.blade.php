<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-1">Verificación en dos pasos</h1>

    @if (! $usingRecoveryCode)
        <p class="text-sm text-gray-500 mb-6">Ingresa el código de tu aplicación Authenticator.</p>

        <form wire:submit="verify" class="space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700">Código</label>
                <input
                    wire:model="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    id="code"
                    autofocus
                    maxlength="6"
                    class="mt-1 block w-full text-center tracking-[0.5em] text-lg font-semibold rounded-md border-gray-300 shadow-sm"
                    placeholder="······"
                >
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button
                type="submit"
                class="w-full inline-flex justify-center items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                wire:loading.attr="disabled"
                wire:target="verify"
            >
                <span wire:loading.remove wire:target="verify">Verificar</span>
                <span wire:loading wire:target="verify">Verificando…</span>
            </button>
        </form>
    @else
        <p class="text-sm text-gray-500 mb-6">Ingresa uno de tus códigos de recuperación de un solo uso.</p>

        <form wire:submit="verifyWithRecoveryCode" class="space-y-4">
            <div>
                <label for="recoveryCode" class="block text-sm font-medium text-gray-700">Código de recuperación</label>
                <input
                    wire:model="recoveryCode"
                    type="text"
                    autocomplete="off"
                    id="recoveryCode"
                    autofocus
                    class="mt-1 block w-full text-center tracking-widest font-mono uppercase rounded-md border-gray-300 shadow-sm"
                    placeholder="XXXXXXXXXX"
                >
                @error('recoveryCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button
                type="submit"
                class="w-full inline-flex justify-center items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                wire:loading.attr="disabled"
                wire:target="verifyWithRecoveryCode"
            >
                <span wire:loading.remove wire:target="verifyWithRecoveryCode">Verificar</span>
                <span wire:loading wire:target="verifyWithRecoveryCode">Verificando…</span>
            </button>
        </form>
    @endif

    <button wire:click="toggleRecoveryCode" type="button" class="mt-4 text-sm text-indigo-600 hover:text-indigo-500">
        @if ($usingRecoveryCode)
            Volver a usar mi app Authenticator
        @else
            ¿No tienes acceso a tu app? Usa un código de recuperación
        @endif
    </button>
</div>
