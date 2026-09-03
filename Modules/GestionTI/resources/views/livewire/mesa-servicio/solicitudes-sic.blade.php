<div>
    @push('page-title')
        Solicitud de SIC
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estatusLabels = [
            'capturado' => 'Capturado',
            'sic_creada' => 'SIC creada',
            'autorizada' => 'Autorizada',
            'rechazada' => 'Rechazada',
        ];
        $estatusColors = [
            'capturado' => 'gray',
            'sic_creada' => 'indigo',
            'autorizada' => 'emerald',
            'rechazada' => 'red',
        ];
        $urgenciaColors = [
            'baja' => 'gray',
            'media' => 'amber',
            'alta' => 'red',
        ];
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Buscar por folio SIC, ticket o solicitante..."
                    class="w-full sm:w-72 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >
                <x-ui.select name="estatusFilter" wire:model.live="estatusFilter" class="sm:w-48">
                    <option value="">Todos los estatus</option>
                    @foreach ($estatusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Ticket', 'Solicitante', 'Tipo de equipo', 'Urgencia', 'Centro de costo', 'Estatus', 'Folio SIC', 'Fecha', '']" :empty="$records->isEmpty()" empty-description="Agrega el primero con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="solicitud-sic-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">
                        {{ $record->ticket?->sdp_display_id ?? $record->ticket?->sdp_id ?? '—' }}
                    </td>
                    <td class="py-2">{{ $record->empleado?->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->tipoEquipo?->nombre }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$urgenciaColors[$record->urgencia] ?? 'gray'">{{ ucfirst($record->urgencia) }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->centroCosto?->nombre }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->estatus] ?? 'gray'">{{ $estatusLabels[$record->estatus] ?? $record->estatus }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->folio_sic }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_solicitud?->format('d/m/Y') }}</td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="exportSicPdf({{ $record->id }})" class="text-sm text-primary hover:brightness-90">Generar PDF</button>
                        <button wire:click="edit({{ $record->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>

                        @if ($record->estatus === 'capturado')
                            <button wire:click="openAdvance({{ $record->id }})" class="text-sm text-primary hover:brightness-90">Marcar SIC creada</button>
                        @elseif ($record->estatus === 'sic_creada')
                            <button
                                wire:click="marcarAutorizada({{ $record->id }})"
                                wire:confirm="¿Marcar esta solicitud como autorizada?"
                                class="text-sm text-emerald-600 hover:text-emerald-500 dark:text-emerald-400 dark:hover:text-emerald-300"
                            >
                                Autorizar
                            </button>
                            <button
                                wire:click="marcarRechazada({{ $record->id }})"
                                wire:confirm="¿Marcar esta solicitud como rechazada?"
                                class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300"
                            >
                                Rechazar
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nueva') . ' — Solicitud de SIC'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Ticket" name="form.ticket_id" wire:model="form.ticket_id">
                    <option value="">Selecciona...</option>
                    @foreach ($ticketOptions as $ticket)
                        <option value="{{ $ticket->id }}">{{ $ticket->sdp_display_id ?? $ticket->sdp_id ?? ('Ticket #'.$ticket->id) }} — {{ $ticket->fecha?->format('d/m/Y') }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Solicitante" name="form.empleado_id" wire:model="form.empleado_id">
                    <option value="">Selecciona...</option>
                    @foreach ($empleadoOptions as $empleado)
                        <option value="{{ $empleado->id }}">{{ $empleado->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Tipo de equipo" name="form.tipo_equipo_id" wire:model="form.tipo_equipo_id">
                    <option value="">Selecciona...</option>
                    @foreach ($tipoEquipoOptions as $tipoEquipo)
                        <option value="{{ $tipoEquipo->id }}">{{ $tipoEquipo->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Urgencia" name="form.urgencia" wire:model="form.urgencia">
                    <option value="">Selecciona...</option>
                    <option value="baja">Baja</option>
                    <option value="media">Media</option>
                    <option value="alta">Alta</option>
                </x-ui.select>
            </div>

            <x-ui.input label="Motivo" name="form.motivo" type="textarea" wire:model="form.motivo" />
            <x-ui.input label="Especificaciones requeridas" name="form.especificaciones_requeridas" type="textarea" wire:model="form.especificaciones_requeridas" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Centro de costo" name="form.centro_costo_id" wire:model="form.centro_costo_id">
                    <option value="">Selecciona...</option>
                    @foreach ($centroCostoOptions as $centroCosto)
                        <option value="{{ $centroCosto->id }}">{{ $centroCosto->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Unidad de negocio" name="form.unidad_negocio_id" wire:model="form.unidad_negocio_id">
                    <option value="">Sin asignar</option>
                    @foreach ($unidadNegocioOptions as $unidadNegocio)
                        <option value="{{ $unidadNegocio->id }}">{{ $unidadNegocio->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.input label="Fecha de solicitud" name="form.fecha_solicitud" type="date" wire:model="form.fecha_solicitud" />

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Adjunto (SIC)</label>
                <input wire:model="adjunto" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300">
                <div wire:loading wire:target="adjunto" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Subiendo...</div>
                @error('adjunto')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                @if ($currentAdjunto)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Adjunto actual: <a href="{{ $currentAdjunto->url() }}" target="_blank" class="text-primary hover:underline">{{ $currentAdjunto->nombre_archivo }}</a>
                    </p>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showAdvanceModal" title="Marcar SIC creada">
        <form wire:submit="confirmAdvanceToSicCreada" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Buscar SIC ya importada de EBS (opcional)</label>
                <input
                    wire:model.live.debounce.300ms="ebsRequisicionSearch"
                    type="search"
                    placeholder="Buscar por código o descripción..."
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >
            </div>

            <x-ui.select label="Requisición de EBS" name="advanceEbsRequisitionId" wire:model.live="advanceEbsRequisitionId" hint="Elegir una autocompleta el folio y la vincula. Si no eliges ninguna, el folio se captura a mano igual que siempre.">
                <option value="">-- Escribir folio manualmente --</option>
                @foreach ($ebsRequisicionOptions as $opcion)
                    <option value="{{ $opcion->id }}">{{ $opcion->code }} — {{ $opcion->description }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Folio SIC (EBS)" name="advanceFolioSic" wire:model="advanceFolioSic" hint="Captura manual — respaldo si el API de EBS no está disponible o el folio no está en la lista de arriba." />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelAdvance">Cancelar</x-ui.button>
                <x-ui.button type="submit" wire:confirm="¿Confirmar que la SIC ya fue creada con este folio?">Confirmar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
