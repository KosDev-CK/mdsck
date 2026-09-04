<div>
    @push('page-title')
        Registro Manual de Activo
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estatusColors = [
            'en_stock' => 'emerald',
            'reservado' => 'amber',
            'asignado' => 'indigo',
            'en_reparacion' => 'red',
            'baja' => 'gray',
        ];
        $estadoLabels = [
            'nuevo' => 'Nuevo',
            'usado' => 'Usado',
            'reacondicionado' => 'Reacondicionado',
        ];
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar por código, número de serie o motivo..."
                class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Código', 'Tipo', 'Marca/Modelo', 'Ubicación', 'Estatus', 'Motivo', 'Dado de alta por', 'Fecha', '']" :empty="$records->isEmpty()" empty-description="Registra la primera alta manual con el botón Nuevo.">
            @foreach ($records as $asset)
                <tr wire:key="registro-manual-{{ $asset->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $asset->codigo }}</td>
                    <td class="py-2">{{ $asset->tipoEquipo?->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ trim(($asset->marca?->nombre ?? '').' '.($asset->modelo?->nombre ?? '')) ?: '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $asset->ubicacionActual?->nombre_conocido ?? $asset->ubicacionActual?->nombre ?? '—' }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$asset->estatus?->codigo] ?? 'gray'">{{ $asset->estatus?->nombre }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400 max-w-xs truncate" title="{{ $asset->motivo_alta_manual }}">{{ $asset->motivo_alta_manual }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $asset->dadoDeAltaPor?->nombre ?? '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ optional($asset->fecha_alta_stock)->format('d/m/Y') ?? $asset->fecha_alta_stock }}</td>
                    <td class="py-2"></td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" title="Nuevo registro manual de activo" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Tipo de equipo" name="form.tipo_equipo_id" wire:model="form.tipo_equipo_id">
                    <option value="">Selecciona...</option>
                    @foreach ($tipoEquipoOptions as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->nombre_conocido ?? $tipo->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Ubicación actual" name="form.ubicacion_actual_id" wire:model="form.ubicacion_actual_id">
                    <option value="">Selecciona...</option>
                    @foreach ($ubicacionOptions as $ubicacion)
                        <option value="{{ $ubicacion->id }}">{{ $ubicacion->nombre_conocido ?? $ubicacion->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Marca (opcional)" name="form.marca_id" wire:model="form.marca_id">
                    <option value="">Sin asignar</option>
                    @foreach ($marcaOptions as $marca)
                        <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Modelo (opcional)" name="form.modelo_id" wire:model="form.modelo_id">
                    <option value="">Sin asignar</option>
                    @foreach ($modeloOptions as $modelo)
                        <option value="{{ $modelo->id }}">{{ $modelo->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Número de serie (opcional)" name="form.numero_serie" wire:model="form.numero_serie" />
                <x-ui.input label="Service tag (opcional)" name="form.service_tag" wire:model="form.service_tag" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Costo de adquisición (opcional)" name="form.costo_adquisicion" type="number" step="0.01" min="0" wire:model="form.costo_adquisicion" />

                <x-ui.select label="Proveedor (opcional)" name="form.vendor_id" wire:model="form.vendor_id">
                    <option value="">Sin asignar</option>
                    @foreach ($vendorOptions as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.input label="Fecha de alta a stock" name="form.fecha_alta_stock" type="date" wire:model="form.fecha_alta_stock" />
                <x-ui.input label="Inicio de garantía (opcional)" name="form.fecha_inicio_garantia" type="date" wire:model="form.fecha_inicio_garantia" />
                <x-ui.input label="Fin de garantía (opcional)" name="form.fecha_fin_garantia" type="date" wire:model="form.fecha_fin_garantia" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Propiedad (opcional)" name="form.propiedad_id" wire:model="form.propiedad_id">
                    <option value="">Sin asignar</option>
                    @foreach ($propiedadOptions as $propiedad)
                        <option value="{{ $propiedad->id }}">{{ $propiedad->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Dado de alta por" name="form.dado_de_alta_por_id" wire:model="form.dado_de_alta_por_id">
                    <option value="">Selecciona...</option>
                    @foreach ($validadorOptions as $validador)
                        <option value="{{ $validador->id }}">{{ $validador->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.input label="Motivo / justificación del alta manual" name="form.motivo_alta_manual" type="textarea" wire:model="form.motivo_alta_manual" />

            <x-ui.input label="Nota de adquisición original (opcional)" name="form.nota_adquisicion_original" type="textarea" wire:model="form.nota_adquisicion_original" />

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destino</label>
                <div class="mt-1 flex gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" wire:model.live="form.destino" value="stock">
                        Enviar a stock
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" wire:model.live="form.destino" value="empleado">
                        Entregar directo a un empleado
                    </label>
                </div>
                @error('form.destino')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @if (($form['destino'] ?? null) === 'empleado')
                <div class="rounded-md bg-gray-50 dark:bg-gray-800/50 p-3 space-y-4">
                    <x-ui.select label="Empleado destinatario" name="form.empleado_id" wire:model="form.empleado_id">
                        <option value="">Selecciona...</option>
                        @foreach ($empleadoOptions as $empleado)
                            <option value="{{ $empleado->id }}">{{ $empleado->nombre }}</option>
                        @endforeach
                    </x-ui.select>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui.select label="Estado del equipo entregado" name="form.estado_equipo_entrega" wire:model="form.estado_equipo_entrega">
                            <option value="">Selecciona...</option>
                            @foreach ($estadoLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select label="Responsable de entrega" name="form.responsable_entrega_id" wire:model="form.responsable_entrega_id">
                            <option value="">Selecciona...</option>
                            @foreach ($validadorOptions as $validador)
                                <option value="{{ $validador->id }}">{{ $validador->nombre }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.input label="Accesorios entregados (opcional)" name="form.accesorios_entregados" type="textarea" wire:model="form.accesorios_entregados" />
                    <x-ui.input label="Observaciones (opcional)" name="form.observaciones" type="textarea" wire:model="form.observaciones" />
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.help-modal titulo="Registro Manual de Activo" :pdf-url="route('gestionti.ayuda.pdf', 'registro-manual')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('registro-manual')])
    </x-ui.help-modal>
</div>
