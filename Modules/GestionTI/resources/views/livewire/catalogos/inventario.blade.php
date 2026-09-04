<div>
    @push('page-title')
        Catálogos de Inventario
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach ($catalogos as $key => $item)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                @class([
                    'px-3 py-1.5 text-sm font-medium rounded-md transition',
                    'bg-primary text-white' => $tab === $key,
                    'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700' => $tab !== $key,
                ])
            >
                {{ $item['label'] }}
            </button>
        @endforeach
    </div>

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            @if (! empty($config['searchColumns']))
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Buscar..."
                    class="w-full sm:w-64 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >
            @else
                <span></span>
            @endif
            <div class="flex items-center gap-2">
                @if (array_key_exists('mergeReferences', $config))
                    <x-ui.button type="button" variant="secondary" wire:click="openMerge">Fusionar duplicados</x-ui.button>
                @endif
                <a href="{{ route('gestionti.catalogos.inventario.export', ['tab' => $tab, 'search' => $search]) }}">
                    <x-ui.button type="button" variant="secondary">Exportar a Excel</x-ui.button>
                </a>
                <x-ui.button wire:click="create">Nuevo</x-ui.button>
            </div>
        </div>

        @php
            $headers = match ($tab) {
                'tipo_equipo' => ['Nombre', 'Nombre conocido', 'En alcance', 'Estatus', ''],
                'modelos' => ['Nombre', 'Marca', 'Estatus', ''],
                'estatus_activo' => ['Código', 'Nombre', 'Estatus', ''],
                'periodicidad_mantenimiento' => ['Tipo de equipo', 'Meses sugeridos', 'Estatus', ''],
                'stock_minimo' => ['Tipo de equipo', 'Ubicación', 'Cantidad mínima', 'Estatus', ''],
                default => ['Nombre', 'Estatus', ''],
            };
        @endphp

        <x-ui.table :headers="$headers" :empty="$records->isEmpty()" empty-description="Agrega el primero con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="{{ $tab }}-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    @if ($tab === 'tipo_equipo')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->nombre_conocido }}</td>
                        <td class="py-2">
                            <x-ui.badge :color="$record->en_alcance ? 'emerald' : 'gray'">{{ $record->en_alcance ? 'Sí' : 'No' }}</x-ui.badge>
                        </td>
                    @elseif ($tab === 'modelos')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->marca?->nombre }}</td>
                    @elseif ($tab === 'estatus_activo')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->codigo }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->nombre }}</td>
                    @elseif ($tab === 'periodicidad_mantenimiento')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->tipoEquipo?->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->meses_sugeridos }}</td>
                    @elseif ($tab === 'stock_minimo')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->tipoEquipo?->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->ubicacion?->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->cantidad_minima }}</td>
                    @else
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre }}</td>
                    @endif
                    <td class="py-2">
                        <x-ui.badge :color="$record->activo ? 'emerald' : 'gray'">{{ $record->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="edit({{ $record->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>
                        <button
                            wire:click="toggleActivo({{ $record->id }})"
                            wire:confirm="¿{{ $record->activo ? 'Desactivar' : 'Reactivar' }} este registro?"
                            class="text-sm {{ $record->activo ? 'text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300' : 'text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300' }}"
                        >
                            {{ $record->activo ? 'Desactivar' : 'Reactivar' }}
                        </button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nuevo') . ' — ' . $config['label']">
        <form wire:submit="save" class="space-y-4">
            @if ($tab === 'tipo_equipo')
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
                <x-ui.input
                    label="Nombre conocido"
                    name="form.nombre_conocido"
                    wire:model="form.nombre_conocido"
                    hint="Cómo se le conoce internamente, si es distinto del nombre formal."
                />
                <x-ui.toggle label="En alcance del inventario activo (Laptop/PC/Monitor)" wire:model="form.en_alcance" />
            @elseif ($tab === 'modelos')
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
                <x-ui.select label="Marca" name="form.marca_id" wire:model="form.marca_id">
                    <option value="">Selecciona una marca</option>
                    @foreach ($marcaOptions as $marca)
                        <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                    @endforeach
                </x-ui.select>
            @elseif ($tab === 'estatus_activo')
                <x-ui.input label="Código" name="form.codigo" wire:model="form.codigo" hint="Clave estable, ej. en_stock." />
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
            @elseif ($tab === 'periodicidad_mantenimiento')
                <x-ui.select label="Tipo de equipo" name="form.tipo_equipo_id" wire:model="form.tipo_equipo_id">
                    <option value="">Selecciona un tipo de equipo</option>
                    @foreach ($tipoEquipoOptions as $tipoEquipo)
                        <option value="{{ $tipoEquipo->id }}">{{ $tipoEquipo->nombre }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input label="Meses sugeridos" name="form.meses_sugeridos" type="number" wire:model="form.meses_sugeridos" />
            @elseif ($tab === 'stock_minimo')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.select label="Tipo de equipo" name="form.tipo_equipo_id" wire:model="form.tipo_equipo_id">
                        <option value="">Selecciona un tipo de equipo</option>
                        @foreach ($tipoEquipoOptions as $tipoEquipo)
                            <option value="{{ $tipoEquipo->id }}">{{ $tipoEquipo->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="Ubicación" name="form.ubicacion_id" wire:model="form.ubicacion_id">
                        <option value="">Selecciona una ubicación</option>
                        @foreach ($ubicacionOptions as $ubicacion)
                            <option value="{{ $ubicacion->id }}">{{ $ubicacion->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <x-ui.input label="Cantidad mínima" name="form.cantidad_minima" type="number" wire:model="form.cantidad_minima" />
            @else
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    @if (array_key_exists('mergeReferences', $config))
        @php
            $mergeDeleteLabel = $mergeDeleteId ? $this->mergeOptionLabel($config['model']::find($mergeDeleteId)) : '(sin seleccionar)';
            $mergeKeepLabel = $mergeKeepId ? $this->mergeOptionLabel($config['model']::find($mergeKeepId)) : '(sin seleccionar)';
            $mergeConfirmMessage = "¿Fusionar duplicados? Se eliminará permanentemente \"{$mergeDeleteLabel}\" y todas sus referencias se repuntarán hacia \"{$mergeKeepLabel}\". Esta acción no se puede deshacer.";
        @endphp
        <x-ui.modal model="showMergeModal" title="Fusionar duplicados — {{ $config['label'] }}">
            <form wire:submit="confirmMerge" class="space-y-4">
                <x-ui.select label="Registro a eliminar" name="mergeDeleteId" wire:model.live="mergeDeleteId">
                    <option value="">Selecciona un registro</option>
                    @foreach ($mergeOptions as $option)
                        <option value="{{ $option->id }}">{{ $this->mergeOptionLabel($option) }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Registro que se conserva" name="mergeKeepId" wire:model.live="mergeKeepId">
                    <option value="">Selecciona un registro</option>
                    @foreach ($mergeOptions as $option)
                        <option value="{{ $option->id }}">{{ $this->mergeOptionLabel($option) }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.alert variant="warning">
                    Esta acción elimina permanentemente el registro "a eliminar" y repunta todas sus referencias hacia el registro que se conserva. No se puede deshacer.
                </x-ui.alert>

                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelMerge">Cancelar</x-ui.button>
                    <x-ui.button type="submit" variant="danger" wire:confirm="{{ $mergeConfirmMessage }}">
                        Fusionar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <x-ui.help-modal titulo="Catálogos de Inventario" :pdf-url="route('gestionti.ayuda.pdf', 'catalogos-inventario')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('catalogos-inventario')])
    </x-ui.help-modal>
</div>
