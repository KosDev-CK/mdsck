<div>
    @push('page-title')
        Conexiones a BD
    @endpush
    @push('page-actions')
        <x-ui.button wire:click="create" size="sm">
            Nueva conexión
        </x-ui.button>
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    @if ($showForm)
        <x-ui.card padding="p-6" class="mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">{{ $editingId ? 'Editar conexión' : 'Nueva conexión' }}</h2>

            <form wire:submit="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                    <input wire:model="name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Clave (identificador único)</label>
                    <input wire:model="key" type="text" placeholder="ej. oracle_finanzas" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @error('key') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Módulo (opcional)</label>
                    <input wire:model="module" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                    <select wire:model.live="driver" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                        <option value="mysql">MySQL</option>
                        <option value="pgsql">PostgreSQL</option>
                        <option value="sqlsrv">SQL Server</option>
                        <option value="api">API externa</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Modo de conexión</label>
                    <select wire:model="mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                        <option value="single">Conexión única</option>
                        <option value="pool">Pool de conexiones</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mt-6 dark:text-gray-300">
                        <input wire:model="isActive" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                        Activa
                    </label>
                </div>

                @if ($driver === 'api')
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL base</label>
                        <input wire:model="baseUrl" type="text" placeholder="https://api.ejemplo.com" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                        @error('baseUrl') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Host</label>
                        <input wire:model="host" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                        @error('host') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Puerto</label>
                        <input wire:model="port" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base de datos</label>
                        <input wire:model="database" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                        @error('database') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Usuario</label>
                    <input wire:model="username" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Contraseña {{ $editingId ? '(dejar vacío para no cambiarla)' : '' }}
                    </label>
                    <input wire:model="password" type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                </div>

                @if ($mode === 'pool')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mínimo de conexiones</label>
                        <input wire:model="poolMin" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Máximo de conexiones</label>
                        <input wire:model="poolMax" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                        @error('poolMax') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Opciones extra (JSON, ej. headers de la API)</label>
                    <textarea wire:model="extraJson" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm font-mono text-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"></textarea>
                    @error('extraJson') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                @if ($testResult)
                    @php [$status, $message] = explode(':', $testResult, 2); @endphp
                    <div class="md:col-span-2 text-sm {{ $status === 'ok' ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $message }}
                    </div>
                @endif

                <div class="md:col-span-2 flex gap-2">
                    <x-ui.button type="submit">
                        Guardar
                    </x-ui.button>
                    <x-ui.button type="button" wire:click="testConnection" variant="secondary">
                        Probar conexión
                    </x-ui.button>
                    <x-ui.button type="button" wire:click="$set('showForm', false)" variant="ghost">
                        Cancelar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                        <th class="py-2">Nombre</th>
                        <th class="py-2">Clave</th>
                        <th class="py-2">Tipo</th>
                        <th class="py-2">Modo</th>
                        <th class="py-2">Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($connections as $connection)
                        <tr class="border-b border-gray-50 dark:border-gray-800">
                            <td class="py-2 font-medium text-gray-900 whitespace-nowrap dark:text-gray-100">{{ $connection->name }}</td>
                            <td class="py-2 text-gray-500 font-mono text-xs whitespace-nowrap dark:text-gray-400">{{ $connection->key }}</td>
                            <td class="py-2 uppercase text-xs text-gray-500 whitespace-nowrap dark:text-gray-400">{{ $connection->driver }}</td>
                            <td class="py-2 text-gray-500 whitespace-nowrap dark:text-gray-400">{{ $connection->mode === 'pool' ? 'Pool' : 'Única' }}</td>
                            <td class="py-2 whitespace-nowrap">
                                <x-ui.badge :color="$connection->is_active ? 'emerald' : 'gray'">
                                    {{ $connection->is_active ? 'Activa' : 'Inactiva' }}
                                </x-ui.badge>
                            </td>
                            <td class="py-2 text-right space-x-2 whitespace-nowrap">
                                <button wire:click="edit({{ $connection->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>
                                <button wire:click="delete({{ $connection->id }})" wire:confirm="¿Eliminar esta conexión?" class="text-red-600 hover:text-red-500 text-sm dark:text-red-400 dark:hover:text-red-300">Eliminar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
