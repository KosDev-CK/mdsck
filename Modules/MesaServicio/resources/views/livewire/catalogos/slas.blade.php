<div class="space-y-6">
    @push('page-title')
        Cumplimiento de SLA
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

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Definiciones de SLA</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Catálogo propio, editable, de tiempos objetivo por prioridad. La prioridad debe coincidir exactamente
            con el nombre que usa ServiceDesk Plus (ej. "Alta") — vacío = definición "por defecto" para cualquier
            prioridad sin una definición específica activa.
        </p>

        <form wire:submit="addDefinicion" class="grid grid-cols-1 sm:grid-cols-5 gap-2 items-end mb-6">
            <x-ui.input label="Nombre" name="newNombre" wire:model="newNombre" />
            <x-ui.input label="Prioridad" name="newPrioridad" wire:model="newPrioridad" hint="Vacío = por defecto" />
            <x-ui.input label="1ra. respuesta (min)" name="newTiempoPrimeraRespuesta" type="number" wire:model="newTiempoPrimeraRespuesta" />
            <x-ui.input label="Resolución (min)" name="newTiempoResolucion" type="number" wire:model="newTiempoResolucion" />
            <x-ui.button type="submit">Agregar</x-ui.button>
        </form>

        <x-ui.table
            :headers="['Nombre', 'Prioridad', '1ra. respuesta (min)', 'Resolución (min)', 'Activo', '']"
            :empty="$definiciones->isEmpty()"
            empty-title="Sin definiciones de SLA"
        >
            @foreach ($definiciones as $definicion)
                <tr wire:key="sla-{{ $definicion->id }}" class="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors">
                    @if ($editingId === $definicion->id)
                        <td class="py-2">
                            <x-ui.input name="editNombre" wire:model="editNombre" />
                        </td>
                        <td class="py-2">
                            <x-ui.input name="editPrioridad" wire:model="editPrioridad" />
                        </td>
                        <td class="py-2">
                            <x-ui.input name="editTiempoPrimeraRespuesta" type="number" wire:model="editTiempoPrimeraRespuesta" />
                        </td>
                        <td class="py-2">
                            <x-ui.input name="editTiempoResolucion" type="number" wire:model="editTiempoResolucion" />
                        </td>
                        <td class="py-2">
                            <x-ui.badge :color="$definicion->activo ? 'emerald' : 'gray'">{{ $definicion->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                        </td>
                        <td class="py-2 text-right whitespace-nowrap">
                            <x-ui.button wire:click="update" size="sm">Guardar</x-ui.button>
                            <x-ui.button wire:click="cancelEdit" variant="secondary" size="sm">Cancelar</x-ui.button>
                        </td>
                    @else
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $definicion->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $definicion->prioridad ?? 'Por defecto' }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $definicion->tiempo_primera_respuesta_minutos ?? '—' }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $definicion->tiempo_resolucion_minutos ?? '—' }}</td>
                        <td class="py-2">
                            <x-ui.toggle wire:click="toggleActivo({{ $definicion->id }})" :checked="$definicion->activo" />
                        </td>
                        <td class="py-2 text-right">
                            <x-ui.row-actions>
                                <x-ui.icon-button wire:click="edit({{ $definicion->id }})" icon="heroicon-o-pencil-square" title="Editar" />
                                <x-ui.icon-button
                                    wire:click="delete({{ $definicion->id }})"
                                    wire:confirm="¿Eliminar esta definición de SLA? Esta acción no se puede deshacer."
                                    icon="heroicon-o-trash"
                                    title="Eliminar"
                                    variant="danger"
                                />
                            </x-ui.row-actions>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-end gap-3">
            <x-ui.input label="Desde" name="desde" type="date" wire:model.live="desde" />
            <x-ui.input label="Hasta" name="hasta" type="date" wire:model.live="hasta" />
        </div>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Cumplimiento por técnico</h2>

        <x-ui.table
            :headers="['Técnico', '1ra. respuesta', 'Resolución']"
            :empty="$porTecnico->isEmpty()"
            empty-title="Sin tickets con técnico asignado en el rango seleccionado"
        >
            @foreach ($porTecnico as $fila)
                <tr wire:key="tecnico-cumplimiento-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $fila['etiqueta'] }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">
                        @if ($fila['primera_respuesta']['pct'] === null)
                            <span class="text-xs">Sin datos evaluables</span>
                        @else
                            {{ $fila['primera_respuesta']['pct'] }}% ({{ $fila['primera_respuesta']['cumplidas'] }}/{{ $fila['primera_respuesta']['evaluables'] }})
                        @endif
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">
                        @if ($fila['resolucion']['pct'] === null)
                            <span class="text-xs">Sin datos evaluables</span>
                        @else
                            {{ $fila['resolucion']['pct'] }}% ({{ $fila['resolucion']['cumplidas'] }}/{{ $fila['resolucion']['evaluables'] }})
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Cumplimiento por categoría</h2>

        <x-ui.table
            :headers="['Categoría', '1ra. respuesta', 'Resolución']"
            :empty="$porCategoria->isEmpty()"
            empty-title="Sin tickets en el rango seleccionado"
        >
            @foreach ($porCategoria as $fila)
                <tr wire:key="categoria-cumplimiento-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $fila['etiqueta'] }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">
                        @if ($fila['primera_respuesta']['pct'] === null)
                            <span class="text-xs">Sin datos evaluables</span>
                        @else
                            {{ $fila['primera_respuesta']['pct'] }}% ({{ $fila['primera_respuesta']['cumplidas'] }}/{{ $fila['primera_respuesta']['evaluables'] }})
                        @endif
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">
                        @if ($fila['resolucion']['pct'] === null)
                            <span class="text-xs">Sin datos evaluables</span>
                        @else
                            {{ $fila['resolucion']['pct'] }}% ({{ $fila['resolucion']['cumplidas'] }}/{{ $fila['resolucion']['evaluables'] }})
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.help-modal titulo="Cumplimiento de SLA" :pdf-url="route('mesaservicio.ayuda.pdf', 'sla')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('sla')])
    </x-ui.help-modal>
</div>
