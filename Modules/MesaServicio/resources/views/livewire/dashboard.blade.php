<div wire:poll.60s>
    @push('page-title')
        Mesa de Servicio
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

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Dashboard de Mesa de Servicio</h1>

        <div class="flex flex-wrap items-center gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input
                    type="checkbox"
                    wire:model.live="soloNivel1"
                    class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary dark:bg-gray-800 dark:border-gray-700"
                >
                Solo técnicos Nivel 1
            </label>

            <x-ui.button
                variant="secondary"
                size="sm"
                wire:click="sincronizar"
                wire:loading.attr="disabled"
                wire:target="sincronizar"
            >
                <span wire:loading.remove wire:target="sincronizar">Sincronizar ahora</span>
                <span wire:loading wire:target="sincronizar">Sincronizando…</span>
            </x-ui.button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-ui.stat-tile label="Creados hoy" :value="$creadosHoy" icon="inbox-arrow-down" color="info" />
        <x-ui.stat-tile label="Atendidos hoy" :value="$atendidosHoy" icon="check-circle" color="success" />
        <x-ui.stat-tile label="Pendientes" :value="$pendientes" icon="clock" color="warning" />
        <x-ui.stat-tile
            label="SLA de 1ª respuesta vencido"
            :value="$slaVencidosCount"
            icon="exclamation-triangle"
            :color="$slaVencidosCount > 0 ? 'danger' : 'primary'"
            hint="Creados hace más de 10 min sin respuesta"
        />
    </div>

    @if ($slaVencidosCount > 0)
        <x-ui.card padding="p-5" class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Tickets con SLA de primera respuesta vencido</h2>
                <x-ui.badge color="red">{{ $slaVencidosCount }} en total</x-ui.badge>
            </div>

            <x-ui.alert variant="warning" class="mb-4">
                Mostrando los 10 más antiguos. Creados hace más de 10 minutos y sin una primera respuesta registrada en SDP.
            </x-ui.alert>

            <x-ui.table :headers="['Folio', 'Asunto', 'Técnico', 'Creado', 'Solicitante']" :empty="$slaVencidosDetalle->isEmpty()">
                @foreach ($slaVencidosDetalle as $ticket)
                    <tr wire:key="sla-{{ $ticket->id }}" class="border-b border-gray-50 dark:border-gray-800">
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $ticket->display_id ?? $ticket->sdp_id }}</td>
                        <td class="py-2 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($ticket->asunto, 60) }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">
                            @if ($ticket->technician)
                                <a wire:navigate href="{{ route('mesaservicio.tecnicos.show', $ticket->technician) }}" class="text-primary hover:underline">
                                    {{ $ticket->technician->nombre }}
                                </a>
                            @else
                                Sin asignar
                            @endif
                        </td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $ticket->created_time->format('d/m/Y H:i') }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $ticket->solicitante_nombre }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Hallazgos del día</h2>
            <span class="text-xs text-gray-400 dark:text-gray-500">Detección por reglas simples, no machine learning</span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                    Categorías más frecuentes hoy
                </h3>

                @if ($topCategorias->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin tickets registrados hoy.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($topCategorias as $fila)
                            <li wire:key="top-categoria-{{ $loop->index }}" class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-700 dark:text-gray-200 truncate">{{ $fila->categoria }}</span>
                                <x-ui.badge color="indigo">{{ $fila->conteo }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                    Picos detectados
                </h3>

                @if (! $historicoSuficiente)
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Historial insuficiente para comparar contra el promedio (se necesitan al menos {{ $minimoDiasHistorial }} días de datos sincronizados).
                    </p>
                @elseif ($picos->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sin picos respecto al promedio histórico.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($picos as $pico)
                            <x-ui.alert variant="warning" wire:key="pico-{{ $loop->index }}">
                                <span class="font-medium">{{ $pico['categoria'] }}</span>:
                                {{ $pico['hoy'] }} tickets hoy vs. promedio de {{ $pico['promedio'] }}
                                ({{ $pico['ratio'] }}x)
                            </x-ui.alert>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-ui.card>

    <x-ui.help-modal titulo="Dashboard de Mesa de Servicio" :pdf-url="route('mesaservicio.ayuda.pdf', 'dashboard')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('dashboard')])
    </x-ui.help-modal>
</div>
