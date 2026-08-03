<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-6">Bitácora de seguridad</h1>

    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Usuario o correo…"
                class="rounded-md border-gray-300 shadow-sm sm:text-sm"
            >

            <select wire:model.live="eventType" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                <option value="">Todos los eventos</option>
                @foreach ($eventTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <input wire:model.live="from" type="date" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
            <input wire:model.live="to" type="date" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
        </div>

        <button wire:click="resetFilters" class="mt-3 text-sm text-gray-500 hover:text-gray-700">
            Limpiar filtros
        </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                    <th class="py-2">Fecha</th>
                    <th class="py-2">Usuario</th>
                    <th class="py-2">Evento</th>
                    <th class="py-2">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr class="border-b border-gray-50">
                        <td class="py-2 text-gray-500 whitespace-nowrap">{{ $event->created_at->format('Y-m-d H:i') }}</td>
                        <td class="py-2">
                            <div class="font-medium text-gray-900">{{ $event->user?->name ?? '—' }}</div>
                            <div class="text-gray-400 text-xs">{{ $event->email }}</div>
                        </td>
                        <td class="py-2">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                {{ $eventTypes[$event->event_type] ?? $event->event_type }}
                            </span>
                        </td>
                        <td class="py-2 text-gray-500">{{ $event->ip_address }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-gray-400">Sin eventos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">{{ $events->links() }}</div>
    </div>
</div>
