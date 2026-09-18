<div class="space-y-6">
    @push('page-title')
        Catálogos SDP
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

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Catálogos de ServiceDesk Plus</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Espejo local de solo lectura de los 12 catálogos de configuración de SDP.
            </p>
        </div>

        <x-ui.button
            variant="secondary"
            size="sm"
            wire:click="sincronizar"
            wire:loading.attr="disabled"
            wire:target="sincronizar"
        >
            <span wire:loading.remove wire:target="sincronizar">Sincronizar catálogos</span>
            <span wire:loading wire:target="sincronizar">Sincronizando…</span>
        </x-ui.button>
    </div>

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap gap-1 border-b border-gray-100 dark:border-gray-800 mb-4">
            @foreach ($etiquetas as $catalogo => $etiqueta)
                <button
                    type="button"
                    wire:click="setTab('{{ $catalogo }}')"
                    wire:key="tab-{{ $catalogo }}"
                    class="px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                        {{ $tabActiva === $catalogo
                            ? 'border-primary text-primary'
                            : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                >
                    {{ $etiqueta }}
                </button>
            @endforeach
        </div>

        <x-ui.table
            :headers="['Nombre', 'Descripción', 'Color', 'Activo']"
            :empty="$entradas->isEmpty()"
            empty-title="Sin registros sincronizados todavía"
            empty-description='Da clic en "Sincronizar catálogos" para traer los datos desde ServiceDesk Plus.'
        >
            @foreach ($entradas as $entrada)
                <tr wire:key="entrada-{{ $entrada->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $entrada->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $entrada->descripcion ?? '—' }}</td>
                    <td class="py-2">
                        @if ($entrada->color)
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-block w-3 h-3 rounded-full border border-gray-200 dark:border-gray-700" style="background-color: {{ $entrada->color }};"></span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $entrada->color }}</span>
                            </span>
                        @else
                            <span class="text-gray-400 dark:text-gray-500">—</span>
                        @endif
                    </td>
                    <td class="py-2">
                        <x-ui.badge :color="$entrada->activo ? 'emerald' : 'gray'">{{ $entrada->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.help-modal titulo="Catálogos SDP" :pdf-url="route('mesaservicio.ayuda.pdf', 'catalogos-sdp')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('catalogos-sdp')])
    </x-ui.help-modal>
</div>
