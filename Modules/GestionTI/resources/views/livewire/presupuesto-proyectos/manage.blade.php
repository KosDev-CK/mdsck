<div>
    @push('page-title')
        Presupuesto por Proyecto
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @php
        $estatusLabels = [
            'armado' => 'Armado',
            'en_captura_costos' => 'En captura de costos',
            'completo' => 'Completo',
            'en_autorizacion' => 'En autorización',
            'autorizado' => 'Autorizado',
            'rechazado' => 'Rechazado',
        ];
        $estatusColors = [
            'armado' => 'gray',
            'en_captura_costos' => 'amber',
            'completo' => 'indigo',
            'en_autorizacion' => 'indigo',
            'autorizado' => 'emerald',
            'rechazado' => 'red',
        ];
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Buscar por nombre, PM o centro de costo..."
                    class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
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

        <x-ui.table :headers="['Proyecto', 'Empresa', 'Centro de costo', 'PM responsable', 'Fecha límite de captura', 'Estatus', '']" :empty="$records->isEmpty()" empty-description="Agrega el primero con el botón Nuevo.">
            @foreach ($records as $record)
                <tr wire:key="proyecto-presupuesto-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->nombre_proyecto }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->empresa?->nombre_comercial }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->centroCosto?->nombre }}</td>
                    <td class="py-2">{{ $record->pmResponsable?->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_limite_captura?->format('d/m/Y') }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->estatus] ?? 'gray'">{{ $estatusLabels[$record->estatus] ?? $record->estatus }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">
                        <a href="{{ route('gestionti.presupuestos-proyecto.show', $record) }}" class="text-sm text-primary hover:brightness-90">Ver detalle</a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" title="Nuevo Presupuesto por Proyecto" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-ui.input label="Nombre del proyecto" name="form.nombre_proyecto" wire:model="form.nombre_proyecto" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Empresa" name="form.empresa_id" wire:model="form.empresa_id">
                    <option value="">Selecciona...</option>
                    @foreach ($empresaOptions as $empresa)
                        <option value="{{ $empresa->id }}">{{ $empresa->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="Centro de costo" name="form.centro_costo_id" wire:model="form.centro_costo_id">
                    <option value="">Selecciona...</option>
                    @foreach ($centroCostoOptions as $centroCosto)
                        <option value="{{ $centroCosto->id }}">{{ $centroCosto->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <x-ui.input label="Dirección del centro" name="form.direccion_centro" wire:model="form.direccion_centro" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.select label="Área operativa solicitante" name="form.area_operativa_solicitante_id" wire:model="form.area_operativa_solicitante_id">
                    <option value="">Selecciona...</option>
                    @foreach ($areaOptions as $area)
                        <option value="{{ $area->id }}">{{ $area->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select label="PM responsable" name="form.pm_responsable_id" wire:model="form.pm_responsable_id">
                    <option value="">Selecciona...</option>
                    @foreach ($empleadoOptions as $empleado)
                        <option value="{{ $empleado->id }}">{{ $empleado->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.input label="Fecha de solicitud" name="form.fecha_solicitud" type="date" wire:model="form.fecha_solicitud" />
                <x-ui.input label="Fecha límite de captura" name="form.fecha_limite_captura" type="date" wire:model="form.fecha_limite_captura" />
            </div>

            <x-ui.input
                label="Factor administrativo"
                name="form.factor_administrativo"
                type="number"
                step="0.0001"
                min="1"
                wire:model="form.factor_administrativo"
                hint="Se aplica sobre los totales del Excel exportado (One Time, On going y Total). No se puede cambiar después de crear el proyecto."
            />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.help-modal titulo="Presupuesto por Proyecto" :pdf-url="route('gestionti.ayuda.pdf', 'presupuestos-proyecto')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('presupuestos-proyecto')])
    </x-ui.help-modal>
</div>
