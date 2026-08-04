<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-6">Perfiles por usuario</h1>

    @if (session('status'))
        <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Buscar por nombre o correo…"
                class="mb-4 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
            >

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2">Usuario</th>
                            <th class="py-2">Perfiles</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-gray-50 {{ $selectedUserId === $user->id ? 'bg-indigo-50/50' : '' }}">
                                <td class="py-2 whitespace-nowrap">
                                    <div class="font-medium text-gray-900">{{ $user->name }}</div>
                                    <div class="text-gray-400 text-xs">{{ $user->email }}</div>
                                </td>
                                <td class="py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($user->getRoleNames() as $roleName)
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">{{ $roleName }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button wire:click="selectUser({{ $user->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm">
                                        Editar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            @if ($selectedUser)
                <h2 class="text-sm font-semibold text-gray-900 mb-1">{{ $selectedUser->name }}</h2>
                <p class="text-sm text-gray-500 mb-4">Marca los perfiles asignados.</p>

                <div class="space-y-2 mb-4">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                value="{{ $role->name }}"
                                wire:model="selectedRoles"
                                @disabled($selectedUser->hasRole('Administrador') && $role->name === 'Administrador')
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>

                <button wire:click="saveRoles" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    Guardar
                </button>
            @else
                <p class="text-sm text-gray-400">Selecciona un usuario para editar sus perfiles.</p>
            @endif
        </div>
    </div>
</div>
