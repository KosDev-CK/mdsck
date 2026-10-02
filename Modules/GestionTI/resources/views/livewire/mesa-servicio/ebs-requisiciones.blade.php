<div>
    @push('page-title')
        SIC en EBS
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.toast-group>
        @if (session('status'))
            <x-ui.toast variant="success">{{ session('status') }}</x-ui.toast>
        @endif
    </x-ui.toast-group>

    @php
        $estatusColors = [
            'APPROVED' => 'emerald',
            'REJECTED' => 'red',
            'IN PROCESS' => 'indigo',
        ];
    @endphp

    <div x-data="{ filtrosOpen: false }">
        <div class="mb-4">
            <x-ui.button type="button" variant="secondary" @click="filtrosOpen = true">
                <x-heroicon-o-funnel class="h-4 w-4" />
                Filtros
            </x-ui.button>
        </div>

        <div x-show="filtrosOpen" x-cloak x-transition.opacity @click="filtrosOpen = false" class="fixed inset-0 z-40 bg-gray-900/50"></div>

        <aside
            x-cloak
            :class="{ 'translate-x-full': !filtrosOpen, 'translate-x-0': filtrosOpen }"
            class="fixed inset-y-0 right-0 z-50 flex w-full max-w-sm flex-col overflow-y-auto bg-white p-5 shadow-xl transition-transform duration-200 dark:bg-gray-900 dark:border-l dark:border-gray-800"
        >
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Filtros</h3>
                <button type="button" @click="filtrosOpen = false" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="flex-1 space-y-4">
                <input
                    wire:model.live.debounce.300ms="codigoFilter"
                    type="search"
                    placeholder="Buscar por código, descripción, solicitante, notas..."
                    class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >
                <x-ui.select name="estatusFilter" wire:model.live="estatusFilter">
                    <option value="">Todos los estatus</option>
                    @foreach ($estatusOptions as $estatus)
                        <option value="{{ $estatus }}">{{ $estatus }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select name="vinculacionFilter" wire:model.live="vinculacionFilter">
                    <option value="">Vinculada o no</option>
                    <option value="vinculada">Vinculadas</option>
                    <option value="no_vinculada">No vinculadas</option>
                </x-ui.select>
                <x-ui.select name="asignacionFilter" wire:model.live="asignacionFilter">
                    <option value="">Asignación a Solicitud de Compra (todas)</option>
                    <option value="asignada">Con solicitud asignada</option>
                    <option value="sin_asignar">Sin asignar</option>
                </x-ui.select>
                <x-ui.input type="date" label="Desde" name="fechaDesde" wire:model.live="fechaDesde" />
                <x-ui.input type="date" label="Hasta" name="fechaHasta" wire:model.live="fechaHasta" />
                <x-ui.input type="date" label="Autorizada desde" name="fechaAprobadaDesde" wire:model.live="fechaAprobadaDesde" />
                <x-ui.input type="date" label="Autorizada hasta" name="fechaAprobadaHasta" wire:model.live="fechaAprobadaHasta" />

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="soloSeleccionables" class="rounded border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700">
                    Solo SICs autorizadas y seleccionables
                </label>
            </div>

            <x-ui.button type="button" variant="secondary" @click="filtrosOpen = false" class="mt-6 w-full">Ocultar filtros</x-ui.button>
        </aside>
    </div>

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="seleccionarTodas">Seleccionar todas</x-ui.button>
                <x-ui.button type="button" variant="secondary" wire:click="deseleccionarTodas">Deseleccionar todas</x-ui.button>
                <span class="text-xs text-gray-500 dark:text-gray-400">Actúa sobre las filas elegibles de esta página.</span>
            </div>

            @if (! empty($sicIdsSeleccionados) || ! empty($ebsIdsSeleccionados))
                <x-ui.button type="button" wire:click="crearSolicitudProveedor">
                    Crear Solicitud a Proveedor ({{ count($sicIdsSeleccionados) + count($ebsIdsSeleccionados) }})
                </x-ui.button>
            @endif
        </div>

        <x-ui.table :headers="['', 'Código', 'Descripción', 'Estatus', 'Fecha', 'Fecha de autorización', 'Vinculada', 'Id solicitud prov.', '']" :empty="$records->isEmpty()" empty-description="Corre gestionti:ebs-sincronizar-creadas para traer datos reales de EBS.">
            @foreach ($records as $record)
                @php
                    $lineaAsignada = $record->solicitudSicBorrador
                        ? $record->solicitudSicBorrador->solicitudProveedorLineas->first()
                        : $record->solicitudProveedorLineas->first();
                @endphp
                <tr wire:key="ebs-requisicion-{{ $record->id }}" class="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                    <td class="py-2">
                        @if ($record->solicitudSicBorrador && in_array($record->solicitudSicBorrador->id, $elegibleSicIds, true))
                            <input
                                type="checkbox"
                                value="{{ $record->solicitudSicBorrador->id }}"
                                wire:model.live="sicIdsSeleccionados"
                                class="rounded border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700"
                            >
                        @elseif (! $record->solicitudSicBorrador && in_array($record->id, $elegibleEbsIds, true))
                            <input
                                type="checkbox"
                                value="{{ $record->id }}"
                                wire:model.live="ebsIdsSeleccionados"
                                class="rounded border-gray-300 text-primary focus:ring-primary dark:bg-gray-800 dark:border-gray-700"
                            >
                        @endif
                    </td>
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">
                        <button type="button" wire:click="openDetalle({{ $record->id }})" class="text-left">{{ $record->code }}</button>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">
                        <button type="button" wire:click="openDetalle({{ $record->id }})" class="text-left">{{ $record->description }}</button>
                    </td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$record->status] ?? 'gray'">{{ $record->status ?? '—' }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->fecha_creacion?->format('d/m/Y') }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $record->approver_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="py-2">
                        @if ($record->solicitudSicBorrador)
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                SIC #{{ $record->solicitudSicBorrador->id }}
                                @if ($record->solicitudSicBorrador->ticket)
                                    — Ticket {{ $record->solicitudSicBorrador->ticket->sdp_display_id ?? $record->solicitudSicBorrador->ticket->sdp_id ?? ('#'.$record->solicitudSicBorrador->ticket->id) }}
                                @endif
                            </span>
                        @else
                            <span class="text-sm text-gray-400 dark:text-gray-500">No vinculada</span>
                        @endif
                    </td>
                    <td class="py-2">
                        @if ($lineaAsignada)
                            <button type="button" wire:click="openSolicitudProveedor({{ $lineaAsignada->solicitud_id }})" class="text-sm text-primary hover:underline">
                                {{ $lineaAsignada->solicitud->folio }}
                            </button>
                        @else
                            <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                        @endif
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">
                        @if (! $record->solicitudSicBorrador)
                            <x-ui.icon-button wire:click="openVincular({{ $record->id }})" icon="heroicon-o-link" title="Vincular" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4 flex items-center justify-between gap-4">
            <div class="flex gap-2">
                <a href="{{ route('gestionti.ebs-requisiciones.export', [
                    'codigo' => $codigoFilter,
                    'estatus' => $estatusFilter,
                    'vinculacion' => $vinculacionFilter,
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta,
                    'aprobada_desde' => $fechaAprobadaDesde,
                    'aprobada_hasta' => $fechaAprobadaHasta,
                ]) }}"
                    title="Descargar Excel"
                    class="rounded-md border border-gray-300 p-2 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                </a>
            </div>

            {{ $records->links() }}
        </div>
    </x-ui.card>

    <x-ui.modal model="showVincularModal" title="Vincular con una Solicitud de SIC">
        <div class="space-y-4">
            <input
                wire:model.live.debounce.300ms="vincularSearch"
                type="search"
                placeholder="Buscar por folio SIC, ticket o solicitante..."
                class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            @error('vincularSolicitudId')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if ($vincularSearch !== '')
                <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($solicitudOptions as $opcion)
                        <label class="flex items-center gap-2 py-2 cursor-pointer">
                            <input type="radio" wire:model="vincularSolicitudId" value="{{ $opcion->id }}">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                SIC #{{ $opcion->id }} — {{ $opcion->empleado?->nombre }}
                                — {{ $opcion->folio_sic ?: 'Sin folio' }}
                                @if ($opcion->ticket)
                                    (Ticket {{ $opcion->ticket->sdp_display_id ?? $opcion->ticket->sdp_id ?? ('#'.$opcion->ticket->id) }})
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400 py-2">Sin resultados.</p>
                    @endforelse
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelVincular">Cancelar</x-ui.button>
                <x-ui.button type="button" wire:click="confirmVincular">Vincular</x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal model="showDetalleModal" title="Detalle de la requisición {{ $detalle?->code }}" max-width="max-w-3xl">
        @include('gestionti::partials.ebs-requisicion-detalle', ['detalle' => $detalle, 'estatusColors' => $estatusColors, 'mostrarLinkSolicitudProveedor' => true])
    </x-ui.modal>

    <x-ui.modal model="showSolicitudProveedorModal" title="Solicitud a Proveedor {{ $solicitudProveedorDetalle?->folio }}" max-width="max-w-2xl">
        @php
            $spEstatusLabels = [
                'solicitada' => 'Solicitada',
                'parcialmente_recibida' => 'Parcialmente recibida',
                'recibida' => 'Recibida',
                'facturada' => 'Facturada',
                'cancelada' => 'Cancelada',
            ];
        @endphp
        @if (! $solicitudProveedorDetalle)
            <p class="text-sm text-gray-500 dark:text-gray-400">No se pudo cargar el detalle.</p>
        @else
            <div class="space-y-4">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Folio</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $solicitudProveedorDetalle->folio }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Proveedor</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $solicitudProveedorDetalle->vendor?->nombre_comercial }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Fecha de solicitud</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $solicitudProveedorDetalle->fecha_solicitud?->format('d/m/Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Estatus</dt>
                        <dd>
                            <x-ui.badge color="gray">{{ $spEstatusLabels[$solicitudProveedorDetalle->estatus] ?? $solicitudProveedorDetalle->estatus }}</x-ui.badge>
                        </dd>
                    </div>
                </dl>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Líneas</h4>
                    @if ($solicitudProveedorDetalle->lineas->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin líneas registradas.</p>
                    @else
                        <x-ui.table :headers="['Artículo', 'Cantidad', 'Precio unitario']">
                            @foreach ($solicitudProveedorDetalle->lineas as $linea)
                                <tr wire:key="sp-detalle-linea-{{ $linea->id }}" class="border-b border-gray-50 dark:border-gray-800">
                                    <td class="py-2 text-gray-900 dark:text-gray-100">{{ $linea->articulo?->descripcion ?? $linea->descripcion_libre ?? '—' }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $linea->cantidad_solicitada }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $linea->precio_unitario_cotizado ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-ui.button type="button" variant="secondary" wire:click="closeSolicitudProveedor">Cerrar</x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.modal>

    <x-ui.help-modal titulo="SIC en EBS" :pdf-url="route('gestionti.ayuda.pdf', 'ebs-requisiciones')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('ebs-requisiciones')])
    </x-ui.help-modal>
</div>
