<div class="space-y-6 max-w-2xl">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Mi perfil</h1>

    @if (session('status'))
        <x-ui.alert variant="success">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Datos generales</h2>

        <form wire:submit="updateName" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo electrónico</label>
                <input type="email" value="{{ auth()->user()->email }}" disabled class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-500">
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <x-ui.button type="submit">
                Guardar
            </x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Verificación en dos pasos</h2>
        <p class="text-sm text-gray-500 mb-4 dark:text-gray-400">Usa una app como Microsoft o Google Authenticator para reforzar tu acceso.</p>

        @if ($recoveryCodes)
            <div class="mb-4 bg-amber-50 border border-amber-200 rounded-md p-4 dark:bg-amber-500/10 dark:border-amber-500/20">
                <p class="text-sm font-medium text-amber-800 mb-2 dark:text-amber-400">Guarda tus códigos de recuperación, no volverán a mostrarse:</p>
                <div class="grid grid-cols-2 gap-1 font-mono text-sm text-amber-900 dark:text-amber-300">
                    @foreach ($recoveryCodes as $code)
                        <span>{{ $code }}</span>
                    @endforeach
                </div>
                <button wire:click="downloadRecoveryCodes" type="button" class="mt-3 inline-flex items-center rounded-md bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-200 dark:bg-amber-500/20 dark:text-amber-300 dark:hover:bg-amber-500/30">
                    Descargar códigos (.txt)
                </button>
            </div>
        @endif

        @if (auth()->user()->hasTwoFactorEnabled())
            <p class="text-sm text-emerald-600 mb-4 dark:text-emerald-400">2FA está activo en tu cuenta.</p>

            <form wire:submit="disableTwoFactor" class="space-y-3">
                <div>
                    <label for="disableCode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ingresa un código para desactivarlo</label>
                    <input wire:model="disableCode" type="text" inputmode="numeric" maxlength="6" id="disableCode" class="mt-1 block w-40 text-center tracking-widest rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @error('disableCode') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
                <x-ui.button type="submit" variant="danger">
                    Desactivar 2FA
                </x-ui.button>
            </form>
        @elseif ($enablingTwoFactor)
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6">
                    <div class="bg-white border border-gray-200 rounded-md p-2 shrink-0 mx-auto sm:mx-0 dark:border-gray-700">
                        {!! $this->qrCodeSvg !!}
                    </div>
                    <div class="text-sm text-gray-600 w-full sm:w-auto dark:text-gray-400">
                        <p class="mb-2">Escanea el código con tu app Authenticator, o ingresa esta clave manualmente:</p>
                        <code class="block bg-gray-100 rounded px-2 py-1 text-xs break-all dark:bg-gray-800 dark:text-gray-300">{{ $pendingSecret }}</code>
                    </div>
                </div>

                <form wire:submit="confirmTwoFactor" class="space-y-3">
                    <div>
                        <label for="confirmationCode" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Código de verificación</label>
                        <input wire:model="confirmationCode" type="text" inputmode="numeric" maxlength="6" id="confirmationCode" class="mt-1 block w-40 text-center tracking-widest rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                        @error('confirmationCode') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2">
                        <x-ui.button type="submit">
                            Confirmar y activar
                        </x-ui.button>
                        <x-ui.button type="button" wire:click="cancelEnablingTwoFactor" variant="ghost">
                            Cancelar
                        </x-ui.button>
                    </div>
                </form>
            </div>
        @else
            <x-ui.button wire:click="startEnablingTwoFactor">
                Activar 2FA
            </x-ui.button>
        @endif
    </x-ui.card>

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Perfiles asignados</h2>
        <div class="flex flex-wrap gap-1 mt-2">
            @foreach (auth()->user()->getRoleNames() as $role)
                <x-ui.badge color="indigo">{{ $role }}</x-ui.badge>
            @endforeach
        </div>
    </x-ui.card>
</div>
