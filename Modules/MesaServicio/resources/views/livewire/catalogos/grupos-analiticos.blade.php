<div>
    @push('page-title')
        Grupos Analíticos
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
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Grupos Analíticos</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Catálogo propio, editable, para agrupar técnicos (y potencialmente otras entidades más adelante) con
            fines de reporte — independiente de los grupos que maneja ServiceDesk Plus.
        </p>

        <form wire:submit="addGrupo" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end mb-6">
            <x-ui.input label="Nombre" name="newNombre" wire:model="newNombre" />
            <x-ui.input label="Descripción (opcional)" name="newDescripcion" wire:model="newDescripcion" />
            <x-ui.button type="submit">Agregar</x-ui.button>
        </form>

        <x-ui.table
            :headers="['Nombre', 'Descripción', 'Activo', '']"
            :empty="$grupos->isEmpty()"
            empty-title="Sin grupos analíticos"
        >
            @foreach ($grupos as $grupo)
                <tr wire:key="grupo-analitico-{{ $grupo->id }}" class="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors">
                    @if ($editingId === $grupo->id)
                        <td class="py-2">
                            <x-ui.input name="editNombre" wire:model="editNombre" />
                        </td>
                        <td class="py-2">
                            <x-ui.input name="editDescripcion" wire:model="editDescripcion" />
                        </td>
                        <td class="py-2">
                            <x-ui.badge :color="$grupo->activo ? 'emerald' : 'gray'">{{ $grupo->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                        </td>
                        <td class="py-2 text-right whitespace-nowrap">
                            <x-ui.button wire:click="update" size="sm">Guardar</x-ui.button>
                            <x-ui.button wire:click="cancelEdit" variant="secondary" size="sm">Cancelar</x-ui.button>
                        </td>
                    @else
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $grupo->nombre }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $grupo->descripcion ?? '—' }}</td>
                        <td class="py-2">
                            <x-ui.toggle wire:click="toggleActivo({{ $grupo->id }})" :checked="$grupo->activo" />
                        </td>
                        <td class="py-2 text-right">
                            <x-ui.row-actions>
                                <x-ui.icon-button wire:click="edit({{ $grupo->id }})" icon="heroicon-o-pencil-square" title="Editar" />
                                <x-ui.icon-button
                                    wire:click="delete({{ $grupo->id }})"
                                    wire:confirm="¿Eliminar este grupo analítico? Esta acción no se puede deshacer."
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

    <x-ui.help-modal titulo="Grupos Analíticos" :pdf-url="route('mesaservicio.ayuda.pdf', 'grupos-analiticos')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('grupos-analiticos')])
    </x-ui.help-modal>
</div>
