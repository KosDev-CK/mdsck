<div>
    @push('page-title')
        Bitácora de seguridad
    @endpush

    <x-ui.card padding="p-5" class="mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Usuario o correo…"
                class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            <select wire:model.live="eventType" class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                <option value="">Todos los eventos</option>
                @foreach ($eventTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <input wire:model.live="from" type="date" class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
            <input wire:model.live="to" type="date" class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
        </div>

        <button wire:click="resetFilters" class="mt-3 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            Limpiar filtros
        </button>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                        <th class="py-2">Fecha</th>
                        <th class="py-2">Usuario</th>
                        <th class="py-2">Evento</th>
                        <th class="py-2">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr class="border-b border-gray-50 dark:border-gray-800">
                            <td class="py-2 text-gray-500 whitespace-nowrap dark:text-gray-400">{{ $event->created_at->format('Y-m-d H:i') }}</td>
                            <td class="py-2 whitespace-nowrap">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $event->user?->name ?? '—' }}</div>
                                <div class="text-gray-400 text-xs dark:text-gray-500">{{ $event->email }}</div>
                            </td>
                            <td class="py-2 whitespace-nowrap">
                                <x-ui.badge color="gray">
                                    {{ $eventTypes[$event->event_type] ?? $event->event_type }}
                                </x-ui.badge>
                            </td>
                            <td class="py-2 text-gray-500 whitespace-nowrap dark:text-gray-400">{{ $event->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-400 dark:text-gray-500">Sin eventos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $events->links() }}</div>
    </x-ui.card>
</div>
