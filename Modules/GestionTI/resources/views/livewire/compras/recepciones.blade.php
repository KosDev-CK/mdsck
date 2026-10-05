<div>
    @push('page-title')
        Recepción de Proveedor
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

    @php
        $estatusLabels = [
            'solicitada' => 'Solicitada',
            'parcialmente_recibida' => 'Parcialmente recibida',
            'recibida' => 'Recibida',
            'facturada' => 'Facturada',
            'cancelada' => 'Cancelada',
        ];
        $estatusColors = [
            'solicitada' => 'indigo',
            'parcialmente_recibida' => 'amber',
            'recibida' => 'emerald',
            'facturada' => 'emerald',
            'cancelada' => 'red',
        ];
    @endphp

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div x-data="{ camara: window.camaraDisponible?.() }" class="flex w-full items-center gap-2 sm:w-auto">
                {{-- Un lector de código de barras escribe el folio y manda Enter: abre esa solicitud. --}}
                <input
                    wire:model.live.debounce.300ms="search"
                    wire:keydown.enter="abrirPorCodigo($event.target.value)"
                    type="search"
                    autofocus
                    placeholder="Buscar o escanear folio de solicitud, proveedor o remisión..."
                    class="w-full sm:w-96 rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                >
                <button
                    type="button"
                    x-show="camara"
                    x-cloak
                    x-on:click="escanearConCamara({ titulo: 'Escanear folio de la solicitud', autoAceptar: true, alAceptar: (valor) => $wire.abrirPorCodigo(valor) })"
                    class="shrink-0 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200"
                >
                    Escanear
                </button>
            </div>
            <select
                wire:model.live="estatusFiltro"
                aria-label="Filtrar por estatus"
                class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >
                <option value="">Todas</option>
                <option value="pendientes">Pendientes de recibir</option>
                <option value="solicitada">Solicitadas</option>
                <option value="parcialmente_recibida">Parcialmente recibidas</option>
                <option value="recibida">Recibidas</option>
                <option value="facturada">Facturadas</option>
                <option value="cancelada">Canceladas</option>
            </select>
        </div>

        <x-ui.table :headers="['Solicitud', 'Proveedor', 'Fecha', 'Entrega prometida', 'Estatus', 'Recibido', 'Recepciones', '']" :empty="$solicitudes->isEmpty()" empty-description="No hay solicitudes a proveedor con ese filtro.">
            @foreach ($solicitudes as $solicitud)
                @php
                    $admite = in_array($solicitud->estatus, ['solicitada', 'parcialmente_recibida'], true);
                @endphp
                <tr
                    wire:key="solicitud-{{ $solicitud->id }}"
                    wire:click="abrirSolicitud({{ $solicitud->id }})"
                    class="cursor-pointer border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors"
                >
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $solicitud->folio }}</td>
                    <td class="py-2">{{ $solicitud->vendor?->nombre_comercial }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $solicitud->fecha_solicitud?->format('d/m/Y') }}</td>
                    <td class="py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
                        {{ $solicitud->fecha_entrega_prometida?->format('d/m/Y') ?? '—' }}
                        @if ($admite && $solicitud->fecha_entrega_prometida?->lt(today()))
                            <x-ui.badge color="red">Vencida</x-ui.badge>
                        @endif
                    </td>
                    <td class="py-2">
                        <x-ui.badge :color="$estatusColors[$solicitud->estatus] ?? 'gray'">{{ $estatusLabels[$solicitud->estatus] ?? $solicitud->estatus }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ (int) $solicitud->total_recibido }} de {{ (int) $solicitud->total_solicitado }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $solicitud->recepciones_count }}</td>
                    <td class="py-2 whitespace-nowrap text-right text-sm font-medium text-primary">{{ $admite ? 'Recibir' : 'Ver' }}</td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-4">{{ $solicitudes->links() }}</div>
    </x-ui.card>

    <x-ui.modal model="showModal" :title="'Recepción — '.($solicitudSeleccionada?->folio ?? '')" max-width="max-w-4xl">
        <form wire:submit="save" class="space-y-4">
            @if ($solicitudSeleccionada)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                    <span><strong>Proveedor:</strong> {{ $solicitudSeleccionada->vendor?->nombre_comercial }}</span>
                    <span><strong>Fecha de solicitud:</strong> {{ $solicitudSeleccionada->fecha_solicitud?->format('d/m/Y') }}</span>
                    <span><strong>Entrega prometida:</strong> {{ $solicitudSeleccionada->fecha_entrega_prometida?->format('d/m/Y') ?? '—' }}</span>
                    <x-ui.badge :color="$estatusColors[$solicitudSeleccionada->estatus] ?? 'gray'">{{ $estatusLabels[$solicitudSeleccionada->estatus] ?? $solicitudSeleccionada->estatus }}</x-ui.badge>
                </div>

                @error('selectedSolicitudId')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div>
                    <p class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recepciones registradas</p>
                    @forelse ($solicitudSeleccionada->recepciones as $recepcion)
                        <div wire:key="recepcion-{{ $recepcion->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-800 py-2 text-sm">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $recepcion->folio_remision }}</span>
                                <span class="text-gray-500 dark:text-gray-400"> · {{ $recepcion->fecha_recepcion?->format('d/m/Y') }} · Recibió: {{ $recepcion->recibidoPor?->nombre }}@if ($recepcion->registradoPor && $recepcion->registradoPor->id !== $recepcion->recibidoPor?->user_id) · Capturó: {{ $recepcion->registradoPor->name }}@endif</span>
                                @if ($solicitudSeleccionada->fecha_entrega_prometida && $recepcion->fecha_recepcion)
                                    @if ($recepcion->fecha_recepcion->lte($solicitudSeleccionada->fecha_entrega_prometida))
                                        <x-ui.badge color="emerald">A tiempo</x-ui.badge>
                                    @else
                                        <x-ui.badge color="red">Con retraso de {{ $solicitudSeleccionada->fecha_entrega_prometida->diffInDays($recepcion->fecha_recepcion) }} d</x-ui.badge>
                                    @endif
                                @endif
                            </div>
                            <x-ui.row-actions>
                                <x-ui.icon-button type="button" wire:click="exportActaPdf({{ $recepcion->id }})" icon="heroicon-o-arrow-down-tray" title="Generar PDF" />

                                @if ($recepcion->documentoRemision)
                                    <x-ui.icon-button tag="a" :href="$recepcion->documentoRemision->url()" target="_blank" icon="heroicon-o-eye" title="Ver remisión" />
                                    <x-ui.icon-button
                                        type="button"
                                        wire:click="quitarRemision({{ $recepcion->id }})"
                                        wire:confirm="¿Quitar la remisión vinculada? El archivo no se borra de SharePoint/disco, solo se desvincula de esta recepción."
                                        icon="heroicon-o-link-slash"
                                        title="Quitar"
                                        variant="danger"
                                    />
                                @else
                                    <x-ui.icon-button type="button" wire:click="openAttach({{ $recepcion->id }})" icon="heroicon-o-paper-clip" title="Adjuntar remisión" />
                                @endif
                            </x-ui.row-actions>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no se registra ninguna recepción de esta solicitud.</p>
                    @endforelse
                </div>

                @if ($admiteRecepcion && ! $validadorActual && ! $esAdministrador)
                    <x-ui.alert variant="warning">
                        Tu usuario no está dado de alta como técnico receptor, así que no puedes registrar recepciones. Pide a un administrador que te vincule en <strong>Catálogos de Inventario → Validador</strong> (campo "Usuario del sistema"; opcionalmente el sitio que atiendes).
                    </x-ui.alert>
                @endif

                @unless ($admiteRecepcion)
                    <x-ui.alert variant="info">
                        Esta solicitud está en estatus "{{ $estatusLabels[$solicitudSeleccionada->estatus] ?? $solicitudSeleccionada->estatus }}" y ya no admite recepciones nuevas.
                    </x-ui.alert>

                    <div>
                        <p class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Líneas de la solicitud</p>
                        @foreach ($solicitudSeleccionada->lineas as $lineaSolicitud)
                            <div wire:key="resumen-linea-{{ $lineaSolicitud->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 dark:border-gray-800 py-2 text-sm">
                                <span class="text-gray-900 dark:text-gray-100">{{ $lineaSolicitud->articulo?->descripcion ?? $lineaSolicitud->descripcion_libre }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Solicitado: {{ $lineaSolicitud->cantidad_solicitada }} · Recibido: {{ $lineaSolicitud->cantidad_recibida }}</span>
                            </div>
                        @endforeach
                    </div>
                @endunless
            @endif

            @if ($solicitudSeleccionada && $admiteRecepcion && ($validadorActual || $esAdministrador))
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Registrar nueva recepción</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Recibido por</label>
                        @if ($esAdministrador)
                            <x-ui.select name="tecnicoRecibeId" wire:model.live="tecnicoRecibeId" class="mt-1">
                                <option value="">Selecciona al técnico que recibió...</option>
                                @foreach ($tecnicosOptions as $tecnicoOpcion)
                                    <option value="{{ $tecnicoOpcion->id }}">{{ $tecnicoOpcion->nombre }}{{ $tecnicoOpcion->lugarEntrega ? ' · '.$tecnicoOpcion->lugarEntrega->nombre : '' }}</option>
                                @endforeach
                            </x-ui.select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Como administrador capturas por el técnico que recibió: queda registrada a su nombre y se guarda que tú la capturaste.</p>
                        @else
                            <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-100">{{ $validadorActual->nombre }}</div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Es quien tiene la sesión iniciada.</p>
                        @endif
                        @error('recibido_por')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sitio de entrega que se recibe</label>
                        @if (! $validadorActual)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Elige primero al técnico.</p>
                        @elseif ($validadorActual->lugar_entrega_id)
                            <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-100">{{ $validadorActual->lugarEntrega?->nombre }}</div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Es la sede del técnico: solo se reciben las líneas de ese sitio.</p>
                        @else
                            <x-ui.select name="lugarRecepcionId" wire:model.live="lugarRecepcionId" class="mt-1">
                                <option value="">Selecciona...</option>
                                @foreach ($lugaresRecepcion as $lugarOpcion)
                                    <option value="{{ $lugarOpcion->id }}">{{ $lugarOpcion->nombre }}</option>
                                @endforeach
                            </x-ui.select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Este técnico no tiene sede asignada: elige el sitio. Una recepción cubre un solo sitio; las líneas de otros sitios se reciben aparte.</p>
                        @endif
                        @error('lugarRecepcionId')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @if ($puedeRecibir)
                @if (collect($lineas)->pluck('sic_id')->filter()->isNotEmpty())
                    <x-ui.alert variant="info">
                        Una o más líneas de esta solicitud tienen una SIC asociada — los activos inventariables de esas líneas quedarán <strong>reservados</strong> contra su SIC correspondiente, en vez de libres en stock (ver el detalle en cada línea abajo).
                    </x-ui.alert>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.input label="Folio de remisión" name="form.folio_remision" wire:model="form.folio_remision" />
                    <x-ui.input label="Fecha de recepción" name="form.fecha_recepcion" type="date" wire:model="form.fecha_recepcion" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Remisión digitalizada (opcional)</label>

                    @if ($documentoRemisionVinculado)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 dark:bg-gray-800/50 p-2 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">Vinculado de SharePoint: {{ $documentoRemisionVinculado['nombre'] }}</span>
                            <button type="button" wire:click="$set('documentoRemisionVinculado', null)" class="text-xs text-red-600 hover:text-red-500 dark:text-red-400">Quitar</button>
                        </div>
                    @else
                        <input wire:model="documentoRemision" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300">
                        <div wire:loading wire:target="documentoRemision" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Subiendo...</div>
                        <button type="button" wire:click="openSharePointBuscar" class="mt-1 text-xs text-primary hover:underline">Buscar en SharePoint</button>
                    @endif

                    @error('documentoRemision')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <x-ui.input label="Observaciones" name="form.observaciones" type="textarea" wire:model="form.observaciones" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Líneas de la solicitud</label>

                    @error('lineas')
                        <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="space-y-3">
                        @foreach ($lineas as $i => $linea)
                            <div wire:key="recepcion-linea-{{ $i }}" class="rounded-md border border-gray-100 dark:border-gray-800 p-3 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $linea['descripcion'] }}
                                        @if (! empty($linea['sic_display']))
                                            <span class="font-normal text-gray-400">·</span>
                                            @if (! empty($linea['sic_id']) || ! empty($linea['ebs_requisition_id']))
                                                <button
                                                    type="button"
                                                    wire:click="openSicDetalle({{ $linea['sic_id'] ?? 0 }}, {{ $linea['ebs_requisition_id'] ?? 0 }})"
                                                    class="font-normal text-primary hover:underline"
                                                    title="Ver el detalle de la SIC"
                                                >SIC {{ $linea['sic_display'] }}</button>
                                            @else
                                                <span class="font-normal text-gray-500 dark:text-gray-400">SIC {{ $linea['sic_display'] }}</span>
                                            @endif
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Solicitado: {{ $linea['cantidad_solicitada'] }} ·
                                        Ya recibido: {{ $linea['cantidad_ya_recibida'] }} ·
                                        Pendiente: {{ $linea['cantidad_pendiente'] }}
                                    </p>
                                </div>

                                @if (! empty($linea['sic_id']))
                                    <p class="text-xs text-info">
                                        Quedará reservada contra {{ $linea['sic_folio'] ? "SIC {$linea['sic_folio']}" : "SIC #{$linea['sic_id']}" }} al recibirse.
                                    </p>
                                @endif

                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Se entrega en: <span class="font-medium">{{ $linea['lugar_nombre'] ?? 'sin lugar de entrega' }}</span>
                                </p>

                                @if ($linea['recibible'] ?? false)
                                    <x-ui.input
                                        label="Cantidad a recibir ahora"
                                        name="lineas.{{ $i }}.cantidad_a_recibir"
                                        type="number"
                                        min="0"
                                        max="{{ $linea['cantidad_pendiente'] }}"
                                        wire:model.live="lineas.{{ $i }}.cantidad_a_recibir"
                                        class="sm:w-56"
                                    />
                                @elseif ((int) $linea['cantidad_pendiente'] > 0)
                                    <p class="text-xs text-amber-600 dark:text-amber-400">
                                        @if (empty($linea['lugar_entrega_id']))
                                            Esta línea no tiene lugar de entrega: no se puede recibir hasta capturarlo en la solicitud.
                                        @elseif ($validadorActual->lugar_entrega_id)
                                            No es de tu sitio: la recibe el técnico de {{ $linea['lugar_nombre'] }}.
                                        @elseif ($lugarRecepcionId === null)
                                            Elige arriba el sitio que recibes para habilitar esta línea.
                                        @else
                                            Se recibe en otra recepción, la de {{ $linea['lugar_nombre'] }}.
                                        @endif
                                    </p>
                                @endif
                                @error('lineas.'.$i.'.cantidad_a_recibir')
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                {{-- El artículo con el que se pidió suele ser genérico (p. ej. "Laptop Ejecutiva"): aquí se elige el REAL que llegó, y de él depende si se da de alta como activo inventariable. --}}
                                @if ((int) $linea['cantidad_a_recibir'] > 0)
                                    @php($elegido = $articulosElegidos->get($linea['articulo_id'] ?? 0))
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Artículo recibido</label>
                                        <div class="mt-1 flex items-center gap-2">
                                            <div class="min-h-9 flex-1 truncate rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:text-gray-100 {{ $errors->has('lineas.'.$i.'.articulo_id') ? 'border-danger!' : '' }}">
                                                @if ($elegido)
                                                    {{ $elegido->codigo }} — {{ $elegido->descripcion }}{{ $elegido->es_inventariable ? ' · inventariable' : '' }}
                                                @else
                                                    <span class="text-gray-400">{{ ! empty($linea['articulo_generico']) ? 'Selecciona el artículo real...' : 'Sin asignar' }}</span>
                                                @endif
                                            </div>
                                            <x-ui.button type="button" variant="secondary" size="sm" wire:click="abrirBuscadorArticulo({{ $i }})">Buscar / cambiar</x-ui.button>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            {{ ! empty($linea['articulo_generico']) ? 'Se pidió con un artículo genérico: elige el artículo real que llegó (solo se ofrecen los del mismo tipo de equipo). Al elegir uno inventariable se piden serie, garantía, etc.' : 'Lo realmente recibido puede diferir de lo solicitado — cámbialo si el proveedor sustituyó el artículo.' }}
                                        </p>
                                        @error('lineas.'.$i.'.articulo_id')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <p class="text-xs {{ $linea['es_activo_inventariable'] ? 'text-success' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $linea['es_activo_inventariable'] ? 'Se dará de alta como activo inventariable (un activo por unidad).' : 'No inventariable: solo se registra la cantidad recibida, sin alta de activos.' }}
                                    </p>
                                @endif

                                @if ($linea['es_activo_inventariable'] && (int) $linea['cantidad_a_recibir'] > 0)
                                    <div class="rounded-md bg-gray-50 dark:bg-gray-800/50 p-3 space-y-2">

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @if (! $linea['articulo_marca_id'])
                                                <x-ui.select label="Marca" name="lineas.{{ $i }}.marca_id" wire:model="lineas.{{ $i }}.marca_id" hint="El artículo no tiene marca definida — captúrala aquí.">
                                                    <option value="">Selecciona...</option>
                                                    @foreach ($marcaOptions as $marca)
                                                        <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            @endif

                                            @if (! $linea['articulo_modelo_id'])
                                                <x-ui.select label="Modelo (opcional)" name="lineas.{{ $i }}.modelo_id" wire:model="lineas.{{ $i }}.modelo_id">
                                                    <option value="">Sin asignar</option>
                                                    @foreach ($modeloOptions as $modelo)
                                                        <option value="{{ $modelo->id }}">{{ $modelo->nombre }}</option>
                                                    @endforeach
                                                </x-ui.select>
                                            @endif
                                        </div>

                                        @if (! $linea['articulo_tipo_equipo_id'])
                                            <x-ui.select label="Tipo de equipo" name="lineas.{{ $i }}.tipo_equipo_id" wire:model="lineas.{{ $i }}.tipo_equipo_id" hint="El artículo no tiene un tipo de equipo asignado — captúralo aquí.">
                                                <option value="">Selecciona...</option>
                                                @foreach ($tipoEquipoOptions as $tipoEquipo)
                                                    <option value="{{ $tipoEquipo->id }}">{{ $tipoEquipo->nombre }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        @endif

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <x-ui.input label="Inicio de garantía (opcional)" name="lineas.{{ $i }}.fecha_inicio_garantia" type="date" wire:model="lineas.{{ $i }}.fecha_inicio_garantia" />
                                            <x-ui.input label="Fin de garantía (opcional)" name="lineas.{{ $i }}.fecha_fin_garantia" type="date" wire:model="lineas.{{ $i }}.fecha_fin_garantia" />
                                        </div>

                                        <div class="space-y-2">
                                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300">Unidades a recibir</p>
                                            @foreach ($linea['unidades'] as $u => $unidad)
                                                <div wire:key="recepcion-linea-{{ $i }}-unidad-{{ $u }}" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    {{-- Captura manual siempre disponible; además, un lector USB (escribe + Enter pasa al siguiente serie) o, en celular, la cámara. --}}
                                                    <div x-data="{ camara: window.camaraDisponible?.() }">
                                                        <x-ui.input
                                                            label="Número de serie"
                                                            name="lineas.{{ $i }}.unidades.{{ $u }}.numero_serie"
                                                            wire:model="lineas.{{ $i }}.unidades.{{ $u }}.numero_serie"
                                                            data-serie
                                                            autocomplete="off"
                                                            x-on:keydown.enter.prevent="enfocarSiguienteSerie($el)"
                                                        />
                                                        <button
                                                            type="button"
                                                            x-show="camara"
                                                            x-cloak
                                                            x-on:click="escanearConCamara({
                                                                titulo: 'Escanear número de serie',
                                                                alAceptar: (valor) => {
                                                                    const campo = $el.parentElement.querySelector('[data-serie]');
                                                                    campo.value = valor;
                                                                    campo.dispatchEvent(new Event('input', { bubbles: true }));
                                                                },
                                                            })"
                                                            class="mt-1 text-xs text-primary hover:underline"
                                                        >
                                                            Escanear con cámara
                                                        </button>
                                                    </div>
                                                    <x-ui.input label="Service tag (opcional)" name="lineas.{{ $i }}.unidades.{{ $u }}.service_tag" wire:model="lineas.{{ $i }}.unidades.{{ $u }}.service_tag" />
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancel">{{ $puedeRecibir ? 'Cancelar' : 'Cerrar' }}</x-ui.button>
                @if ($puedeRecibir)
                    <x-ui.button type="submit">Guardar</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showArticuloModal" title="Buscar artículo recibido" max-width="max-w-xl">
        <div class="space-y-3">
            <input
                wire:model.live.debounce.300ms="articuloSearch"
                type="search"
                placeholder="Buscar por código, descripción, marca o modelo..."
                class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($resultadosArticulos->take(10) as $resultado)
                    <button
                        type="button"
                        wire:key="resultado-articulo-{{ $resultado->id }}"
                        wire:click="elegirArticulo({{ $resultado->id }})"
                        class="flex w-full items-center justify-between gap-3 py-2 text-left text-sm text-gray-700 hover:text-primary dark:text-gray-300"
                    >
                        <span>
                            <span class="font-medium">{{ $resultado->codigo }}</span> — {{ $resultado->descripcion }}
                            @if ($resultado->marca || $resultado->modelo)
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ collect([$resultado->marca?->nombre, $resultado->modelo?->nombre])->filter()->implode(' · ') }}</span>
                            @endif
                        </span>
                        @if ($resultado->es_inventariable)
                            <x-ui.badge color="emerald">Inventariable</x-ui.badge>
                        @endif
                    </button>
                @empty
                    <p class="py-2 text-sm text-gray-500 dark:text-gray-400">Ningún artículo coincide. Solo se ofrecen artículos activos del mismo tipo de equipo que el solicitado.</p>
                @endforelse
            </div>

            @if ($resultadosArticulos->count() > 10)
                <p class="text-xs text-gray-500 dark:text-gray-400">Se muestran 10 artículos: escribe en el buscador para ver otros.</p>
            @endif

            <div class="flex justify-end gap-2">
                @if ($articuloLineaIndex !== null && empty($lineas[$articuloLineaIndex]['articulo_generico']))
                    <x-ui.button type="button" variant="secondary" wire:click="quitarArticulo">Sin artículo</x-ui.button>
                @endif
                <x-ui.button type="button" variant="secondary" wire:click="cerrarBuscadorArticulo">Cancelar</x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal model="showAttachModal" title="Adjuntar remisión">
        <form wire:submit="confirmAttach" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Remisión digitalizada</label>

                @if ($attachDocumentoRemisionVinculado)
                    <div class="flex items-center justify-between rounded-md bg-gray-50 dark:bg-gray-800/50 p-2 text-sm">
                        <span class="text-gray-700 dark:text-gray-300">Vinculado de SharePoint: {{ $attachDocumentoRemisionVinculado['nombre'] }}</span>
                        <button type="button" wire:click="$set('attachDocumentoRemisionVinculado', null)" class="text-xs text-red-600 hover:text-red-500 dark:text-red-400">Quitar</button>
                    </div>
                @else
                    <input wire:model="attachDocumentoRemision" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300">
                    <div wire:loading wire:target="attachDocumentoRemision" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Subiendo...</div>
                    <button type="button" wire:click="openSharePointBuscar('attachDocumentoRemision')" class="mt-1 text-xs text-primary hover:underline">Buscar en SharePoint</button>
                @endif

                @error('attachDocumentoRemision')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelAttach">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal model="showSharePointModal" title="Buscar en SharePoint">
        <div class="space-y-4">
            <input
                wire:model.live.debounce.300ms="sharePointSearch"
                type="search"
                placeholder="Buscar por nombre de archivo..."
                class="w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
            >

            @error('sharePointArchivos')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($sharePointArchivosFiltrados as $archivo)
                    <button
                        type="button"
                        wire:click="elegirArchivoSharePoint('{{ $archivo['driveItemId'] }}')"
                        class="flex w-full items-center justify-between py-2 text-left text-sm text-gray-700 hover:text-primary dark:text-gray-300"
                    >
                        {{ $archivo['nombre'] }}
                    </button>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-2">Sin archivos en esta carpeta.</p>
                @endforelse
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" wire:click="cancelSharePointBuscar">Cancelar</x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.modal
        model="showDetalleModal"
        :title="$detalleEbsRequisicion ? 'Detalle de la requisición '.$detalleEbsRequisicion->code : 'Detalle de la SIC '.($detalleSicLocal?->folio_sic ?: '#'.$detalleSicLocal?->id)"
        max-width="max-w-3xl"
    >
        @if ($detalleEbsRequisicion)
            @include('gestionti::partials.ebs-requisicion-detalle', ['detalle' => $detalleEbsRequisicion, 'estatusColors' => $ebsEstatusColors, 'mostrarLinkSolicitudProveedor' => false])
        @elseif ($detalleSicLocal)
            @include('gestionti::partials.sic-local-detalle', ['detalle' => $detalleSicLocal])
        @endif
    </x-ui.modal>

    <x-ui.help-modal titulo="Recepción de Proveedor" :pdf-url="route('gestionti.ayuda.pdf', 'recepciones')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('recepciones')])
    </x-ui.help-modal>
</div>
