<div>
    @push('page-title')
        Facturación
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estatusLabels = [
            'recibida' => 'Recibida',
            'registrada' => 'Registrada',
            'autorizada' => 'Autorizada',
            'pagada' => 'Pagada',
        ];
        $estatusColors = [
            'recibida' => 'indigo',
            'registrada' => 'amber',
            'autorizada' => 'info',
            'pagada' => 'emerald',
        ];
        $diferenciaPreview = ($form['monto_total'] ?? '') !== '' && $form['monto_total'] !== null
            ? round((float) $form['monto_total'], 2) - round($totalCotizadoSeleccion, 2)
            : null;
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Buscar por folio o proveedor..."
                    class="w-full sm:w-72 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >
                <x-ui.select name="estatusFilter" wire:model.live="estatusFilter" class="sm:w-56">
                    <option value="">Todos los estatus</option>
                    @foreach ($estatusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.toggle label="Solo con diferencia a revisar" wire:model.live="soloDiferencia" />
            </div>
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Folio', 'Proveedor', 'Fecha', 'Monto', 'Estatus', 'Diferencia', '']" :empty="$records->isEmpty()" empty-description="Agrega la primera con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="invoice-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->folio_factura }}</td>
                    <td class="py-2">{{ $record->vendor?->nombre_comercial }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_recepcion?->format('d/m/Y') }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">${{ number_format((float) $record->monto_total, 2) }} {{ $record->moneda }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->estatus] ?? 'gray'">{{ $estatusLabels[$record->estatus] ?? $record->estatus }}</x-ui.badge>
                    </td>
                    <td class="py-2">
                        @if ($record->diferencia_a_revisar)
                            <x-ui.badge color="red">Diferencia a revisar</x-ui.badge>
                        @endif
                    </td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="edit({{ $record->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>

                        @if ($record->estatus === 'recibida')
                            <button
                                wire:click="marcarRegistrada({{ $record->id }})"
                                wire:confirm="¿Marcar esta factura como registrada?"
                                class="text-sm text-primary hover:underline"
                            >
                                Registrar
                            </button>
                        @endif

                        @if ($record->estatus === 'registrada')
                            <button
                                wire:click="marcarAutorizada({{ $record->id }})"
                                wire:confirm="¿Marcar esta factura como autorizada?"
                                class="text-sm text-primary hover:underline"
                            >
                                Autorizar
                            </button>
                        @endif

                        @if ($record->estatus === 'autorizada')
                            <button
                                wire:click="marcarPagada({{ $record->id }})"
                                wire:confirm="¿Marcar esta factura como pagada?"
                                class="text-sm text-primary hover:underline"
                            >
                                Marcar pagada
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nueva') . ' — Factura'" max-width="max-w-3xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Folio de factura" name="form.folio_factura" wire:model="form.folio_factura" />

                <x-ui.select label="Proveedor" name="form.vendor_id" wire:model.live="form.vendor_id">
                    <option value="">Selecciona...</option>
                    @foreach ($vendorOptions as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.input label="Fecha de recepción" name="form.fecha_recepcion" type="date" wire:model="form.fecha_recepcion" />
                <x-ui.input label="Monto total" name="form.monto_total" type="number" step="0.01" wire:model.live="form.monto_total" />

                <x-ui.select label="Moneda" name="form.moneda" wire:model="form.moneda">
                    <option value="MXN">MXN</option>
                    <option value="USD">USD</option>
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Partida presupuestal (opcional)" name="form.partida_presupuestal" wire:model="form.partida_presupuestal" />
                <x-ui.input label="Ejercicio fiscal (opcional)" name="form.ejercicio_fiscal" wire:model="form.ejercicio_fiscal" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Adjunto (factura)</label>
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

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Remisiones a vincular</label>

                @error('recepcionIds')
                    <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                @if (! ($form['vendor_id'] ?? null))
                    <p class="text-sm text-gray-500 dark:text-gray-400">Selecciona un proveedor para ver sus remisiones disponibles.</p>
                @elseif ($recepcionOptions->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Este proveedor no tiene remisiones registradas todavía.</p>
                @else
                    <div class="space-y-1 max-h-56 overflow-y-auto rounded-md border border-gray-100 dark:border-gray-800 p-3">
                        @foreach ($recepcionOptions as $recepcion)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" value="{{ $recepcion->id }}" wire:model.live="recepcionIds" class="rounded border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700">
                                {{ $recepcion->folio_remision }} — Solicitud {{ $recepcion->solicitudProveedor?->folio }} — recibido {{ $recepcion->fecha_recepcion?->format('d/m/Y') }}
                            </label>
                        @endforeach
                    </div>
                @endif

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Total cotizado de las remisiones seleccionadas: <strong>${{ number_format($totalCotizadoSeleccion, 2) }}</strong>
                </p>

                @if ($diferenciaPreview !== null)
                    <p class="text-sm {{ $diferenciaPreview == 0 ? 'text-gray-600 dark:text-gray-400' : 'text-warning font-medium' }}">
                        Diferencia: ${{ number_format($diferenciaPreview, 2) }}
                    </p>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
