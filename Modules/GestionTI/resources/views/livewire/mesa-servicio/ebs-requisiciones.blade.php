<div>
    @push('page-title')
        SIC en EBS
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estatusColors = [
            'APPROVED' => 'emerald',
            'REJECTED' => 'red',
            'IN PROCESS' => 'indigo',
        ];
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-end gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="codigoFilter"
                type="search"
                placeholder="Buscar por código..."
                class="w-full sm:w-48 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
            <x-ui.select name="estatusFilter" wire:model.live="estatusFilter" class="sm:w-44">
                <option value="">Todos los estatus</option>
                @foreach ($estatusOptions as $estatus)
                    <option value="{{ $estatus }}">{{ $estatus }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="vinculacionFilter" wire:model.live="vinculacionFilter" class="sm:w-48">
                <option value="">Vinculada o no</option>
                <option value="vinculada">Vinculadas</option>
                <option value="no_vinculada">No vinculadas</option>
            </x-ui.select>
            <x-ui.input type="date" label="Desde" name="fechaDesde" wire:model.live="fechaDesde" />
            <x-ui.input type="date" label="Hasta" name="fechaHasta" wire:model.live="fechaHasta" />
        </div>

        <x-ui.table :headers="['Código', 'Descripción', 'Estatus', 'Fecha', 'Vinculada', '']" :empty="$records->isEmpty()" empty-description="Corre gestionti:ebs-sincronizar-creadas para traer datos reales de EBS.">
            @foreach ($records as $record)
                <tr wire:key="ebs-requisicion-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->code }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->description }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->status] ?? 'gray'">{{ $record->status ?? '—' }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_creacion?->format('d/m/Y') }}</td>
                    <td class="py-2">
                        @if ($record->solicitudSicBorrador)
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                SIC #{{ $record->solicitudSicBorrador->id }}
                                @if ($record->solicitudSicBorrador->ticket)
                                    — Ticket {{ $record->solicitudSicBorrador->ticket->sdp_display_id ?? $record->solicitudSicBorrador->ticket->sdp_id ?? ('#'.$record->solicitudSicBorrador->ticket->id) }}
                                @endif
                            </span>
                        @else
                            <span class="text-sm text-gray-400 dark:text-gray-500">No vinculada</span>
                        @endif
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">
                        @if (! $record->solicitudSicBorrador)
                            <button wire:click="openVincular({{ $record->id }})" class="text-sm text-primary hover:brightness-90">Vincular</button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showVincularModal" title="Vincular con una Solicitud de SIC">
        <div class="space-y-4">
            <input
                wire:model.live.debounce.300ms="vincularSearch"
                type="search"
                placeholder="Buscar por folio SIC, ticket o solicitante..."
                class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            @error('vincularSolicitudId')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if ($vincularSearch !== '')
                <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($solicitudOptions as $opcion)
                        <label class="flex items-center gap-2 py-2 cursor-pointer">
                            <input type="radio" wire:model="vincularSolicitudId" value="{{ $opcion->id }}">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                SIC #{{ $opcion->id }} — {{ $opcion->empleado?->nombre }}
                                — {{ $opcion->folio_sic ?: 'Sin folio' }}
                                @if ($opcion->ticket)
                                    (Ticket {{ $opcion->ticket->sdp_display_id ?? $opcion->ticket->sdp_id ?? ('#'.$opcion->ticket->id) }})
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 py-2">Sin resultados.</p>
                    @endforelse
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelVincular">Cancelar</x-ui.button>
                <x-ui.button type="button" wire:click="confirmVincular">Vincular</x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
