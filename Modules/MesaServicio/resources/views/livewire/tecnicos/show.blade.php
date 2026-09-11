<div>
    @push('page-title')
        Ficha de técnico
    @endpush

    <x-ui.card padding="p-5" class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $tecnico->nombre }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tecnico->correo }}</p>
                @if ($tecnico->puesto)
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tecnico->puesto }}</p>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <x-ui.badge :color="$tecnico->activo ? 'emerald' : 'gray'">{{ $tecnico->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                @if ($tecnico->es_nivel_1)
                    <x-ui.badge color="indigo">Nivel 1</x-ui.badge>
                @endif
            </div>
        </div>
    </x-ui.card>

    <x-ui.card padding="p-5" class="mb-6">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Pendientes ({{ $pendientes->count() }})</h2>

        <x-ui.table
            :headers="['Folio', 'Asunto', 'Estado', 'Creado', 'Solicitante']"
            :empty="$pendientes->isEmpty()"
            empty-title="Sin tickets pendientes"
        >
            @foreach ($pendientes as $ticket)
                <tr wire:key="pendiente-{{ $ticket->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $ticket->display_id ?? $ticket->sdp_id }}</td>
                    <td class="py-2 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($ticket->asunto, 60) }}</td>
                    <td class="py-2">
                        <x-ui.badge color="amber">{{ $ticket->estado_nombre }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $ticket->created_time->format('d/m/Y H:i') }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $ticket->solicitante_nombre }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Atendidos ({{ $atendidos->count() }})</h2>

        <x-ui.table
            :headers="['Folio', 'Asunto', 'Estado', 'Completado', 'Solicitante']"
            :empty="$atendidos->isEmpty()"
            empty-title="Sin tickets atendidos"
        >
            @foreach ($atendidos as $ticket)
                <tr wire:key="atendido-{{ $ticket->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $ticket->display_id ?? $ticket->sdp_id }}</td>
                    <td class="py-2 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($ticket->asunto, 60) }}</td>
                    <td class="py-2">
                        <x-ui.badge color="emerald">{{ $ticket->estado_nombre }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $ticket->completed_time?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $ticket->solicitante_nombre }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
