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

    @if (! $showForm)
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
    @else
        <form wire:submit="save" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-ui.button type="button" variant="ghost" wire:click="cancel">&larr; Volver al listado</x-ui.button>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $editingId ? 'Editar solicitud a proveedor' : 'Nueva solicitud a proveedor' }}
                </h2>
            </div>

            {{-- Bloque 1: datos de la solicitud --}}
            <x-ui.card padding="p-5">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">Datos de la solicitud</h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.input label="Folio" name="form.folio" wire:model="form.folio" hint="Sugerido automáticamente — puedes cambiarlo." />

                    <x-ui.select label="Proveedor" name="form.vendor_id" wire:model="form.vendor_id">
                        <option value="">Selecciona...</option>
                        @foreach ($vendorOptions as $vendor)
                            <option value="{{ $vendor->id }}">{{ $vendor->nombre_comercial }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input label="Fecha de solicitud" name="form.fecha_solicitud" type="date" wire:model="form.fecha_solicitud" />

                    <x-ui.select label="Tipo de solicitud" name="form.tipo_solicitud" wire:model="form.tipo_solicitud">
                        <option value="regular">Regular</option>
                        <option value="compra_especial">Compra especial</option>
                    </x-ui.select>

                    <x-ui.select label="Ticket (opcional)" name="form.ticket_id" wire:model="form.ticket_id">
                        <option value="">Sin asignar</option>
                        @foreach ($ticketOptions as $ticket)
                            <option value="{{ $ticket->id }}">{{ $ticket->sdp_display_id ?? $ticket->sdp_id ?? ('Ticket #'.$ticket->id) }} — {{ $ticket->fecha?->format('d/m/Y') }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Origen</label>
                    <div class="flex flex-wrap items-center gap-4">
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
                    <div class="mt-4 max-w-2xl">
                        <x-ui.select label="Artículo de Proyecto de Presupuesto" name="form.proyecto_presupuesto_articulo_id" wire:model="form.proyecto_presupuesto_articulo_id" hint="Solo artículos Laptops/Desktops de proyectos ya autorizados.">
                            <option value="">Sin asignar</option>
                            @foreach ($proyectoArticuloOptions as $proyectoArticulo)
                                <option value="{{ $proyectoArticulo->id }}">{{ $proyectoArticulo->proyecto?->nombre_proyecto }} — {{ $proyectoArticulo->descripcion }} (x{{ $proyectoArticulo->cantidad }})</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                @endif
            </x-ui.card>

            {{-- Bloque 2: tabla paginada de SICs/EBS (solo origen sic) --}}
            @if ($origen === 'sic')
                <x-ui.card padding="p-5">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">SICs y requisiciones disponibles</h3>
                            <p class="mt-1 max-w-4xl text-xs text-gray-500 dark:text-gray-400">
                                Solo aparecen SICs autorizadas, de una categoría marcada como "Va a Compras" (pestaña "Categoría" de Catálogos de Compras) y que ninguna otra solicitud haya recogido todavía — más requisiciones de EBS que nunca tuvieron SIC local, aprobadas y con su artículo mapeado de una categoría "va a Compra". Marca una o más: sus campos se vuelven editables en la misma fila. La selección se conserva al cambiar de página o al buscar.
                            </p>
                        </div>
                        <x-ui.badge color="indigo">{{ count($seleccion) }} {{ count($seleccion) === 1 ? 'seleccionada' : 'seleccionadas' }}</x-ui.badge>
                    </div>

                    <div class="mb-3 flex flex-wrap items-center gap-4">
                        <input
                            wire:model.live.debounce.300ms="sicSearch"
                            type="search"
                            placeholder="Buscar por folio, descripción EBS o artículo..."
                            class="w-full rounded-md border-gray-300 shadow-sm sm:w-96 sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                        >
                        <x-ui.toggle wire:model.live="soloSeleccionadas" label="Solo seleccionadas" />
                    </div>

                    <div class="overflow-x-auto rounded-md border border-gray-100 dark:border-gray-800">
                        <table class="w-full min-w-[84rem] table-fixed text-sm">
                            <colgroup>
                                <col class="w-14">
                                <col class="w-32">
                                <col class="w-48">
                                <col>
                                <col class="w-24">
                                <col class="w-32">
                                <col class="w-40">
                                <col class="w-56">
                                <col class="w-28">
                            </colgroup>
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <th class="px-2 py-2 text-center font-medium">Selec.</th>
                                    <th class="px-2 py-2 font-medium">SIC</th>
                                    <th class="px-2 py-2 font-medium">Art. EBS</th>
                                    <th class="px-2 py-2 font-medium">Artículo</th>
                                    <th class="px-2 py-2 font-medium">Cantidad</th>
                                    <th class="px-2 py-2 font-medium">P. Unit.</th>
                                    <th class="px-2 py-2 font-medium">Lugar de entrega</th>
                                    <th class="px-2 py-2 font-medium">Observaciones</th>
                                    <th class="px-2 py-2 text-center font-medium">Inventariable</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sics as $fila)
                                    @php
                                        $clave = $fila['clave'];
                                        $sel = $seleccion[$clave] ?? null;
                                        $err = fn (string $campo) => $errors->has("seleccion.$clave.$campo") ? 'border-danger!' : '';
                                    @endphp
                                    <tr wire:key="sic-fila-{{ $clave }}" class="border-b border-gray-50 dark:border-gray-800 {{ $sel ? 'bg-primary/5 dark:bg-primary/10' : '' }}">
                                        <td class="px-2 py-1.5">
                                            <div class="flex h-9 items-center justify-center">
                                                <input
                                                    type="checkbox"
                                                    wire:click="toggleSeleccion('{{ $clave }}')"
                                                    @checked($sel !== null)
                                                    class="rounded border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700"
                                                    aria-label="Seleccionar {{ $fila['folio_sic_display'] }}"
                                                >
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <div class="flex h-9 items-center">
                                                <button
                                                    type="button"
                                                    wire:click="openSicDetalle({{ $fila['sic_id'] ?? 0 }}, {{ $fila['ebs_requisition_id'] ?? 0 }})"
                                                    class="block w-full truncate text-left text-xs text-primary hover:underline"
                                                    title="{{ $fila['tipo'] === 'sic' ? 'Clic para ver el detalle de la SIC.' : 'Clic para ver el detalle de la requisición de EBS.' }}"
                                                >
                                                    {{ $fila['folio_sic_display'] ?? '—' }}
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <div class="flex h-9 items-center">
                                                @if (! empty($fila['ebs_item_description']))
                                                    <span class="block w-full truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $fila['ebs_item_description'] }}">{{ $fila['ebs_item_description'] }}</span>
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @if ($sel)
                                                <x-ui.select wire:model.live="seleccion.{{ $clave }}.articulo_id" class="h-9 {{ $err('articulo_id') }}" title="{{ $errors->first('seleccion.'.$clave.'.articulo_id') }}">
                                                    <option value="">{{ ! empty($sel['descripcion_libre']) ? 'Descripción libre: '.\Illuminate\Support\Str::limit($sel['descripcion_libre'], 40) : 'Selecciona un artículo...' }}</option>
                                                    @foreach ($articuloOptions as $articulo)
                                                        <option value="{{ $articulo->id }}">{{ $articulo->codigo }} — {{ $articulo->descripcion }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            @else
                                                <div class="flex h-9 items-center">
                                                    <span class="block w-full truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $fila['articulo_descripcion_preview'] }}">{{ $fila['articulo_descripcion_preview'] ?? '—' }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @if ($sel)
                                                <x-ui.input type="number" min="1" wire:model="seleccion.{{ $clave }}.cantidad_solicitada" class="h-9 {{ $err('cantidad_solicitada') }}" title="{{ $errors->first('seleccion.'.$clave.'.cantidad_solicitada') }}" />
                                            @else
                                                <div class="flex h-9 items-center"><span class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['cantidad'] }}</span></div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @if ($sel)
                                                <x-ui.input type="number" step="0.01" min="0" wire:model="seleccion.{{ $clave }}.precio_unitario_cotizado" class="h-9 {{ $err('precio_unitario_cotizado') }}" title="{{ $errors->first('seleccion.'.$clave.'.precio_unitario_cotizado') }}" />
                                            @else
                                                <div class="flex h-9 items-center"><span class="text-xs text-gray-400 dark:text-gray-500">—</span></div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @if ($sel)
                                                <x-ui.select wire:model="seleccion.{{ $clave }}.lugar_entrega_id" class="h-9 {{ $err('lugar_entrega_id') }}">
                                                    <option value="">Sin asignar</option>
                                                    @foreach ($lugarEntregaOptions as $lugar)
                                                        <option value="{{ $lugar->id }}">{{ $lugar->nombre }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            @else
                                                <div class="flex h-9 items-center"><span class="text-xs text-gray-400 dark:text-gray-500">—</span></div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @if ($sel)
                                                <x-ui.input wire:model="seleccion.{{ $clave }}.observaciones_especificaciones" class="h-9 {{ $err('observaciones_especificaciones') }}" title="{{ $errors->first('seleccion.'.$clave.'.observaciones_especificaciones') }}" />
                                            @else
                                                <div class="flex h-9 items-center"><span class="text-xs text-gray-400 dark:text-gray-500">—</span></div>
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <div class="flex h-9 items-center justify-center">
                                                @if ($sel)
                                                    @include('gestionti::partials.inventariable-badge', ['articuloId' => $sel['articulo_id'] ?? null])
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-2 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            @if ($sicSearch !== '' || $soloSeleccionadas)
                                                Ninguna SIC o requisición coincide con la búsqueda{{ $soloSeleccionadas ? ' entre las seleccionadas' : '' }}.
                                            @else
                                                No hay SICs ni requisiciones de EBS elegibles por ahora. Puedes capturar una a mano en "Líneas manuales".
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $sics->links() }}</div>
                </x-ui.card>
            @endif

            {{-- Bloque 3: líneas manuales (sin SIC real) --}}
            <x-ui.card padding="p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ $origen === 'sic' ? 'Líneas manuales (sin SIC real)' : 'Líneas del pedido' }}
                        </h3>
                        @if ($origen === 'sic')
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Para una SIC que aún no existe como registro: captura el folio a mano y el artículo.</p>
                        @endif
                    </div>
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="addLinea">
                        + Agregar línea{{ $origen === 'sic' ? ' manual (sin SIC real)' : '' }}
                    </x-ui.button>
                </div>

                @if (empty($lineasManuales))
                    <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Sin líneas manuales.</p>
                @else
                    <div class="overflow-x-auto rounded-md border border-gray-100 dark:border-gray-800">
                        <table class="w-full min-w-[70rem] table-fixed text-sm">
                            <colgroup>
                                @if ($origen === 'sic')
                                    <col class="w-40">
                                @endif
                                <col>
                                <col class="w-24">
                                <col class="w-32">
                                <col class="w-40">
                                <col class="w-56">
                                <col class="w-28">
                                <col class="w-14">
                            </colgroup>
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    @if ($origen === 'sic')
                                        <th class="px-2 py-2 font-medium">Folio de SIC</th>
                                    @endif
                                    <th class="px-2 py-2 font-medium">Artículo</th>
                                    <th class="px-2 py-2 font-medium">Cantidad</th>
                                    <th class="px-2 py-2 font-medium">P. Unit.</th>
                                    <th class="px-2 py-2 font-medium">Lugar de entrega</th>
                                    <th class="px-2 py-2 font-medium">Observaciones</th>
                                    <th class="px-2 py-2 text-center font-medium">Inventariable</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lineasManuales as $i => $linea)
                                    <tr wire:key="linea-manual-{{ $i }}" class="border-b border-gray-50 align-top dark:border-gray-800">
                                        @if ($origen === 'sic')
                                            <td class="px-2 py-1.5">
                                                <x-ui.input name="lineasManuales.{{ $i }}.folio_sic_manual" wire:model="lineasManuales.{{ $i }}.folio_sic_manual" placeholder="Folio SIC (manual)" class="text-xs" />
                                            </td>
                                        @endif
                                        <td class="px-2 py-1.5">
                                            <div class="space-y-1">
                                                <x-ui.select name="lineasManuales.{{ $i }}.articulo_id" wire:model.live="lineasManuales.{{ $i }}.articulo_id">
                                                    <option value="">Sin catálogo (descripción libre)</option>
                                                    @foreach ($articuloOptions as $articulo)
                                                        <option value="{{ $articulo->id }}">{{ $articulo->codigo }} — {{ $articulo->descripcion }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                                <x-ui.input name="lineasManuales.{{ $i }}.descripcion_libre" wire:model="lineasManuales.{{ $i }}.descripcion_libre" placeholder="o descripción libre" class="text-xs" />
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <x-ui.input name="lineasManuales.{{ $i }}.cantidad_solicitada" type="number" min="1" wire:model="lineasManuales.{{ $i }}.cantidad_solicitada" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <x-ui.input name="lineasManuales.{{ $i }}.precio_unitario_cotizado" type="number" step="0.01" min="0" wire:model="lineasManuales.{{ $i }}.precio_unitario_cotizado" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <x-ui.select name="lineasManuales.{{ $i }}.lugar_entrega_id" wire:model="lineasManuales.{{ $i }}.lugar_entrega_id">
                                                <option value="">Sin asignar</option>
                                                @foreach ($lugarEntregaOptions as $lugar)
                                                    <option value="{{ $lugar->id }}">{{ $lugar->nombre }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <x-ui.input name="lineasManuales.{{ $i }}.observaciones_especificaciones" wire:model="lineasManuales.{{ $i }}.observaciones_especificaciones" />
                                        </td>
                                        <td class="px-2 py-1.5">
                                            <div class="flex h-9 items-center justify-center">
                                                @include('gestionti::partials.inventariable-badge', ['articuloId' => $linea['articulo_id'] ?? null])
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5 text-right">
                                            <x-ui.icon-button wire:click="removeLinea({{ $i }})" icon="heroicon-o-trash" title="Quitar línea" variant="danger" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>

            {{-- Errores globales de líneas + botones --}}
            @error('lineas')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if (! empty($erroresLineas))
                <div class="rounded-md border border-danger/30 bg-danger/10 p-3 text-sm text-red-700 dark:text-red-300" role="alert">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium">Hay {{ count($erroresLineas) }} {{ count($erroresLineas) === 1 ? 'línea con errores' : 'líneas con errores' }} que impiden guardar:</p>
                        @if ($origen === 'sic' && ! $soloSeleccionadas)
                            <button type="button" wire:click="$set('soloSeleccionadas', true)" class="text-xs underline">Ver solo las seleccionadas</button>
                        @endif
                    </div>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        @foreach ($erroresLineas as $errorLinea)
                            <li><span class="font-medium">{{ $errorLinea['etiqueta'] }}</span> — {{ implode(' ', $errorLinea['mensajes']) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    @endif

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
