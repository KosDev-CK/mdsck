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
                <x-ui.input type="date" label="Desde" name="fechaDesde" wire:model.live="fechaDesde" />
                <x-ui.input type="date" label="Hasta" name="fechaHasta" wire:model.live="fechaHasta" />
                <x-ui.input type="date" label="Autorizada desde" name="fechaAprobadaDesde" wire:model.live="fechaAprobadaDesde" />
                <x-ui.input type="date" label="Autorizada hasta" name="fechaAprobadaHasta" wire:model.live="fechaAprobadaHasta" />
            </div>

            <x-ui.button type="button" variant="secondary" @click="filtrosOpen = false" class="mt-6 w-full">Ocultar filtros</x-ui.button>
        </aside>
    </div>

    <x-ui.card padding="p-5">
        <x-ui.table :headers="['Código', 'Descripción', 'Estatus', 'Fecha', 'Fecha de autorización', 'Vinculada', '']" :empty="$records->isEmpty()" empty-description="Corre gestionti:ebs-sincronizar-creadas para traer datos reales de EBS.">
            @foreach ($records as $record)
                <tr wire:key="ebs-requisicion-{{ $record->id }}" class="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
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
        @if (! $detalle)
            <p class="text-sm text-gray-500 dark:text-gray-400">No se pudo cargar el detalle.</p>
        @else
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <dl class="text-sm space-y-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Código</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $detalle->code }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Descripción</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->description ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Estatus</dt>
                            <dd><x-ui.badge :color="$estatusColors[$detalle->status] ?? 'gray'">{{ $detalle->status ?? '—' }}</x-ui.badge></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Vinculada</dt>
                            <dd class="text-gray-900 dark:text-gray-100">
                                @if ($detalle->solicitudSicBorrador)
                                    SIC #{{ $detalle->solicitudSicBorrador->id }}
                                    @if ($detalle->solicitudSicBorrador->ticket)
                                        — Ticket {{ $detalle->solicitudSicBorrador->ticket->sdp_display_id ?? $detalle->solicitudSicBorrador->ticket->sdp_id ?? ('#'.$detalle->solicitudSicBorrador->ticket->id) }}
                                    @endif
                                @else
                                    No vinculada
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <dl class="text-sm space-y-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Fecha de creación</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->fecha_creacion?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Fecha de autorización</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->approver_date?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Creada por</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->created_by_description ?? $detalle->created_by_user ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Autorizada por</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->approver_name ?? $detalle->approver_user ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Líneas</h4>
                    @if ($detalle->lines->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin líneas registradas.</p>
                    @else
                        <x-ui.table :headers="['#', 'Descripción', 'Cantidad', 'Unidad', 'Precio unitario', 'Moneda']">
                            @foreach ($detalle->lines as $line)
                                <tr wire:key="ebs-linea-{{ $line->id }}" class="border-b border-gray-50 dark:border-gray-800">
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $line->line_number }}</td>
                                    <td class="py-2 text-gray-900 dark:text-gray-100">{{ $line->item_description ?? '—' }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $line->quantity }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $line->unit_measurement ?? '—' }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $line->unit_price }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $line->currency_code ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Notas</h4>
                        @if ($detalle->notes->isNotEmpty())
                            <button
                                type="button"
                                title="Copiar notas"
                                x-data="{ copiado: false }"
                                @click="
                                    const texto = @js($detalle->notes->map(fn ($n) => "{$n->clave}: {$n->valor}")->join("\n"));
                                    const marcarCopiado = () => { copiado = true; setTimeout(() => copiado = false, 1500); };
                                    if (navigator.clipboard && window.isSecureContext) {
                                        navigator.clipboard.writeText(texto).then(marcarCopiado).catch(() => {
                                            const el = document.createElement('textarea');
                                            el.value = texto;
                                            el.style.position = 'fixed';
                                            el.style.opacity = '0';
                                            document.body.appendChild(el);
                                            el.focus();
                                            el.select();
                                            document.execCommand('copy');
                                            document.body.removeChild(el);
                                            marcarCopiado();
                                        });
                                    } else {
                                        const el = document.createElement('textarea');
                                        el.value = texto;
                                        el.style.position = 'fixed';
                                        el.style.opacity = '0';
                                        document.body.appendChild(el);
                                        el.focus();
                                        el.select();
                                        document.execCommand('copy');
                                        document.body.removeChild(el);
                                        marcarCopiado();
                                    }
                                "
                                class="shrink-0 rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-primary dark:text-gray-500 dark:hover:bg-gray-800"
                            >
                                <x-heroicon-o-clipboard-document x-show="!copiado" class="h-4 w-4" />
                                <x-heroicon-o-check x-show="copiado" class="h-4 w-4 text-emerald-500" />
                            </button>
                        @endif
                    </div>
                    @if ($detalle->notes->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sin notas registradas.</p>
                    @else
                        <ul class="text-sm space-y-1">
                            @foreach ($detalle->notes as $note)
                                <li class="text-gray-700 dark:text-gray-300">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $note->clave }}:</span>
                                    {{ $note->valor }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-ui.button type="button" variant="secondary" wire:click="closeDetalle">Cerrar</x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.modal>

    <x-ui.help-modal titulo="SIC en EBS" :pdf-url="route('gestionti.ayuda.pdf', 'ebs-requisiciones')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('ebs-requisiciones')])
    </x-ui.help-modal>
</div>
