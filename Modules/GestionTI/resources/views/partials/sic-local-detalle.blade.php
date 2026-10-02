{{--
    Contenido del modal "Detalle de la SIC" para una `SolicitudSicBorrador`
    puramente local (capturada a mano, sin `ebs_requisition_id`) — cuando sí
    tiene vínculo a EBS, se usa en su lugar
    `partials.ebs-requisicion-detalle` (ese es el detalle que realmente le
    interesa revisar, con líneas/notas reales de Oracle EBS). Ver
    `livewire/compras/solicitudes-proveedor.blade.php`, columna "SIC" de la
    tabla de líneas, y `SolicitudesProveedor::openSicDetalle()`.

    Props esperadas:
    - $detalle: ?SolicitudSicBorrador, con ['empleado', 'ticket', 'tipoEquipo',
      'articulo.categoria', 'centroCosto', 'unidadNegocio', 'solicitudProveedorLineas.solicitud']
      precargado.
--}}
@php
    $sicEstatusLabels = [
        'capturado' => 'Capturado',
        'sic_creada' => 'SIC creada',
        'autorizada' => 'Autorizada',
        'rechazada' => 'Rechazada',
    ];
    $sicEstatusColors = [
        'capturado' => 'gray',
        'sic_creada' => 'indigo',
        'autorizada' => 'emerald',
        'rechazada' => 'red',
    ];
@endphp

@if (! $detalle)
    <p class="text-sm text-gray-500 dark:text-gray-400">No se pudo cargar el detalle.</p>
@else
    <div class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <dl class="text-sm space-y-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Folio</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $detalle->folio_sic ?: "SIC #{$detalle->id}" }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Estatus</dt>
                    <dd><x-ui.badge :color="$sicEstatusColors[$detalle->estatus] ?? 'gray'">{{ $sicEstatusLabels[$detalle->estatus] ?? $detalle->estatus }}</x-ui.badge></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Fecha de solicitud</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->fecha_solicitud?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Empleado</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->empleado?->nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Ticket</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        {{ $detalle->ticket?->sdp_display_id ?? $detalle->ticket?->sdp_id ?? ($detalle->ticket ? "#{$detalle->ticket->id}" : '—') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Urgencia</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->urgencia ? ucfirst($detalle->urgencia) : '—' }}</dd>
                </div>
            </dl>

            <dl class="text-sm space-y-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Artículo</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        {{ $detalle->articulo?->descripcion ?? '—' }}
                        @if ($detalle->articulo?->categoria)
                            <span class="text-gray-500 dark:text-gray-400">({{ $detalle->articulo->categoria->nombre }})</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tipo de equipo</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->tipoEquipo?->nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Centro de costo</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $detalle->centroCosto?->nombre ?? '—' }}</dd>
                </div>
                @php
                    $lineaAsignadaSicLocal = $detalle->solicitudProveedorLineas->first();
                @endphp
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Id solicitud prov.</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $lineaAsignadaSicLocal?->solicitud?->folio ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div>
            <dt class="text-gray-500 dark:text-gray-400 text-sm">Motivo</dt>
            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $detalle->motivo ?? '—' }}</dd>
        </div>

        @if ($detalle->especificaciones_requeridas)
            <div>
                <dt class="text-gray-500 dark:text-gray-400 text-sm">Especificaciones requeridas</dt>
                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $detalle->especificaciones_requeridas }}</dd>
            </div>
        @endif

        <div class="flex justify-end">
            <x-ui.button type="button" variant="secondary" wire:click="closeDetalle">Cerrar</x-ui.button>
        </div>
    </div>
@endif
