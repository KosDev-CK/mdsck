<div>
    @push('page-title')
        Solicitud a Proveedores
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.toast-group>
        @if (session('status'))
            <x-ui.toast variant="success">{{ session('status') }}</x-ui.toast>
        @endif

        @if (session('error'))
            <x-ui.toast variant="error">{{ session('error') }}</x-ui.toast>
        @endif
    </x-ui.toast-group>

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
                <tr wire:key="solicitud-proveedor-{{ $record->id }}" class="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->folio }}</td>
                    <td class="py-2">{{ $record->vendor?->nombre_comercial }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_solicitud?->format('d/m/Y') }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $tipoLabels[$record->tipo_solicitud] ?? $record->tipo_solicitud }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->estatus] ?? 'gray'">{{ $estatusLabels[$record->estatus] ?? $record->estatus }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->lineas_count }}</td>
                    <td class="py-2 text-right whitespace-nowrap">
                        <x-ui.row-actions>
                            @if ($this->puedeEditar($record))
                                <x-ui.icon-button wire:click="edit({{ $record->id }})" icon="heroicon-o-pencil-square" title="Editar" />
                            @endif

                            <x-ui.icon-button
                                wire:click="enviarAProveedor({{ $record->id }})"
                                wire:confirm="¿{{ $record->enviada_at ? 'Reenviar' : 'Enviar' }} esta solicitud al proveedor por correo?"
                                icon="heroicon-o-paper-airplane"
                                :title="$record->enviada_at ? 'Reenviar al proveedor' : 'Enviar a proveedor'"
                            />

                            <x-ui.icon-button tag="a" :href="route('gestionti.solicitudes-proveedor.pdf', $record)" icon="heroicon-o-arrow-down-tray" title="Generar PDF" />

                            @if ($record->estatus === 'solicitada' && $this->puedeEditar($record))
                                <x-ui.icon-button
                                    wire:click="cancelarSolicitud({{ $record->id }})"
                                    wire:confirm="¿Cancelar esta solicitud a proveedor?"
                                    icon="heroicon-o-x-circle"
                                    title="Cancelar"
                                    variant="danger"
                                />
                            @endif
                        </x-ui.row-actions>
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

            <x-ui.select label="Ticket (opcional)" name="form.ticket_id" wire:model="form.ticket_id">
                <option value="">Sin asignar</option>
                @foreach ($ticketOptions as $ticket)
                    <option value="{{ $ticket->id }}">{{ $ticket->sdp_display_id ?? $ticket->sdp_id ?? ('Ticket #'.$ticket->id) }} — {{ $ticket->fecha?->format('d/m/Y') }}</option>
                @endforeach
            </x-ui.select>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Origen</label>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" wire:model.live="origen" value="sic" class="border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700">
                        Una o más SICs
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" wire:model.live="origen" value="proyecto" class="border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700">
                        Artículo de Proyecto de Presupuesto
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">El origen es una o más SICs o un artículo de proyecto, no ambos. También es válido dejar la solicitud sin ningún origen vinculado.</p>

                @error('origen')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @if ($origen !== 'sic')
                <x-ui.select label="Artículo de Proyecto de Presupuesto" name="form.proyecto_presupuesto_articulo_id" wire:model="form.proyecto_presupuesto_articulo_id" hint="Solo artículos Laptops/Desktops de proyectos ya autorizados.">
                    <option value="">Sin asignar</option>
                    @foreach ($proyectoArticuloOptions as $proyectoArticulo)
                        <option value="{{ $proyectoArticulo->id }}">{{ $proyectoArticulo->proyecto?->nombre_proyecto }} — {{ $proyectoArticulo->descripcion }} (x{{ $proyectoArticulo->cantidad }})</option>
                    @endforeach
                </x-ui.select>

                @error('form.proyecto_presupuesto_articulo_id')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            @endif

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Líneas del pedido</label>
                    <button type="button" wire:click="addLinea" class="text-sm text-primary hover:underline">
                        + Agregar línea{{ $origen === 'sic' ? ' manual (sin SIC real)' : '' }}
                    </button>
                </div>

                @if ($origen === 'sic')
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        Solo aparecen SICs autorizadas, de una categoría marcada como "va a Compra" (pantalla "Categorías que van a Compra") y que ninguna otra solicitud haya recogido todavía — más requisiciones de EBS que nunca tuvieron SIC local, aprobadas y con su artículo mapeado de una categoría "va a Compra". Marca una o más — cada una se agrega al pedido y sus campos se vuelven editables justo ahí.
                    </p>
                @endif

                @error('lineas')
                    <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="overflow-x-auto rounded-md border border-gray-100 dark:border-gray-800">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                                <th class="py-2 px-2 whitespace-nowrap">Selec.</th>
                                <th class="py-2 px-2 whitespace-nowrap">SIC</th>
                                <th class="py-2 px-2 whitespace-nowrap">Art. EBS</th>
                                <th class="py-2 px-2 min-w-[14rem]">Artículo</th>
                                <th class="py-2 px-2 whitespace-nowrap">Cantidad</th>
                                <th class="py-2 px-2 whitespace-nowrap">P. Unit.</th>
                                <th class="py-2 px-2 min-w-[9rem]">Lugar de entrega</th>
                                <th class="py-2 px-2 min-w-[10rem]">Observaciones</th>
                                <th class="py-2 px-2 whitespace-nowrap">Inventariable</th>
                                <th class="py-2 px-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lineas as $i => $linea)
                                @php
                                    $esFilaDePool = ! empty($linea['sic_id']) || ! empty($linea['ebs_requisition_id']);
                                    $mostrarEditable = ! $esFilaDePool || ! empty($linea['seleccionada']);
                                @endphp
                                <tr wire:key="linea-{{ $i }}" class="border-b border-gray-50 dark:border-gray-800 align-top">
                                    <td class="py-2 px-2 w-10 text-center">
                                        @if ($esFilaDePool)
                                            <input type="checkbox" wire:model.live="lineas.{{ $i }}.seleccionada" class="rounded border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700">
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 w-28">
                                        @if ($esFilaDePool)
                                            <button
                                                type="button"
                                                wire:click="openSicDetalle({{ $linea['sic_id'] ?? 0 }}, {{ $linea['ebs_requisition_id'] ?? 0 }})"
                                                class="block text-xs text-primary hover:underline text-left"
                                                title="{{ ! empty($linea['sic_id']) ? 'Clic para ver el detalle de la SIC.' : 'Clic para ver el detalle de la requisición de EBS.' }}"
                                            >
                                                {{ $linea['folio_sic_display'] ?? '—' }}
                                            </button>
                                        @elseif ($origen === 'sic')
                                            <x-ui.input name="lineas.{{ $i }}.folio_sic_manual" wire:model="lineas.{{ $i }}.folio_sic_manual" placeholder="Folio SIC (manual)" class="text-xs" />
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 w-36">
                                        @if (! empty($linea['ebs_item_description']))
                                            <span class="block text-xs text-gray-500 dark:text-gray-400 max-w-[9rem] truncate" title="{{ $linea['ebs_item_description'] }}">
                                                {{ $linea['ebs_item_description'] }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 min-w-[14rem]">
                                        @if ($mostrarEditable)
                                            <div class="space-y-1">
                                                <x-ui.select name="lineas.{{ $i }}.articulo_id" wire:model="lineas.{{ $i }}.articulo_id">
                                                    <option value="">Sin catálogo (descripción libre)</option>
                                                    @foreach ($articuloOptions as $articulo)
                                                        <option value="{{ $articulo->id }}">{{ $articulo->codigo }} — {{ $articulo->descripcion }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                                <x-ui.input name="lineas.{{ $i }}.descripcion_libre" wire:model="lineas.{{ $i }}.descripcion_libre" placeholder="o descripción libre" class="text-xs" />
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $linea['articulo_descripcion_preview'] ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 w-20">
                                        @if ($mostrarEditable)
                                            <x-ui.input name="lineas.{{ $i }}.cantidad_solicitada" type="number" wire:model="lineas.{{ $i }}.cantidad_solicitada" class="w-20" />
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 w-24">
                                        @if ($mostrarEditable)
                                            <x-ui.input name="lineas.{{ $i }}.precio_unitario_cotizado" type="number" wire:model="lineas.{{ $i }}.precio_unitario_cotizado" class="w-24" />
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 min-w-[9rem]">
                                        @if ($mostrarEditable)
                                            <x-ui.select name="lineas.{{ $i }}.lugar_entrega_id" wire:model="lineas.{{ $i }}.lugar_entrega_id">
                                                <option value="">Sin asignar</option>
                                                @foreach ($lugarEntregaOptions as $lugar)
                                                    <option value="{{ $lugar->id }}">{{ $lugar->nombre }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 min-w-[10rem]">
                                        @if ($mostrarEditable)
                                            <x-ui.input name="lineas.{{ $i }}.observaciones_especificaciones" wire:model="lineas.{{ $i }}.observaciones_especificaciones" />
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 text-center">
                                        @if ($mostrarEditable)
                                            <x-ui.toggle wire:model="lineas.{{ $i }}.es_activo_inventariable" />
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="py-2 px-2 text-right">
                                        @unless ($esFilaDePool)
                                            <x-ui.icon-button wire:click="removeLinea({{ $i }})" icon="heroicon-o-trash" title="Quitar línea" variant="danger" />
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal
        model="showDetalleModal"
        :title="$detalleEbsRequisicion ? 'Detalle de la requisición '.$detalleEbsRequisicion->code : 'Detalle de la SIC '.($detalleSicLocal?->folio_sic ?: '#'.$detalleSicLocal?->id)"
        max-width="max-w-3xl"
    >
        @if ($detalleEbsRequisicion)
            @include('gestionti::partials.ebs-requisicion-detalle', ['detalle' => $detalleEbsRequisicion, 'estatusColors' => $ebsEstatusColors, 'mostrarLinkSolicitudProveedor' => false])
        @else
            @include('gestionti::partials.sic-local-detalle', ['detalle' => $detalleSicLocal])
        @endif
    </x-ui.modal>

    <x-ui.help-modal titulo="Solicitud a Proveedores" :pdf-url="route('gestionti.ayuda.pdf', 'solicitudes-proveedor')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('solicitudes-proveedor')])
    </x-ui.help-modal>
</div>
