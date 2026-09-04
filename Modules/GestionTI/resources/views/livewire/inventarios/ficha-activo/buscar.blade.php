<div>
    @push('page-title')
        Ficha de Activo
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Código, número de serie o service tag..."
                class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
        </div>

        <x-ui.table :headers="['Código', 'Tipo', 'Marca/Modelo', 'N° de serie', 'Estatus', '']" :empty="$records->isEmpty()" empty-description="Ningún activo coincide con la búsqueda.">
            @foreach ($records as $asset)
                <tr wire:key="ficha-activo-{{ $asset->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $asset->codigo }}</td>
                    <td class="py-2">{{ $asset->tipoEquipo?->nombre }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ trim(($asset->marca?->nombre ?? '').' '.($asset->modelo?->nombre ?? '')) ?: '—' }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $asset->numero_serie ?? '—' }}</td>
                    <td class="py-2">
                        <x-ui.badge color="gray">{{ $asset->estatus?->nombre ?? '—' }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">
                        <a href="{{ route('gestionti.ficha-activo.show', $asset) }}" class="text-sm text-primary hover:brightness-90">Ver ficha</a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $records->links() }}</div>
    </x-ui.card>

    <x-ui.help-modal titulo="Ficha de Activo" :pdf-url="route('gestionti.ayuda.pdf', 'ficha-activo')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('ficha-activo')])
    </x-ui.help-modal>
</div>
