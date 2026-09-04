<div>
    @push('page-title')
        Mantenimiento
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $tipoLabels = [
            'preventivo' => 'Preventivo',
            'correctivo' => 'Correctivo',
        ];
        $origenLabels = [
            'interno' => 'Interno',
            'externo' => 'Externo',
        ];
        $estatusLabels = [
            'programado' => 'Programado',
            'en_proceso' => 'En proceso',
            'realizado' => 'Realizado',
            'cancelado' => 'Cancelado',
            'reprogramado' => 'Reprogramado',
        ];
        $estatusColors = [
            'programado' => 'indigo',
            'en_proceso' => 'amber',
            'realizado' => 'emerald',
            'cancelado' => 'gray',
            'reprogramado' => 'amber',
        ];
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Código de activo..."
                    class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >

                <x-ui.select name="tipoFilter" wire:model.live="tipoFilter">
                    <option value="">Todos los tipos</option>
                    @foreach ($tipoLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select name="origenFilter" wire:model.live="origenFilter">
                    <option value="">Todos los orígenes</option>
                    @foreach ($origenLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select name="estatusFilter" wire:model.live="estatusFilter">
                    <option value="">Todos los estatus</option>
                    @foreach ($estatusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table
            :headers="['Activo', 'Tipo', 'Origen', 'Fecha programada', 'Fecha realizada', 'Estatus', 'Proveedor/Realizado por', '']"
            :empty="$records->isEmpty()"
            empty-description="Agrega el primero con el botón Nuevo."
        >
            @foreach ($records as $record)
                <tr wire:key="mantenimiento-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">
                        {{ $record->asset?->codigo }}
                        @if ($record->asset)
                            <a href="{{ route('gestionti.ficha-activo.show', $record->asset_id) }}" class="ml-2 text-xs font-normal text-primary hover:brightness-90">Ver ficha</a>
                        @endif
                    </td>
                    <td class="py-2">{{ $tipoLabels[$record->tipo] ?? $record->tipo }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $origenLabels[$record->origen_ejecucion] ?? $record->origen_ejecucion }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_programada?->format('d/m/Y') ?? '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_realizada?->format('d/m/Y') ?? '—' }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->estatus] ?? 'gray'">{{ $estatusLabels[$record->estatus] ?? $record->estatus }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">
                        @if ($record->origen_ejecucion === 'externo')
                            {{ $record->vendor?->nombre_comercial ?? '—' }}
                        @else
                            {{ $record->realizadoPor?->nombre ?? '—' }}
                        @endif
                    </td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        @if (in_array($record->estatus, ['programado', 'reprogramado'], true))
                            <button wire:click="openReprogramar({{ $record->id }})" class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                                Reprogramar
                            </button>
                            <button wire:click="iniciar({{ $record->id }})" wire:confirm="¿Iniciar este mantenimiento?" class="text-sm text-primary hover:underline">
                                Iniciar
                            </button>
                        @endif

                        @if ($record->estatus === 'en_proceso')
                            <button wire:click="openCompletar({{ $record->id }})" class="text-sm text-primary hover:underline">
                                Completar
                            </button>
                        @endif

                        @if (in_array($record->estatus, ['programado', 'reprogramado', 'en_proceso'], true))
                            <button wire:click="cancelar({{ $record->id }})" wire:confirm="¿Cancelar este mantenimiento?" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">
                                Cancelar
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" title="Nuevo — Mantenimiento" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Activo" name="form.asset_id" wire:model.live="form.asset_id">
                <option value="">Selecciona...</option>
                @foreach ($assetOptions as $asset)
                    <option value="{{ $asset->id }}">{{ $this->assetOptionLabel($asset) }}</option>
                @endforeach
            </x-ui.select>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Tipo" name="form.tipo" wire:model.live="form.tipo">
                    <option value="">Selecciona...</option>
                    @foreach ($tipoLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Ticket relacionado (opcional)" name="form.ticket_id" wire:model="form.ticket_id">
                    <option value="">Sin ticket</option>
                    @foreach ($ticketOptions as $ticket)
                        <option value="{{ $ticket->id }}">{{ $ticket->sdp_display_id ?? "Ticket #{$ticket->id}" }} — {{ $ticket->fecha?->format('d/m/Y') }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Origen de ejecución" name="form.origen_ejecucion" wire:model.live="form.origen_ejecucion">
                    <option value="">Selecciona...</option>
                    @foreach ($origenLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Fecha programada" name="form.fecha_programada" type="date" wire:model="form.fecha_programada" hint="Se sugiere automáticamente para mantenimiento preventivo con periodicidad activa — editable." />
            </div>

            @if (($form['origen_ejecucion'] ?? null) === 'externo')
                <x-ui.select label="Proveedor" name="form.vendor_id" wire:model="form.vendor_id">
                    <option value="">Selecciona...</option>
                    @foreach ($vendorOptions as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showReprogramarModal" title="Reprogramar mantenimiento">
        <form wire:submit="confirmReprogramar" class="space-y-4">
            <x-ui.input label="Nueva fecha programada" name="reprogramarForm.fecha_programada" type="date" wire:model="reprogramarForm.fecha_programada" />
            <x-ui.input label="Motivo (opcional)" name="reprogramarForm.motivo" type="textarea" wire:model="reprogramarForm.motivo" />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelReprogramar">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showCompletarModal" title="Completar mantenimiento" max-width="max-w-2xl">
        <form wire:submit="confirmCompletar" class="space-y-4">
            <x-ui.input label="Fecha realizada" name="completarForm.fecha_realizada" type="date" wire:model="completarForm.fecha_realizada" />
            <x-ui.input label="Diagnóstico" name="completarForm.diagnostico" type="textarea" wire:model="completarForm.diagnostico" />

            @if ($completandoRecord?->origen_ejecucion === 'externo')
                <x-ui.input label="Costo" name="completarForm.costo" type="number" step="0.01" wire:model="completarForm.costo" />
            @else
                <x-ui.select label="Realizado por" name="completarForm.realizado_por_id" wire:model="completarForm.realizado_por_id">
                    <option value="">Selecciona...</option>
                    @foreach ($validadorOptions as $validador)
                        <option value="{{ $validador->id }}">{{ $validador->nombre }}</option>
                    @endforeach
                </x-ui.select>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Orden de servicio / reporte (opcional)</label>
                <input wire:model="completarAdjunto" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300">
                <div wire:loading wire:target="completarAdjunto" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Subiendo...</div>
                @error('completarAdjunto')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelCompletar">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.help-modal titulo="Mantenimiento" :pdf-url="route('gestionti.ayuda.pdf', 'mantenimientos')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('mantenimientos')])
    </x-ui.help-modal>
</div>
