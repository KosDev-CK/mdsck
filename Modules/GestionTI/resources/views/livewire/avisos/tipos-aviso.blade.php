<div>
    @push('page-title')
        Configuración de Avisos
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
            <x-ui.button wire:click="create">Nuevo</x-ui.button>
        </div>

        <x-ui.table :headers="['Código', 'Descripción', 'Evento disparador', 'Destinatarios', 'Estatus', '']" :empty="$records->isEmpty()" empty-description="Agrega el primero con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="tipo-aviso-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->codigo }}</td>
                    <td class="py-2">{{ $record->descripcion }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->evento_disparador }}</td>
                    <td class="py-2">{{ $record->destinatarios_count }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$record->activo ? 'emerald' : 'gray'">{{ $record->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        <button wire:click="edit({{ $record->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">Editar</button>
                        <button
                            wire:click="toggleActivo({{ $record->id }})"
                            wire:confirm="¿{{ $record->activo ? 'Desactivar' : 'Reactivar' }} este tipo de aviso?"
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

    <x-ui.modal model="showModal" :title="($editingId ? 'Editar' : 'Nuevo') . ' tipo de aviso'" maxWidth="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-ui.input label="Código" name="form.codigo" wire:model="form.codigo" hint="Identificador único, ej. SIC_AUTORIZADA." />
            <x-ui.input label="Descripción" name="form.descripcion" wire:model="form.descripcion" />
            <x-ui.input label="Entidad relacionada" name="form.entidad_relacionada" wire:model="form.entidad_relacionada" hint="Clase base a la que aplica, ej. SolicitudSicBorrador." />
            <x-ui.input label="Evento disparador" name="form.evento_disparador" wire:model="form.evento_disparador" hint="Código único que el código dispara, ej. SIC_AUTORIZADA." />
            @php($plantillaHint = 'Usa dobles llaves alrededor del nombre de la variable (ej. folio) para sustituir datos al momento del envío.')
            <x-ui.input type="textarea" rows="3" label="Plantilla del mensaje" name="form.plantilla_mensaje" wire:model="form.plantilla_mensaje" :hint="$plantillaHint" />

            <x-ui.toggle label="Activo" name="form.activo" wire:model="form.activo" />

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destinatarios</label>
                    <button type="button" wire:click="addDestinatario" class="text-sm text-primary hover:underline">+ Agregar destinatario</button>
                </div>

                @error('destinatarios')
                    <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="space-y-3">
                    @foreach ($destinatarios as $i => $destinatario)
                        <div wire:key="destinatario-{{ $i }}" class="flex items-end gap-2 border border-gray-100 dark:border-gray-700 rounded-md p-3">
                            <div class="flex-1 space-y-2">
                                <x-ui.select label="Tipo de destinatario" name="destinatarios.{{ $i }}.tipo_destinatario" wire:model.live="destinatarios.{{ $i }}.tipo_destinatario">
                                    <option value="">Selecciona...</option>
                                    <option value="rol_fijo">Rol fijo</option>
                                    <option value="validador_especifico">Validador específico</option>
                                    <option value="dinamico_solicitante">Dinámico — solicitante</option>
                                    <option value="dinamico_responsable">Dinámico — responsable</option>
                                </x-ui.select>

                                @if (($destinatario['tipo_destinatario'] ?? '') === 'rol_fijo')
                                    <x-ui.select label="Rol" name="destinatarios.{{ $i }}.rol_nombre" wire:model="destinatarios.{{ $i }}.rol_nombre">
                                        <option value="">Selecciona un rol</option>
                                        @foreach ($rolOptions as $rol)
                                            <option value="{{ $rol }}">{{ $rol }}</option>
                                        @endforeach
                                    </x-ui.select>
                                @elseif (($destinatario['tipo_destinatario'] ?? '') === 'validador_especifico')
                                    <x-ui.select label="Validador" name="destinatarios.{{ $i }}.validador_id" wire:model="destinatarios.{{ $i }}.validador_id">
                                        <option value="">Selecciona un validador</option>
                                        @foreach ($validadorOptions as $validador)
                                            <option value="{{ $validador->id }}">{{ $validador->nombre }}</option>
                                        @endforeach
                                    </x-ui.select>
                                @endif
                            </div>

                            <button type="button" wire:click="removeDestinatario({{ $i }})" class="text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300 mb-2">
                                Quitar
                            </button>
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
