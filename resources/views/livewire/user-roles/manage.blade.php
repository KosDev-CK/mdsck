<div>
    @push('page-title')
        Perfiles por usuario
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-ui.card padding="p-5" class="lg:col-span-2">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Buscar por nombre o correo…"
                class="mb-4 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                            <th class="py-2">Usuario</th>
                            <th class="py-2">Perfiles</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-gray-50 dark:border-gray-800 {{ $selectedUserId === $user->id ? 'bg-indigo-50/50 dark:bg-indigo-500/10' : '' }}">
                                <td class="py-2 whitespace-nowrap">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</div>
                                    <div class="text-gray-400 text-xs dark:text-gray-500">{{ $user->email }}</div>
                                </td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($user->getRoleNames() as $roleName)
                                            <x-ui.badge color="indigo">{{ $roleName }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button wire:click="selectUser({{ $user->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </x-ui.card>

        <x-ui.card padding="p-5">
            @if ($selectedUser)
                <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">{{ $selectedUser->name }}</h2>
                <p class="text-sm text-gray-500 mb-4 dark:text-gray-400">Marca los perfiles asignados.</p>

                <div class="space-y-2 mb-4">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                value="{{ $role->name }}"
                                wire:model="selectedRoles"
                                @disabled($selectedUser->hasRole('Administrador') && $role->name === 'Administrador')
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                            >
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <x-ui.button wire:click="saveRoles">
                        Guardar
                    </x-ui.button>
                    <x-ui.button wire:click="cancelEdit" variant="ghost">
                        Cancelar
                    </x-ui.button>
                </div>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500">Selecciona un usuario para editar sus perfiles.</p>
            @endif
        </x-ui.card>
    </div>
</div>
