<div class="space-y-6 max-w-2xl">
    <h1 class="text-lg font-semibold text-gray-900">Mi perfil</h1>

    @if (session('status'))
        <div class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Datos generales</h2>

        <form wire:submit="updateName" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                <input type="email" value="{{ auth()->user()->email }}" disabled class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 sm:text-sm">
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
                <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                Guardar
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1">Verificación en dos pasos</h2>
        <p class="text-sm text-gray-500 mb-4">Usa una app como Microsoft o Google Authenticator para reforzar tu acceso.</p>

        @if ($recoveryCodes)
            <div class="mb-4 bg-amber-50 border border-amber-200 rounded-md p-4">
                <p class="text-sm font-medium text-amber-800 mb-2">Guarda tus códigos de recuperación, no volverán a mostrarse:</p>
                <div class="grid grid-cols-2 gap-1 font-mono text-sm text-amber-900">
                    @foreach ($recoveryCodes as $code)
                        <span>{{ $code }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        @if (auth()->user()->hasTwoFactorEnabled())
            <p class="text-sm text-emerald-600 mb-4">2FA está activo en tu cuenta.</p>

            <form wire:submit="disableTwoFactor" class="space-y-3">
                <div>
                    <label for="disableCode" class="block text-sm font-medium text-gray-700">Ingresa un código para desactivarlo</label>
                    <input wire:model="disableCode" type="text" inputmode="numeric" maxlength="6" id="disableCode" class="mt-1 block w-40 text-center tracking-widest rounded-md border-gray-300 shadow-sm sm:text-sm">
                    @error('disableCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="inline-flex items-center rounded-md bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                    Desactivar 2FA
                </button>
            </form>
        @elseif ($enablingTwoFactor)
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6">
                    <div class="border border-gray-200 rounded-md p-2 shrink-0 mx-auto sm:mx-0">
                        {!! $this->qrCodeSvg !!}
                    </div>
                    <div class="text-sm text-gray-600 w-full sm:w-auto">
                        <p class="mb-2">Escanea el código con tu app Authenticator, o ingresa esta clave manualmente:</p>
                        <code class="block bg-gray-100 rounded px-2 py-1 text-xs break-all">{{ $pendingSecret }}</code>
                    </div>
                </div>

                <form wire:submit="confirmTwoFactor" class="space-y-3">
                    <div>
                        <label for="confirmationCode" class="block text-sm font-medium text-gray-700">Código de verificación</label>
                        <input wire:model="confirmationCode" type="text" inputmode="numeric" maxlength="6" id="confirmationCode" class="mt-1 block w-40 text-center tracking-widest rounded-md border-gray-300 shadow-sm sm:text-sm">
                        @error('confirmationCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                            Confirmar y activar
                        </button>
                        <button type="button" wire:click="cancelEnablingTwoFactor" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        @else
            <button wire:click="startEnablingTwoFactor" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                Activar 2FA
            </button>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1">Perfiles asignados</h2>
        <div class="flex flex-wrap gap-1 mt-2">
            @foreach (auth()->user()->getRoleNames() as $role)
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $role }}</span>
            @endforeach
        </div>
    </div>
</div>
