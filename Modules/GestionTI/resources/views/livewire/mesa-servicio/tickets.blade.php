<div>
    @push('page-title')
        Tickets
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar por folio SDP o solicitante..."
                class="w-full sm:w-72 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Folio SDP', 'Fecha', 'Solicitante', 'Solicitudes SIC', 'Observaciones', '']" :empty="$records->isEmpty()" empty-description="Agrega el primero con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="ticket-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">
                        {{ $record->sdp_display_id ?? $record->sdp_id ?? '—' }}
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha?->format('d/m/Y') }}</td>
                    <td class="py-2">{{ $record->empleado?->nombre }}</td>
                    <td class="py-2">
                        <x-ui.badge color="indigo">{{ $record->solicitudes_sic_count }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ $record->observaciones }}</td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="edit({{ $record->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nuevo') . ' — Ticket'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Folio SDP (ID interno)" name="form.sdp_id" wire:model="form.sdp_id" />
                <x-ui.input label="Folio SDP (visible)" name="form.sdp_display_id" wire:model="form.sdp_display_id" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Fecha" name="form.fecha" type="date" wire:model="form.fecha" />
                <x-ui.select label="Solicitante" name="form.empleado_id" wire:model="form.empleado_id">
                    <option value="">Selecciona...</option>
                    @foreach ($empleadoOptions as $empleado)
                        <option value="{{ $empleado->id }}">{{ $empleado->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.input label="Observaciones" name="form.observaciones" type="textarea" wire:model="form.observaciones" />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.help-modal titulo="Tickets" :pdf-url="route('gestionti.ayuda.pdf', 'tickets')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('tickets')])
    </x-ui.help-modal>
</div>
