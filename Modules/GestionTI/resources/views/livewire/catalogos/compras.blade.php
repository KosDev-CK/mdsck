<div>
    @push('page-title')
        Catálogos de Compras
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
                <a href="{{ route('gestionti.catalogos.compras.export', ['tab' => $tab, 'search' => $search]) }}">
                    <x-ui.button type="button" variant="secondary">Exportar a Excel</x-ui.button>
                </a>
                @if ($tab !== 'ebs_articulos')
                    <x-ui.button wire:click="create">Nuevo</x-ui.button>
                @endif
            </div>
        </div>

        @php
            $headers = match ($tab) {
                'proveedores' => ['Nombre comercial', 'Razón social', 'RFC', 'Contacto', 'Estatus', ''],
                'categorias' => ['Nombre', 'Va a Compras', 'Estatus', ''],
                'lugares_entrega' => ['Nombre', 'Estatus', ''],
                'ebs_articulos' => ['Item ID (EBS)', 'Descripción en EBS', 'Artículo mapeado', ''],
                default => ['Código', 'Descripción', 'Unidad de medida', 'Categoría', 'Tipo de equipo', 'Estatus', ''],
            };
        @endphp

        <x-ui.table :headers="$headers" :empty="$records->isEmpty()" :empty-description="$tab === 'ebs_articulos' ? 'Todavía no hay ítems de EBS sincronizados — corre gestionti:ebs-sincronizar-creadas.' : 'Agrega el primero con el botón Nuevo.'">
            @foreach ($records as $record)
                <tr wire:key="{{ $tab }}-{{ $record->id }}" class="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors">
                    @if ($tab === 'proveedores')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre_comercial }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->razon_social }}</td>
                        <td class="py-2">{{ $record->rfc }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->contacto_nombre }}</td>
                    @elseif ($tab === 'categorias')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre }}</td>
                        <td class="py-2">
                            <x-ui.badge :color="$record->es_compra ? 'emerald' : 'gray'">{{ $record->es_compra ? 'Sí' : 'No' }}</x-ui.badge>
                        </td>
                    @elseif ($tab === 'lugares_entrega')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre }}</td>
                    @elseif ($tab === 'ebs_articulos')
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->ebs_item_id }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->ebs_item_description ?? '—' }}</td>
                        <td class="py-2">
                            @if ($record->articulo)
                                <span class="text-gray-900 dark:text-gray-100">{{ $record->articulo->codigo }} — {{ $record->articulo->descripcion }}</span>
                            @else
                                <x-ui.badge color="amber">Sin mapear</x-ui.badge>
                            @endif
                        </td>
                    @else
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->codigo }}</td>
                        <td class="py-2">{{ $record->descripcion }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->unidad_medida }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->categoria?->nombre ?? '—' }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->tipoEquipo?->nombre ?? '—' }}</td>
                    @endif
                    @if ($tab !== 'ebs_articulos')
                        <td class="py-2">
                            <x-ui.badge :color="$record->activo ? 'emerald' : 'gray'">{{ $record->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                        </td>
                    @endif
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <x-ui.row-actions>
                            <x-ui.icon-button wire:click="edit({{ $record->id }})" icon="heroicon-o-pencil-square" title="Editar" />
                            @if ($tab !== 'ebs_articulos')
                                <button
                                    wire:click="toggleActivo({{ $record->id }})"
                                    wire:confirm="¿{{ $record->activo ? 'Desactivar' : 'Reactivar' }} este registro?"
                                    class="text-sm {{ $record->activo ? 'text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300' : 'text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300' }}"
                                >
                                    {{ $record->activo ? 'Desactivar' : 'Reactivar' }}
                                </button>
                                <x-ui.icon-button
                                    wire:click="delete({{ $record->id }})"
                                    wire:confirm="¿Eliminar este registro? Esta acción no se puede deshacer."
                                    icon="heroicon-o-trash"
                                    title="Eliminar"
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

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nuevo') . ' — ' . $config['label']" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            @if ($tab === 'proveedores')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.input label="Nombre comercial" name="form.nombre_comercial" wire:model="form.nombre_comercial" />
                    <x-ui.input label="Razón social" name="form.razon_social" wire:model="form.razon_social" />
                </div>
                <x-ui.input label="RFC" name="form.rfc" wire:model="form.rfc" />
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-ui.input label="Contacto" name="form.contacto_nombre" wire:model="form.contacto_nombre" />
                    <x-ui.input label="Teléfono de contacto" name="form.contacto_telefono" wire:model="form.contacto_telefono" />
                    <x-ui.input label="Correo de contacto" name="form.contacto_correo" type="email" wire:model="form.contacto_correo" />
                </div>
            @elseif ($tab === 'categorias')
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
                <x-ui.toggle label="Va a Compras (genera Solicitud a Proveedor)" name="form.es_compra" wire:model="form.es_compra" />
            @elseif ($tab === 'lugares_entrega')
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
            @elseif ($tab === 'ebs_articulos')
                <x-ui.select label="Artículo del catálogo" name="form.articulo_id" wire:model="form.articulo_id" hint="El artículo estándar/genérico al que corresponde este ítem de EBS (no la marca/modelo real — eso se resuelve después, en Recepción de Proveedor).">
                    <option value="">Sin mapear</option>
                    @foreach ($articuloMapeadoOptions as $articuloMapeado)
                        <option value="{{ $articuloMapeado->id }}">{{ $articuloMapeado->codigo }} — {{ $articuloMapeado->descripcion }}</option>
                    @endforeach
                </x-ui.select>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.input label="Código" name="form.codigo" wire:model="form.codigo" />
                    <x-ui.input label="Unidad de medida" name="form.unidad_medida" wire:model="form.unidad_medida" hint="Ej. pieza, caja." />
                </div>
                <x-ui.input label="Descripción" name="form.descripcion" wire:model="form.descripcion" />
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.select label="Categoría (opcional)" name="form.categoria_id" wire:model="form.categoria_id">
                        <option value="">Sin asignar</option>
                        @foreach ($categoriaOptions as $categoria)
                            <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="Tipo de equipo" name="form.tipo_equipo_id" wire:model="form.tipo_equipo_id">
                        <option value="">Sin asignar</option>
                        @foreach ($tipoEquipoOptions as $tipoEquipo)
                            <option value="{{ $tipoEquipo->id }}">{{ $tipoEquipo->nombre }}</option>
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
                            <option value="{{ $modelo->id }}">{{ $modelo->marca?->nombre }} — {{ $modelo->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-ui.select label="Procesador (opcional)" name="form.procesador_id" wire:model="form.procesador_id">
                        <option value="">Sin asignar</option>
                        @foreach ($procesadorOptions as $procesador)
                            <option value="{{ $procesador->id }}">{{ $procesador->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="RAM (opcional)" name="form.ram_id" wire:model="form.ram_id">
                        <option value="">Sin asignar</option>
                        @foreach ($ramOptions as $ram)
                            <option value="{{ $ram->id }}">{{ $ram->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.select label="Almacenamiento (opcional)" name="form.almacenamiento_id" wire:model="form.almacenamiento_id">
                        <option value="">Sin asignar</option>
                        @foreach ($almacenamientoOptions as $almacenamiento)
                            <option value="{{ $almacenamiento->id }}">{{ $almacenamiento->nombre }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <x-ui.toggle label="Es inventariable (se puede dar de alta como Activo real)" name="form.es_inventariable" wire:model="form.es_inventariable" />
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

    <x-ui.help-modal titulo="Catálogos de Compras" :pdf-url="route('gestionti.ayuda.pdf', 'catalogos-compras')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('catalogos-compras')])
    </x-ui.help-modal>
</div>
