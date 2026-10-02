{{--
    Contenido del modal "Detalle de la requisición" — extraído de
    `livewire/mesa-servicio/ebs-requisiciones.blade.php` (su pantalla
    original) para reutilizarse tal cual desde cualquier otra pantalla que
    necesite mostrar el mismo detalle de solo lectura al dar clic sobre un
    número de SIC/requisición (ver `livewire/compras/solicitudes-proveedor.blade.php`,
    columna "SIC" de la tabla de líneas).

    Props esperadas:
    - $detalle: ?EbsRequisition, con ['lines', 'notes', 'solicitudSicBorrador.ticket',
      'solicitudSicBorrador.solicitudProveedorLineas.solicitud', 'solicitudProveedorLineas.solicitud']
      precargado.
    - $estatusColors: array — mapa status EBS -> color de `<x-ui.badge>`.
    - $mostrarLinkSolicitudProveedor: bool (default true) — en "SIC en EBS" el
      link "Id solicitud prov." abre el modal de esa Solicitud a Proveedor
      (`openSolicitudProveedor`); al reutilizarse DESDE la propia pantalla de
      Solicitud a Proveedor no tiene sentido (es circular, ya se está viendo
      esa misma solicitud) — en ese caso se pasa `false` y se muestra el
      folio como texto plano, sin acción.
--}}
@php
    $mostrarLinkSolicitudProveedor = $mostrarLinkSolicitudProveedor ?? true;
@endphp

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
                @php
                    $lineaAsignadaDetalle = $detalle->solicitudSicBorrador
                        ? $detalle->solicitudSicBorrador->solicitudProveedorLineas->first()
                        : $detalle->solicitudProveedorLineas->first();
                @endphp
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Id solicitud prov.</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        @if ($lineaAsignadaDetalle)
                            @if ($mostrarLinkSolicitudProveedor)
                                <button type="button" wire:click="openSolicitudProveedor({{ $lineaAsignadaDetalle->solicitud_id }})" class="text-primary hover:underline">
                                    {{ $lineaAsignadaDetalle->solicitud->folio }}
                                </button>
                            @else
                                {{ $lineaAsignadaDetalle->solicitud->folio }}
                            @endif
                        @else
                            —
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
