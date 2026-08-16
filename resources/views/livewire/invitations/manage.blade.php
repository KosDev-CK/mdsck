<div>
    @push('page-title')
        Configuración de acceso
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Invitar persona</h2>

            <form wire:submit="send" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                    <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo</label>
                    <input wire:model="email" type="email" id="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @error('email') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-300">Perfiles que podrá ver</span>
                    <div class="space-y-1">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" value="{{ $role->id }}" wire:model="roleIds" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('roleIds') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <x-ui.button type="submit">
                    Enviar invitación
                </x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card padding="p-5" class="lg:col-span-2">
            <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Invitaciones</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                            <th class="py-2">Nombre</th>
                            <th class="py-2">Perfiles</th>
                            <th class="py-2">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invitations as $invitation)
                            @php
                                $state = match(true) {
                                    $invitation->isAccepted() => ['Aceptada', 'emerald'],
                                    $invitation->isRevoked() => ['Revocada', 'gray'],
                                    $invitation->isExpired() => ['Vencida', 'amber'],
                                    default => ['Pendiente', 'indigo'],
                                };
                            @endphp
                            <tr class="border-b border-gray-50 dark:border-gray-800">
                                <td class="py-2 whitespace-nowrap">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $invitation->name }}</div>
                                    <div class="text-gray-400 text-xs dark:text-gray-500">{{ $invitation->email }}</div>
                                </td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($invitation->roles as $role)
                                            <x-ui.badge color="gray">{{ $role->name }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-2 whitespace-nowrap">
                                    <x-ui.badge :color="$state[1]">{{ $state[0] }}</x-ui.badge>
                                </td>
                                <td class="py-2 text-right space-x-2 whitespace-nowrap">
                                    @if ($invitation->isPending())
                                        <button wire:click="resend({{ $invitation->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Reenviar</button>
                                        <button wire:click="revoke({{ $invitation->id }})" wire:confirm="¿Revocar esta invitación?" class="text-red-600 hover:text-red-500 text-sm dark:text-red-400 dark:hover:text-red-300">Revocar</button>
                                    @elseif ($invitation->isAccepted() && $invitation->user)
                                        <x-ui.badge :color="$invitation->user->is_active ? 'emerald' : 'gray'">
                                            {{ $invitation->user->is_active ? 'Activo' : 'Inactivo' }}
                                        </x-ui.badge>

                                        <button
                                            wire:click="toggleActive({{ $invitation->user->id }})"
                                            wire:confirm="¿{{ $invitation->user->is_active ? 'Desactivar' : 'Reactivar' }} a {{ $invitation->user->name }}?"
                                            class="text-sm {{ $invitation->user->is_active ? 'text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300' : 'text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300' }}"
                                        >
                                            {{ $invitation->user->is_active ? 'Desactivar' : 'Reactivar' }}
                                        </button>

                                        @if ($invitation->user->hasTwoFactorEnabled())
                                            <button wire:click="startRevokingTwoFactor({{ $invitation->user->id }})" class="text-amber-600 hover:text-amber-500 text-sm dark:text-amber-400 dark:hover:text-amber-300">
                                                Revocar 2FA
                                            </button>
                                        @endif
                                    @endif
                                </td>
                            </tr>

                            @if ($invitation->user && $revokingTwoFactorUserId === $invitation->user->id)
                                <tr class="border-b border-gray-50 bg-amber-50/50 dark:border-gray-800 dark:bg-amber-500/5">
                                    <td colspan="4" class="py-3">
                                        <form wire:submit="confirmRevokeTwoFactor" class="flex flex-wrap items-end gap-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    @if (auth()->user()->hasTwoFactorEnabled())
                                                        Ingresa tu propio código 2FA para confirmar
                                                    @else
                                                        Confirma que quieres revocar el 2FA de {{ $invitation->user->name }}
                                                    @endif
                                                </label>
                                                @if (auth()->user()->hasTwoFactorEnabled())
                                                    <input
                                                        wire:model="adminTwoFactorCode"
                                                        type="text"
                                                        inputmode="numeric"
                                                        maxlength="6"
                                                        autofocus
                                                        class="mt-1 w-32 text-center tracking-widest rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                                                    >
                                                    @error('adminTwoFactorCode') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                                @endif
                                            </div>

                                            <x-ui.button type="submit" variant="danger" size="sm">
                                                Confirmar revocación
                                            </x-ui.button>
                                            <x-ui.button type="button" wire:click="cancelRevokingTwoFactor" variant="ghost" size="sm">
                                                Cancelar
                                            </x-ui.button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $invitations->links() }}</div>
        </x-ui.card>
    </div>
</div>
