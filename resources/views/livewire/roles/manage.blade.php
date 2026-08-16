<div>
    @push('page-title')
        Perfiles
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-3 dark:text-gray-100">Perfiles existentes</h2>

            <ul class="space-y-1 mb-4">
                @foreach ($roles as $role)
                    <li class="flex items-center justify-between rounded-md {{ $selectedRoleId === $role->id ? 'bg-indigo-50 dark:bg-indigo-500/10' : '' }}">
                        <button wire:click="selectRole({{ $role->id }})" class="flex-1 text-left px-3 py-2 text-sm {{ $selectedRoleId === $role->id ? 'text-indigo-700 font-medium dark:text-indigo-400' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $role->name }}
                            <span class="text-xs text-gray-400 dark:text-gray-500">({{ $role->users_count }})</span>
                        </button>

                        @if ($role->name !== 'Administrador')
                            <button
                                wire:click="deleteRole({{ $role->id }})"
                                wire:confirm="¿Eliminar el perfil {{ $role->name }}?"
                                class="px-2 text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400"
                            >
                                &times;
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>

            <form wire:submit="createRole" class="space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                <label for="newRoleName" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nuevo perfil</label>
                <input wire:model="newRoleName" type="text" id="newRoleName" class="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100" placeholder="Ej. Supervisor">
                @error('newRoleName') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <x-ui.button type="submit" size="sm">
                    Crear
                </x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card padding="p-5" class="lg:col-span-2">
            @if ($selectedRole)
                <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Pantallas para "{{ $selectedRole->name }}"</h2>
                <p class="text-sm text-gray-500 mb-4 dark:text-gray-400">Marca las pantallas que este perfil puede ver.</p>

                <div class="space-y-2 mb-4">
                    @foreach ($screens as $screen)
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                value="{{ $screen->permission_name }}"
                                wire:model="selectedPermissions"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                            >
                            {{ $screen->name }}
                        </label>
                    @endforeach
                </div>

                <x-ui.button wire:click="savePermissions">
                    Guardar permisos
                </x-ui.button>
            @else
                <p class="text-sm text-gray-400 dark:text-gray-500">Crea o selecciona un perfil para asignarle pantallas.</p>
            @endif
        </x-ui.card>
    </div>
</div>
