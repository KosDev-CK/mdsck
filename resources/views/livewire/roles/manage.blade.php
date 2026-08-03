<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-6">Perfiles</h1>

    @if (session('status'))
        <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Perfiles existentes</h2>

            <ul class="space-y-1 mb-4">
                @foreach ($roles as $role)
                    <li class="flex items-center justify-between rounded-md {{ $selectedRoleId === $role->id ? 'bg-indigo-50' : '' }}">
                        <button wire:click="selectRole({{ $role->id }})" class="flex-1 text-left px-3 py-2 text-sm {{ $selectedRoleId === $role->id ? 'text-indigo-700 font-medium' : 'text-gray-700' }}">
                            {{ $role->name }}
                            <span class="text-xs text-gray-400">({{ $role->users_count }})</span>
                        </button>

                        @if ($role->name !== 'Administrador')
                            <button
                                wire:click="deleteRole({{ $role->id }})"
                                wire:confirm="¿Eliminar el perfil {{ $role->name }}?"
                                class="px-2 text-gray-400 hover:text-red-600"
                            >
                                &times;
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>

            <form wire:submit="createRole" class="space-y-2 border-t border-gray-100 pt-4">
                <label for="newRoleName" class="block text-sm font-medium text-gray-700">Nuevo perfil</label>
                <input wire:model="newRoleName" type="text" id="newRoleName" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" placeholder="Ej. Supervisor">
                @error('newRoleName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">
                    Crear
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
            @if ($selectedRole)
                <h2 class="text-sm font-semibold text-gray-900 mb-1">Pantallas para "{{ $selectedRole->name }}"</h2>
                <p class="text-sm text-gray-500 mb-4">Marca las pantallas que este perfil puede ver.</p>

                <div class="space-y-2 mb-4">
                    @foreach ($screens as $screen)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                value="{{ $screen->permission_name }}"
                                wire:model="selectedPermissions"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            {{ $screen->name }}
                        </label>
                    @endforeach
                </div>

                <button wire:click="savePermissions" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    Guardar permisos
                </button>
            @else
                <p class="text-sm text-gray-400">Crea o selecciona un perfil para asignarle pantallas.</p>
            @endif
        </div>
    </div>
</div>
