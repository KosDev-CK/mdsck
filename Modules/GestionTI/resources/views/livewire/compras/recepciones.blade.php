<div>
    @push('page-title')
        Recepción de Proveedor
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
        $solicitudSeleccionada = $selectedSolicitudId
            ? $solicitudOptions->firstWhere('id', $selectedSolicitudId)
            : null;
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar por folio de remisión o de solicitud..."
                class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Folio remisión', 'Solicitud a proveedor', 'Fecha', 'Recibido por', 'Estatus (solicitud)', 'Acciones']" :empty="$records->isEmpty()" empty-description="Registra la primera recepción con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="recepcion-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->folio_remision }}</td>
                    <td class="py-2">{{ $record->solicitudProveedor?->folio }} — {{ $record->solicitudProveedor?->vendor?->nombre_comercial }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_recepcion?->format('d/m/Y') }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->recibidoPor?->nombre }}</td>
                    <td class="py-2">
                        @php($estatusSolicitud = $record->solicitudProveedor?->estatus)
                        <x-ui.badge :color="$estatusColors[$estatusSolicitud] ?? 'gray'">{{ $estatusLabels[$estatusSolicitud] ?? $estatusSolicitud }}</x-ui.badge>
                    </td>
                    <td class="py-2">
                        <button type="button" wire:click="exportActaPdf({{ $record->id }})" class="text-sm text-primary hover:brightness-90">Generar PDF</button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" title="Nueva recepción de proveedor" max-width="max-w-4xl">
        <form wire:submit="save" class="space-y-4">
            <x-ui.select label="Solicitud a proveedor" name="selectedSolicitudId" wire:model.live="selectedSolicitudId">
                <option value="">Selecciona...</option>
                @foreach ($solicitudOptions as $solicitud)
                    <option value="{{ $solicitud->id }}">{{ $solicitud->folio }} — {{ $solicitud->vendor?->nombre_comercial }}</option>
                @endforeach
            </x-ui.select>

            @if ($solicitudSeleccionada)
                @if ($solicitudSeleccionada->sic_id)
                    <x-ui.alert variant="info">
                        Esta solicitud tiene una SIC asociada — los activos inventariables que reciba aquí quedarán <strong>reservados</strong> contra esa SIC en vez de libres en stock.
                    </x-ui.alert>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.input label="Folio de remisión" name="form.folio_remision" wire:model="form.folio_remision" />
                    <x-ui.input label="Fecha de recepción" name="form.fecha_recepcion" type="date" wire:model="form.fecha_recepcion" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.select label="Recibido por" name="form.recibido_por_id" wire:model="form.recibido_por_id">
                        <option value="">Selecciona...</option>
                        @foreach ($validadorOptions as $validador)
                            <option value="{{ $validador->id }}">{{ $validador->nombre }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select label="Ubicación destino" name="form.ubicacion_id" wire:model="form.ubicacion_id">
                        <option value="">Selecciona...</option>
                        @foreach ($ubicacionOptions as $ubicacion)
                            <option value="{{ $ubicacion->id }}">{{ $ubicacion->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Remisión digitalizada (opcional)</label>

                    @if ($documentoRemisionVinculado)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 dark:bg-gray-800/50 p-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">Vinculado de SharePoint: {{ $documentoRemisionVinculado['nombre'] }}</span>
                            <button type="button" wire:click="$set('documentoRemisionVinculado', null)" class="text-xs text-red-600 hover:text-red-500 dark:text-red-400">Quitar</button>
                        </div>
                    @else
                        <input wire:model="documentoRemision" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300">
                        <div wire:loading wire:target="documentoRemision" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Subiendo...</div>
                        <button type="button" wire:click="openSharePointBuscar" class="mt-1 text-xs text-primary hover:underline">Buscar en SharePoint</button>
                    @endif

                    @error('documentoRemision')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.input label="Observaciones" name="form.observaciones" type="textarea" wire:model="form.observaciones" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Líneas de la solicitud</label>

                    @error('lineas')
                        <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="space-y-3">
                        @foreach ($lineas as $i => $linea)
                            <div wire:key="recepcion-linea-{{ $i }}" class="rounded-md border border-gray-100 dark:border-gray-800 p-3 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $linea['descripcion'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Solicitado: {{ $linea['cantidad_solicitada'] }} ·
                                        Ya recibido: {{ $linea['cantidad_ya_recibida'] }} ·
                                        Pendiente: {{ $linea['cantidad_pendiente'] }}
                                    </p>
                                </div>

                                <x-ui.input
                                    label="Cantidad a recibir ahora"
                                    name="lineas.{{ $i }}.cantidad_a_recibir"
                                    type="number"
                                    min="0"
                                    max="{{ $linea['cantidad_pendiente'] }}"
                                    wire:model.live="lineas.{{ $i }}.cantidad_a_recibir"
                                    class="sm:w-56"
                                />

                                @if ($linea['es_activo_inventariable'] && (int) $linea['cantidad_a_recibir'] > 0)
                                    <div class="rounded-md bg-gray-50 dark:bg-gray-800/50 p-3 space-y-2">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <x-ui.select label="Marca" name="lineas.{{ $i }}.marca_id" wire:model="lineas.{{ $i }}.marca_id">
                                                <option value="">Selecciona...</option>
                                                @foreach ($marcaOptions as $marca)
                                                    <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                                                @endforeach
                                            </x-ui.select>

                                            <x-ui.select label="Modelo (opcional)" name="lineas.{{ $i }}.modelo_id" wire:model="lineas.{{ $i }}.modelo_id">
                                                <option value="">Sin asignar</option>
                                                @foreach ($modeloOptions as $modelo)
                                                    <option value="{{ $modelo->id }}">{{ $modelo->nombre }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        </div>

                                        @if (! $linea['articulo_tipo_equipo_id'])
                                            <x-ui.select label="Tipo de equipo" name="lineas.{{ $i }}.tipo_equipo_id" wire:model="lineas.{{ $i }}.tipo_equipo_id" hint="El artículo no tiene un tipo de equipo asignado — captúralo aquí.">
                                                <option value="">Selecciona...</option>
                                                @foreach ($tipoEquipoOptions as $tipoEquipo)
                                                    <option value="{{ $tipoEquipo->id }}">{{ $tipoEquipo->nombre }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        @endif

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <x-ui.input label="Inicio de garantía (opcional)" name="lineas.{{ $i }}.fecha_inicio_garantia" type="date" wire:model="lineas.{{ $i }}.fecha_inicio_garantia" />
                                            <x-ui.input label="Fin de garantía (opcional)" name="lineas.{{ $i }}.fecha_fin_garantia" type="date" wire:model="lineas.{{ $i }}.fecha_fin_garantia" />
                                        </div>

                                        <div class="space-y-2">
                                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Unidades a recibir</p>
                                            @foreach ($linea['unidades'] as $u => $unidad)
                                                <div wire:key="recepcion-linea-{{ $i }}-unidad-{{ $u }}" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    <x-ui.input label="Número de serie" name="lineas.{{ $i }}.unidades.{{ $u }}.numero_serie" wire:model="lineas.{{ $i }}.unidades.{{ $u }}.numero_serie" />
                                                    <x-ui.input label="Service tag (opcional)" name="lineas.{{ $i }}.unidades.{{ $u }}.service_tag" wire:model="lineas.{{ $i }}.unidades.{{ $u }}.service_tag" />
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showSharePointModal" title="Buscar en SharePoint">
        <div class="space-y-4">
            <input
                wire:model.live.debounce.300ms="sharePointSearch"
                type="search"
                placeholder="Buscar por nombre de archivo..."
                class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            @error('sharePointArchivos')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($sharePointArchivosFiltrados as $archivo)
                    <button
                        type="button"
                        wire:click="elegirArchivoSharePoint('{{ $archivo['driveItemId'] }}')"
                        class="flex w-full items-center justify-between py-2 text-left text-sm text-gray-700 hover:text-primary dark:text-gray-300"
                    >
                        {{ $archivo['nombre'] }}
                    </button>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-2">Sin archivos en esta carpeta.</p>
                @endforelse
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelSharePointBuscar">Cancelar</x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
