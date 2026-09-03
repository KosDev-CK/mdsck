<div>
    @push('page-title')
        Solicitud a Proveedores
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estatusLabels = [
            'solicitada' => 'Solicitada',
            'parcialmente_recibida' => 'Parcialmente recibida',
            'recibida' => 'Recibida',
            'facturada' => 'Facturada',
            'cancelada' => 'Cancelada',
        ];
        $estatusColors = [
            'solicitada' => 'indigo',
            'parcialmente_recibida' => 'amber',
            'recibida' => 'emerald',
            'facturada' => 'emerald',
            'cancelada' => 'red',
        ];
        $tipoLabels = [
            'regular' => 'Regular',
            'compra_especial' => 'Compra especial',
        ];
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
            </div>
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Folio', 'Proveedor', 'Fecha', 'Tipo', 'Estatus', 'Líneas', '']" :empty="$records->isEmpty()" empty-description="Agrega la primera con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="solicitud-proveedor-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->folio }}</td>
                    <td class="py-2">{{ $record->vendor?->nombre_comercial }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_solicitud?->format('d/m/Y') }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $tipoLabels[$record->tipo_solicitud] ?? $record->tipo_solicitud }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->estatus] ?? 'gray'">{{ $estatusLabels[$record->estatus] ?? $record->estatus }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->lineas_count }}</td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="edit({{ $record->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>

                        @if ($record->estatus === 'solicitada')
                            <button
                                wire:click="cancelarSolicitud({{ $record->id }})"
                                wire:confirm="¿Cancelar esta solicitud a proveedor?"
                                class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300"
                            >
                                Cancelar
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nueva') . ' — Solicitud a Proveedores'" max-width="max-w-3xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Folio" name="form.folio" wire:model="form.folio" hint="Sugerido automáticamente — puedes cambiarlo." />

                <x-ui.select label="Proveedor" name="form.vendor_id" wire:model="form.vendor_id">
                    <option value="">Selecciona...</option>
                    @foreach ($vendorOptions as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Fecha de solicitud" name="form.fecha_solicitud" type="date" wire:model="form.fecha_solicitud" />

                <x-ui.select label="Tipo de solicitud" name="form.tipo_solicitud" wire:model="form.tipo_solicitud">
                    <option value="regular">Regular</option>
                    <option value="compra_especial">Compra especial</option>
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Ticket (opcional)" name="form.ticket_id" wire:model="form.ticket_id">
                    <option value="">Sin asignar</option>
                    @foreach ($ticketOptions as $ticket)
                        <option value="{{ $ticket->id }}">{{ $ticket->sdp_display_id ?? $ticket->sdp_id ?? ('Ticket #'.$ticket->id) }} — {{ $ticket->fecha?->format('d/m/Y') }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Solicitud de SIC (opcional)" name="form.sic_id" wire:model="form.sic_id" hint="El origen es una SIC o un artículo de proyecto, no ambos.">
                    <option value="">Sin asignar</option>
                    @foreach ($sicOptions as $sic)
                        <option value="{{ $sic->id }}">{{ $sic->folio_sic ? "SIC {$sic->folio_sic}" : "SIC #{$sic->id} (sin folio)" }} — {{ $sic->ticket?->sdp_display_id ?? $sic->ticket?->sdp_id ?? ('Ticket #'.$sic->ticket_id) }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.select label="Artículo de Proyecto de Presupuesto (opcional)" name="form.proyecto_presupuesto_articulo_id" wire:model="form.proyecto_presupuesto_articulo_id" hint="Solo artículos Laptops/Desktops de proyectos ya autorizados. El origen es una SIC o un artículo de proyecto, no ambos.">
                <option value="">Sin asignar</option>
                @foreach ($proyectoArticuloOptions as $proyectoArticulo)
                    <option value="{{ $proyectoArticulo->id }}">{{ $proyectoArticulo->proyecto?->nombre_proyecto }} — {{ $proyectoArticulo->descripcion }} (x{{ $proyectoArticulo->cantidad }})</option>
                @endforeach
            </x-ui.select>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Líneas del pedido</label>
                    <button type="button" wire:click="addLinea" class="text-sm text-primary hover:underline">+ Agregar línea</button>
                </div>

                @error('lineas')
                    <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="space-y-3">
                    @foreach ($lineas as $i => $linea)
                        <div wire:key="linea-{{ $i }}" class="rounded-md border border-gray-100 dark:border-gray-800 p-3 space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <x-ui.select label="Artículo del catálogo" name="lineas.{{ $i }}.articulo_id" wire:model="lineas.{{ $i }}.articulo_id">
                                    <option value="">Sin catálogo (descripción libre)</option>
                                    @foreach ($articuloOptions as $articulo)
                                        <option value="{{ $articulo->id }}">{{ $articulo->codigo }} — {{ $articulo->descripcion }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.input label="Descripción libre" name="lineas.{{ $i }}.descripcion_libre" wire:model="lineas.{{ $i }}.descripcion_libre" hint="Usa esto solo si el artículo no está en el catálogo." />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                                <x-ui.input label="Cantidad solicitada" name="lineas.{{ $i }}.cantidad_solicitada" type="number" wire:model="lineas.{{ $i }}.cantidad_solicitada" />
                                <x-ui.input label="Precio unitario cotizado" name="lineas.{{ $i }}.precio_unitario_cotizado" type="number" wire:model="lineas.{{ $i }}.precio_unitario_cotizado" />
                                <x-ui.toggle label="Es activo inventariable" wire:model="lineas.{{ $i }}.es_activo_inventariable" />
                            </div>

                            <div class="flex justify-end">
                                <button type="button" wire:click="removeLinea({{ $i }})" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">
                                    Quitar línea
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
