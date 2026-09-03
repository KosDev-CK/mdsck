<div>
    @push('page-title')
        Empleados
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar..."
                class="w-full sm:w-64 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
            <div class="flex items-center gap-2">
                <a href="{{ route('gestionti.catalogos.empleados.export', ['search' => $search]) }}">
                    <x-ui.button type="button" variant="secondary">Exportar a Excel</x-ui.button>
                </a>
                <x-ui.button wire:click="create">Nuevo</x-ui.button>
            </div>
        </div>

        <x-ui.table :headers="['Número de empleado', 'Nombre', 'Correo', 'Puesto', 'Estatus', '']" :empty="$records->isEmpty()" empty-description="Agrega el primero con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="empleado-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->numero_empleado }}</td>
                    <td class="py-2">{{ $record->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->correo }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->puesto?->nombre }}</td>
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

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nuevo') . ' — Empleado'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Número de empleado" name="form.numero_empleado" wire:model="form.numero_empleado" />
                <x-ui.input label="Nombre" name="form.nombre" wire:model="form.nombre" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Correo" name="form.correo" type="email" wire:model="form.correo" />
                <x-ui.input label="RFC" name="form.rfc" wire:model="form.rfc" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Puesto" name="form.puesto_id" wire:model="form.puesto_id">
                    <option value="">Selecciona...</option>
                    @foreach ($puestoOptions as $puesto)
                        <option value="{{ $puesto->id }}">{{ $puesto->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Área" name="form.area_id" wire:model="form.area_id">
                    <option value="">Selecciona...</option>
                    @foreach ($areaOptions as $area)
                        <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Ubicación" name="form.ubicacion_id" wire:model="form.ubicacion_id">
                    <option value="">Selecciona...</option>
                    @foreach ($ubicacionOptions as $ubicacion)
                        <option value="{{ $ubicacion->id }}">{{ $ubicacion->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Unidad de negocio" name="form.unidad_negocio_id" wire:model="form.unidad_negocio_id">
                    <option value="">Selecciona...</option>
                    @foreach ($unidadNegocioOptions as $unidadNegocio)
                        <option value="{{ $unidadNegocio->id }}">{{ $unidadNegocio->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Empresa" name="form.empresa_id" wire:model="form.empresa_id">
                    <option value="">Selecciona...</option>
                    @foreach ($empresaOptions as $empresa)
                        <option value="{{ $empresa->id }}">{{ $empresa->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Jefe inmediato o Gerente" name="form.jefe_inmediato_id" wire:model="form.jefe_inmediato_id">
                    <option value="">Sin asignar</option>
                    @foreach ($empleadoOptions as $empleadoOpcion)
                        <option value="{{ $empleadoOpcion->id }}">{{ $empleadoOpcion->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Director" name="form.director_id" wire:model="form.director_id">
                    <option value="">Sin asignar</option>
                    @foreach ($empleadoOptions as $empleadoOpcion)
                        <option value="{{ $empleadoOpcion->id }}">{{ $empleadoOpcion->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Director Ejecutivo" name="form.director_ejecutivo_id" wire:model="form.director_ejecutivo_id">
                    <option value="">Sin asignar</option>
                    @foreach ($empleadoOptions as $empleadoOpcion)
                        <option value="{{ $empleadoOpcion->id }}">{{ $empleadoOpcion->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
