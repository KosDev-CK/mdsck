<div>
    @push('page-title')
        Entregas internas
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.toast-group>
        @if (session('status'))
            <x-ui.toast variant="success">{{ session('status') }}</x-ui.toast>
        @endif
    </x-ui.toast-group>

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button type="button" :variant="$vista === 'pendientes' ? 'primary' : 'secondary'" wire:click="setVista('pendientes')">Por confirmar</x-ui.button>
                <x-ui.button type="button" :variant="$vista === 'confirmadas' ? 'primary' : 'secondary'" wire:click="setVista('confirmadas')">Confirmadas</x-ui.button>
            </div>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Buscar por folio o área..."
                class="w-full sm:w-72 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
        </div>

        <x-ui.table
            :headers="['Folio', 'Área', 'Solicitó', 'Enviada', 'Entrega prometida', 'Líneas', $vista === 'confirmadas' ? 'Confirmó' : 'Estatus']"
            :empty="$registros->isEmpty()"
            :empty-description="$vista === 'pendientes' ? 'No hay entregas por confirmar.' : 'Todavía no hay entregas confirmadas.'"
        >
            @foreach ($registros as $registro)
                <tr
                    wire:key="entrega-{{ $registro->id }}"
                    wire:click="openDetalle({{ $registro->id }})"
                    class="cursor-pointer border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors"
                >
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $registro->folio }}</td>
                    <td class="py-2">{{ $registro->vendor?->nombre_comercial }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $registro->creadoPor?->name ?? '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $registro->enviada_at?->format('d/m/Y') }}</td>
                    <td class="py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
                        {{ $registro->fecha_entrega_prometida?->format('d/m/Y') ?? '—' }}
                        @if ($registro->entrega_confirmada_at === null && $registro->fecha_entrega_prometida?->lt(today()))
                            <x-ui.badge color="red">Vencida</x-ui.badge>
                        @endif
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $registro->lineas_count }}</td>
                    <td class="py-2">
                        @if ($registro->entrega_confirmada_at)
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $registro->entregaConfirmadaPor?->name }} · {{ $registro->entrega_confirmada_at->format('d/m/Y') }}</span>
                        @else
                            <x-ui.badge color="amber">Por confirmar</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $registros->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" :title="'Entrega — '.($detalle?->folio ?? '')" max-width="max-w-3xl">
        @if ($detalle)
            <div class="space-y-4">
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Área</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->vendor?->nombre_comercial }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Solicitó</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->creadoPor?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Enviada</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->enviada_at?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Entrega prometida</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->fecha_entrega_prometida?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                </dl>

                <x-ui.table :headers="['SIC', 'Artículo', 'Cantidad', 'Entrega en', 'Observaciones']" :empty="$detalle->lineas->isEmpty()">
                    @foreach ($detalle->lineas as $linea)
                        <tr wire:key="entrega-linea-{{ $linea->id }}" class="border-b border-gray-50 dark:border-gray-800">
                            <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $linea->folioSicDisplay() ?? '—' }}</td>
                            <td class="py-2 text-gray-700 dark:text-gray-300">{{ $linea->articulo?->descripcion ?? $linea->descripcion_libre ?? '—' }}</td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $linea->cantidad_solicitada }}</td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $linea->lugarEntrega?->nombre ?? '—' }}</td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $linea->observaciones_especificaciones ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>

                @if ($detalle->entrega_confirmada_at)
                    <x-ui.alert variant="success">
                        Entrega confirmada por {{ $detalle->entregaConfirmadaPor?->name }} el {{ $detalle->entrega_confirmada_at->format('d/m/Y H:i') }}.
                        @if ($detalle->entrega_confirmada_notas)
                            <span class="block mt-1">Notas: {{ $detalle->entrega_confirmada_notas }}</span>
                        @endif
                    </x-ui.alert>
                    <div class="flex justify-end">
                        <x-ui.button type="button" variant="secondary" wire:click="closeDetalle">Cerrar</x-ui.button>
                    </div>
                @else
                    <div>
                        <label for="notas" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notas de la entrega (opcional)</label>
                        <textarea
                            id="notas"
                            wire:model="notas"
                            rows="3"
                            placeholder="Ej. se entregó a TI el 15/10, falta la caja del equipo..."
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                        ></textarea>
                        @error('notas')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" wire:click="closeDetalle">Cancelar</x-ui.button>
                        <x-ui.button type="button" wire:click="confirmarEntrega" wire:confirm="¿Confirmas que ya se entregó lo solicitado? Se avisará a quien creó la solicitud.">Confirmar entrega</x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </x-ui.modal>

    <x-ui.help-modal titulo="Entregas internas" :pdf-url="route('gestionti.ayuda.pdf', 'entregas-internas')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('entregas-internas')])
    </x-ui.help-modal>
</div>
