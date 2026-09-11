<div>
    @push('page-title')
        Técnicos
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar por nombre o correo..."
                class="w-full sm:w-64 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.select name="filterActivo" wire:model.live="filterActivo" class="sm:w-40">
                    <option value="todos">Todos</option>
                    <option value="activos">Activos</option>
                    <option value="inactivos">Inactivos</option>
                </x-ui.select>

                <x-ui.select name="filterNivel1" wire:model.live="filterNivel1" class="sm:w-48">
                    <option value="todos">Nivel 1: todos</option>
                    <option value="si">Nivel 1: sí</option>
                    <option value="no">Nivel 1: no</option>
                </x-ui.select>
            </div>
        </div>

        <x-ui.table
            :headers="['Nombre', 'Correo', 'Puesto', 'Estatus', 'Nivel 1']"
            :empty="$records->isEmpty()"
            empty-title="Sin técnicos"
            empty-description="Corre php artisan sdp:sync-technicians para sincronizar desde ServiceDesk Plus."
        >
            @foreach ($records as $record)
                <tr wire:key="tecnico-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->correo }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->puesto }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$record->activo ? 'emerald' : 'gray'">{{ $record->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                    </td>
                    <td class="py-2">
                        <x-ui.toggle
                            wire:click="toggleNivel1({{ $record->id }})"
                            :checked="$record->es_nivel_1"
                        />
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>
</div>
