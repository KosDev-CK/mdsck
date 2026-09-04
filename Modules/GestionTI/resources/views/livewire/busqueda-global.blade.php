<div>
    @push('page-title')
        Búsqueda Global
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.card padding="p-5">
        <div class="mb-5">
            <x-ui.input
                wire:model.live.debounce.300ms="query"
                type="search"
                label="Buscar en todo el módulo"
                placeholder="Serie, service tag, número de empleado, folio..."
                hint="Busca por serie/service tag de activo, número de empleado, o folio (SIC, solicitud a proveedor, recepción, factura)."
            />
        </div>

        @if (strlen($term) < 2)
            <x-ui.empty-state
                icon="magnifying-glass"
                title="Escribe al menos 2 caracteres para buscar"
            />
        @elseif (empty($results))
            <x-ui.empty-state
                icon="magnifying-glass"
                title="Sin resultados para &ldquo;{{ $term }}&rdquo;"
            />
        @else
            <div class="space-y-6">
                @foreach ($results as $key => $group)
                    <div wire:key="grupo-{{ $key }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $group['label'] }}</h3>
                            @if ($group['total'] > $group['items']->count())
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    Mostrando {{ $group['items']->count() }} de {{ $group['total'] }} — refina tu búsqueda
                                </span>
                            @endif
                        </div>

                        <ul class="divide-y divide-gray-100 dark:divide-gray-800 rounded-md border border-gray-100 dark:border-gray-800 overflow-hidden">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <a
                                        href="{{ $item['url'] }}"
                                        class="flex items-center justify-between gap-3 px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                    >
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $item['title'] }}</p>
                                            @if ($item['subtitle'])
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $item['subtitle'] }}</p>
                                            @endif
                                        </div>
                                        <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-300 dark:text-gray-600 shrink-0" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <x-ui.help-modal titulo="Búsqueda Global" :pdf-url="route('gestionti.ayuda.pdf', 'busqueda-global')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('busqueda-global')])
    </x-ui.help-modal>
</div>
