<div>
    @push('page-title')
        Stock
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
    @endphp

    @if ($alertasMinimos->isNotEmpty())
        <div class="mb-4 space-y-2">
            @foreach ($alertasMinimos as $alerta)
                <x-ui.alert variant="warning">
                    <span class="font-medium">{{ $alerta['tipo_equipo'] }}</span> en
                    <span class="font-medium">{{ $alerta['ubicacion'] }}</span>:
                    stock actual {{ $alerta['stock_actual'] }}, mínimo requerido {{ $alerta['cantidad_minima'] }}.
                </x-ui.alert>
            @endforeach
        </div>
    @endif

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 flex-1">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Código, número de serie o service tag..."
                    class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >

                <x-ui.select name="ubicacionFilter" wire:model.live="ubicacionFilter">
                    <option value="">Todas las ubicaciones</option>
                    @foreach ($ubicacionOptions as $ubicacion)
                        <option value="{{ $ubicacion->id }}">{{ $ubicacion->nombre_conocido ?? $ubicacion->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select name="tipoEquipoFilter" wire:model.live="tipoEquipoFilter">
                    <option value="">Todos los tipos</option>
                    @foreach ($tipoEquipoOptions as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->nombre_conocido ?? $tipo->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select name="marcaFilter" wire:model.live="marcaFilter">
                    <option value="">Todas las marcas</option>
                    @foreach ($marcaOptions as $marca)
                        <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select name="empresaFilter" wire:model.live="empresaFilter">
                    <option value="">Todas las empresas</option>
                    @foreach ($empresaOptions as $empresa)
                        <option value="{{ $empresa->id }}">{{ $empresa->nombre_comercial }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select name="estatusFilter" wire:model.live="estatusFilter">
                    <option value="">Todos los estatus</option>
                    @foreach ($estatusOptions as $estatus)
                        <option value="{{ $estatus->id }}">{{ $estatus->nombre }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            El filtro "Empresa" solo aplica a activos asignados — un activo en stock/reservado todavía no tiene empresa asociada.
        </p>

        <x-ui.table :headers="['Código', 'Tipo', 'Marca/Modelo', 'N° de serie', 'Ubicación', 'Estatus', 'SIC reservada', '']" :empty="$records->isEmpty()" empty-description="Ningún activo coincide con los filtros seleccionados.">
            @foreach ($records as $asset)
                <tr wire:key="asset-{{ $asset->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">
                        {{ $asset->codigo }}
                        <a href="{{ route('gestionti.ficha-activo.show', $asset->id) }}" class="ml-2 text-xs font-normal text-primary hover:brightness-90">Ver ficha</a>
                    </td>
                    <td class="py-2">{{ $asset->tipoEquipo?->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ trim(($asset->marca?->nombre ?? '').' '.($asset->modelo?->nombre ?? '')) ?: '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $asset->numero_serie ?? '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $asset->ubicacionActual?->nombre_conocido ?? $asset->ubicacionActual?->nombre ?? '—' }}</td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$asset->estatus?->codigo] ?? 'gray'">{{ $asset->estatus?->nombre }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">
                        @if ($asset->estatus?->codigo === 'reservado')
                            {{ $asset->sicReservada?->folio_sic ?: ($asset->sicReservada ? "SIC #{$asset->sicReservada->id}" : '—') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="py-2 text-right space-x-2 whitespace-nowrap">
                        @if ($asset->estatus?->codigo === 'reservado')
                            <button wire:click="openReassign({{ $asset->id }})" class="text-primary hover:brightness-90 text-sm">
                                Reasignar SIC
                            </button>
                        @endif

                        @if (in_array($asset->estatus?->codigo, ['en_stock', 'reservado'], true))
                            <button wire:click="openTraslado({{ $asset->id }})" class="text-indigo-600 hover:text-indigo-500 text-sm dark:text-indigo-400 dark:hover:text-indigo-300">
                                Trasladar
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showReassignModal" title="Reasignar SIC reservada">
        <form wire:submit="confirmReassign" class="space-y-4">
            <x-ui.select label="Nueva SIC" name="reassignForm.sic_nueva_id" wire:model="reassignForm.sic_nueva_id">
                <option value="">Selecciona...</option>
                @foreach ($sicReservationOptions as $sic)
                    <option value="{{ $sic->id }}">{{ $this->sicOptionLabel($sic) }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Motivo del cambio" name="reassignForm.motivo" type="textarea" wire:model="reassignForm.motivo" />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelReassign">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showTrasladoModal" title="Trasladar entre almacenes">
        <form wire:submit="confirmTraslado" class="space-y-4">
            <x-ui.select label="Ubicación destino" name="trasladoForm.ubicacion_destino_id" wire:model="trasladoForm.ubicacion_destino_id">
                <option value="">Selecciona...</option>
                @foreach ($ubicacionDestinoOptions as $ubicacion)
                    <option value="{{ $ubicacion->id }}">{{ $ubicacion->nombre_conocido ?? $ubicacion->nombre }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.input label="Comentarios (opcional)" name="trasladoForm.comentarios" type="textarea" wire:model="trasladoForm.comentarios" />

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelTraslado">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.help-modal titulo="Stock" :pdf-url="route('gestionti.ayuda.pdf', 'stock')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('stock')])
    </x-ui.help-modal>
</div>
