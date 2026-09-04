<div>
    @push('page-title')
        Historial de Avisos
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.card padding="p-5">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-5 mb-4">
            <x-ui.select label="Tipo de aviso" name="tipoAvisoFilter" wire:model.live="tipoAvisoFilter">
                <option value="">Todos</option>
                @foreach ($tipoAvisoOptions as $tipoAviso)
                    <option value="{{ $tipoAviso->id }}">{{ $tipoAviso->codigo }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Canal" name="canalFilter" wire:model.live="canalFilter">
                <option value="">Todos</option>
                <option value="correo">Correo</option>
                <option value="in_app">En la aplicación</option>
            </x-ui.select>

            <x-ui.select label="Estatus" name="estatusFilter" wire:model.live="estatusFilter">
                <option value="">Todos</option>
                <option value="enviado">Enviado</option>
                <option value="fallido">Fallido</option>
            </x-ui.select>

            <x-ui.input type="date" label="Desde" name="fechaDesde" wire:model.live="fechaDesde" />
            <x-ui.input type="date" label="Hasta" name="fechaHasta" wire:model.live="fechaHasta" />
        </div>

        <x-ui.table :headers="['Tipo de aviso', 'Destinatario', 'Canal', 'Fecha de envío', 'Estatus', 'Leído']" :empty="$records->isEmpty()" empty-description="No hay avisos que coincidan con los filtros.">
            @foreach ($records as $record)
                <tr wire:key="aviso-enviado-{{ $record->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $record->tipoAviso?->codigo ?? 'Tipo eliminado' }}</td>
                    <td class="py-2">{{ $record->destinatario?->name }} <span class="text-gray-500 dark:text-gray-400">{{ $record->destinatario?->email }}</span></td>
                    <td class="py-2">
                        <x-ui.badge :color="$record->canal === 'correo' ? 'info' : 'indigo'">{{ $record->canal === 'correo' ? 'Correo' : 'En la aplicación' }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_envio->format('d/m/Y H:i') }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$record->estatus_envio === 'enviado' ? 'emerald' : 'red'">{{ $record->estatus_envio === 'enviado' ? 'Enviado' : 'Fallido' }}</x-ui.badge>
                    </td>
                    <td class="py-2">
                        @if ($record->canal === 'in_app')
                            <x-ui.badge :color="$record->leido ? 'emerald' : 'gray'">{{ $record->leido ? 'Sí' : 'No' }}</x-ui.badge>
                        @else
                            <span class="text-gray-400 dark:text-gray-500">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.help-modal titulo="Historial de Avisos" :pdf-url="route('gestionti.ayuda.pdf', 'avisos-historial')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('avisos-historial')])
    </x-ui.help-modal>
</div>
