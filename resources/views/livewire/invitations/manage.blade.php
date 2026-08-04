<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-6">Configuración de acceso</h1>

    @if (session('status'))
        <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Invitar persona</h2>

            <form wire:submit="send" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo</label>
                    <input wire:model="email" type="email" id="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-1">Perfiles que podrá ver</span>
                    <div class="space-y-1">
                        @foreach ($roles as $role)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" value="{{ $role->id }}" wire:model="roleIds" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('roleIds') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    Enviar invitación
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Invitaciones</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
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
                                    $invitation->isAccepted() => ['Aceptada', 'bg-emerald-50 text-emerald-700'],
                                    $invitation->isRevoked() => ['Revocada', 'bg-gray-100 text-gray-500'],
                                    $invitation->isExpired() => ['Vencida', 'bg-amber-50 text-amber-700'],
                                    default => ['Pendiente', 'bg-indigo-50 text-indigo-700'],
                                };
                            @endphp
                            <tr class="border-b border-gray-50">
                                <td class="py-2 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $invitation->name }}</div>
                                    <div class="text-gray-400 text-xs">{{ $invitation->email }}</div>
                                </td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($invitation->roles as $role)
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $role->name }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-2 whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $state[1] }}">{{ $state[0] }}</span>
                                </td>
                                <td class="py-2 text-right space-x-2 whitespace-nowrap">
                                    @if ($invitation->isPending())
                                        <button wire:click="resend({{ $invitation->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm">Reenviar</button>
                                        <button wire:click="revoke({{ $invitation->id }})" wire:confirm="¿Revocar esta invitación?" class="text-red-600 hover:text-red-500 text-sm">Revocar</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $invitations->links() }}</div>
        </div>
    </div>
</div>
