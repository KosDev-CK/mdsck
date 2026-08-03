<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-semibold text-gray-900">Conexiones a BD</h1>
        <button wire:click="create" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
            Nueva conexión
        </button>
    </div>

    @if (session('status'))
        <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
            {{ session('status') }}
        </div>
    @endif

    @if ($showForm)
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ $editingId ? 'Editar conexión' : 'Nueva conexión' }}</h2>

            <form wire:submit="save" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombre</label>
                    <input wire:model="name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Clave (identificador único)</label>
                    <input wire:model="key" type="text" placeholder="ej. oracle_finanzas" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    @error('key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Módulo (opcional)</label>
                    <input wire:model="module" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select wire:model.live="driver" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        <option value="mysql">MySQL</option>
                        <option value="pgsql">PostgreSQL</option>
                        <option value="sqlsrv">SQL Server</option>
                        <option value="api">API externa</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Modo de conexión</label>
                    <select wire:model="mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        <option value="single">Conexión única</option>
                        <option value="pool">Pool de conexiones</option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mt-6">
                        <input wire:model="isActive" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Activa
                    </label>
                </div>

                @if ($driver === 'api')
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">URL base</label>
                        <input wire:model="baseUrl" type="text" placeholder="https://api.ejemplo.com" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        @error('baseUrl') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Host</label>
                        <input wire:model="host" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        @error('host') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Puerto</label>
                        <input wire:model="port" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Base de datos</label>
                        <input wire:model="database" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        @error('database') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700">Usuario</label>
                    <input wire:model="username" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Contraseña {{ $editingId ? '(dejar vacío para no cambiarla)' : '' }}
                    </label>
                    <input wire:model="password" type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                </div>

                @if ($mode === 'pool')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mínimo de conexiones</label>
                        <input wire:model="poolMin" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Máximo de conexiones</label>
                        <input wire:model="poolMax" type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        @error('poolMax') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Opciones extra (JSON, ej. headers de la API)</label>
                    <textarea wire:model="extraJson" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm font-mono text-xs"></textarea>
                    @error('extraJson') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($testResult)
                    @php [$status, $message] = explode(':', $testResult, 2); @endphp
                    <div class="md:col-span-2 text-sm {{ $status === 'ok' ? 'text-emerald-700' : 'text-red-600' }}">
                        {{ $message }}
                    </div>
                @endif

                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        Guardar
                    </button>
                    <button type="button" wire:click="testConnection" class="inline-flex items-center rounded-md bg-white border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Probar conexión
                    </button>
                    <button type="button" wire:click="$set('showForm', false)" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
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
                    <tr class="border-b border-gray-50">
                        <td class="py-2 font-medium text-gray-900">{{ $connection->name }}</td>
                        <td class="py-2 text-gray-500 font-mono text-xs">{{ $connection->key }}</td>
                        <td class="py-2 uppercase text-xs text-gray-500">{{ $connection->driver }}</td>
                        <td class="py-2 text-gray-500">{{ $connection->mode === 'pool' ? 'Pool' : 'Única' }}</td>
                        <td class="py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $connection->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $connection->is_active ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td class="py-2 text-right space-x-2">
                            <button wire:click="edit({{ $connection->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm">Editar</button>
                            <button wire:click="delete({{ $connection->id }})" wire:confirm="¿Eliminar esta conexión?" class="text-red-600 hover:text-red-500 text-sm">Eliminar</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
