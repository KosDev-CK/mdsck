<div>
    @push('page-title')
        Catálogos Núcleo
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
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar..."
                class="w-full sm:w-64 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
            <div class="flex items-center gap-2">
                @if (array_key_exists('mergeReferences', $config))
                    <x-ui.button type="button" variant="secondary" wire:click="openMerge">Fusionar duplicados</x-ui.button>
                @endif
                <a href="{{ route('gestionti.catalogos.nucleo.export', ['tab' => $tab, 'search' => $search]) }}">
                    <x-ui.button type="button" variant="secondary">Exportar a Excel</x-ui.button>
                </a>
                <x-ui.button wire:click="create">Nuevo</x-ui.button>
            </div>
        </div>

        @php
            $headers = match ($tab) {
                'empresas' => ['Nombre comercial', 'Razón social', 'RFC', 'Estatus', ''],
                'centros_costo' => ['Código', 'Nombre', 'Empresa', 'Estatus', ''],
                default => ['Nombre', 'Nombre conocido', 'Estatus', ''],
            };
        @endphp

        <x-ui.table :headers="$headers" :empty="$records->isEmpty()" empty-description="Agrega el primero con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="{{ $tab }}-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    @if ($tab === 'empresas')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre_comercial }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->razon_social }}</td>
                        <td class="py-2">{{ $record->rfc }}</td>
                    @elseif ($tab === 'centros_costo')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->codigo }}</td>
                        <td class="py-2">{{ $record->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->empresa?->nombre_comercial }}</td>
                    @else
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->nombre_conocido }}</td>
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
            @if ($tab === 'empresas')
                <x-ui.input label="Nombre comercial" name="form.nombre_comercial" wire:model="form.nombre_comercial" />
                <x-ui.input label="Razón social" name="form.razon_social" wire:model="form.razon_social" />
                <x-ui.input label="RFC" name="form.rfc" wire:model="form.rfc" />
            @elseif ($tab === 'centros_costo')
                <x-ui.input label="Código" name="form.codigo" wire:model="form.codigo" />
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
                <x-ui.input
                    label="Nombre conocido"
                    name="form.nombre_conocido"
                    wire:model="form.nombre_conocido"
                    hint="Cómo se le conoce internamente, si es distinto del nombre formal."
                />
                <x-ui.select label="Empresa" name="form.empresa_id" wire:model="form.empresa_id">
                    <option value="">Selecciona una empresa</option>
                    @foreach ($empresasOptions as $empresa)
                        <option value="{{ $empresa->id }}">{{ $empresa->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>
            @else
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
                <x-ui.input
                    label="Nombre conocido"
                    name="form.nombre_conocido"
                    wire:model="form.nombre_conocido"
                    hint="Cómo se le conoce internamente, si es distinto del nombre formal."
                />
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

    <x-ui.help-modal titulo="Catálogos Núcleo" :pdf-url="route('gestionti.ayuda.pdf', 'catalogos-nucleo')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('catalogos-nucleo')])
    </x-ui.help-modal>
</div>
