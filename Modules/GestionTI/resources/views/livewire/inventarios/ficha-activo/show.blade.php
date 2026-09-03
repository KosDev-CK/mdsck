<div>
    @push('page-title')
        Ficha de Activo — {{ $asset->codigo }}
    @endpush

    @php
        // Mismos tokens semánticos que usa `x-ui.badge` internamente (ver
        // resources/views/components/ui/badge.blade.php) — nunca un color de
        // marca hardcodeado (bg-indigo-500, etc.), la marca puede cambiar en
        // tiempo real vía SiteSetting.
        $colorClasses = [
            'gray' => 'bg-gray-100 text-gray-500 dark:bg-gray-700/50 dark:text-gray-300',
            'indigo' => 'bg-primary/10 text-primary',
            'emerald' => 'bg-success/10 text-success',
            'amber' => 'bg-warning/10 text-warning',
            'red' => 'bg-danger/10 text-danger',
            'info' => 'bg-info/10 text-info',
        ];
    @endphp

    <div class="flex items-center justify-between gap-3 mb-4">
        <a href="{{ route('gestionti.ficha-activo.index') }}" class="text-sm text-primary hover:brightness-90">&larr; Volver al buscador</a>
        <button wire:click="exportTrazabilidadPdf" class="text-sm text-primary hover:brightness-90">Generar reporte PDF</button>
    </div>

    <x-ui.card padding="p-5" class="mb-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $asset->codigo }}</h3>
            <x-ui.badge color="gray">{{ $asset->estatus?->nombre ?? '—' }}</x-ui.badge>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Tipo de equipo</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $asset->tipoEquipo?->nombre ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Marca / Modelo</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ trim(($asset->marca?->nombre ?? '').' '.($asset->modelo?->nombre ?? '')) ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">N° de serie / Service tag</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $asset->numero_serie ?? '—' }} / {{ $asset->service_tag ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Ubicación actual</dt>
                <dd class="text-gray-900 dark:text-gray-100">{{ $asset->ubicacionActual?->nombre_conocido ?? $asset->ubicacionActual?->nombre ?? '—' }}</dd>
            </div>
            @if ($asset->estatus?->codigo === 'reservado')
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">SIC reservada actual</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $asset->sicReservada?->folio_sic ?: ($asset->sicReservada ? "SIC #{$asset->sicReservada->id}" : '—') }}</dd>
                </div>
            @endif
        </dl>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Línea de tiempo</h3>

        @if (empty($timeline))
            <x-ui.empty-state description="Este activo no tiene eventos registrados todavía." />
        @else
            <ul class="space-y-6">
                @foreach ($timeline as $evento)
                    <li wire:key="evento-{{ $loop->index }}" class="flex gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $colorClasses[$evento['color']] ?? $colorClasses['gray'] }}">
                            <x-dynamic-component :component="'heroicon-o-'.$evento['icono']" class="h-5 w-5" />
                        </div>
                        <div class="flex-1 pb-4 {{ ! $loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $evento['titulo'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $evento['fecha']?->format('d/m/Y') ?? 'Sin fecha registrada' }}</p>
                            </div>
                            @if ($evento['detalle'])
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $evento['detalle'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
