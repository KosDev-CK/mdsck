<div class="space-y-6">
    @push('page-title')
        Dashboard Ejecutivo
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

    {{-- Resumen del periodo activo (siempre visible, aunque el panel esté cerrado) + botón para abrirlo. --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('open-filtros-periodo'))"
            class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
            title="Filtrar periodo"
        >
            <x-heroicon-o-funnel class="h-4 w-4" />
        </button>

        <x-heroicon-o-calendar-days class="h-4 w-4 shrink-0" />
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $periodoFiltroTexto }}</span>
    </div>

    {{--
        Chips de cross-filtering activo (clics en gráficas/KPIs, ver
        seleccionarCategoria()/seleccionarDepartamento()/seleccionarTipoSolicitud()
        en la clase) — se acumulan encima del periodo de arriba, no lo
        reemplazan, y son independientes de él (no se resetean al cambiar
        de periodo). El "×" de cada chip vuelve a llamar al mismo método
        `seleccionar*` con el valor legible original, que hace toggle y lo
        quita.
    --}}
    @if (! empty($filtrosActivos))
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($filtrosActivos as $filtro)
                <x-ui.badge color="indigo" wire:key="filtro-activo-{{ $loop->index }}">
                    <span class="flex items-center gap-1.5">
                        {{ $filtro['etiqueta'] }}
                        <button
                            type="button"
                            wire:click="{{ $filtro['metodo'] }}(@js($filtro['valor']))"
                            class="text-primary/70 hover:text-primary"
                            title="Quitar filtro"
                        >
                            <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                        </button>
                    </span>
                </x-ui.badge>
            @endforeach

            <button
                type="button"
                wire:click="limpiarFiltrosSeleccion"
                class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                Limpiar filtros
            </button>
        </div>
    @endif

    <x-ui.slide-over title="Filtrar periodo" event="open-filtros-periodo" close-event="close-filtros-periodo">
        {{--
            Los 4 controles conviven siempre visibles, sin pestañas ni modos
            mutuamente excluyentes (decisión explícita del usuario, ver
            docblock de la clase) — Año/Mes son atajos que rellenan
            Desde/Hasta de inmediato (updatedEjercicio()/updatedMes()),
            Desde/Hasta siguen siendo editables a mano y son lo único que
            realmente usa la consulta.
        --}}
        <div class="grid grid-cols-2 gap-3">
            <x-ui.select label="Año" name="ejercicio" wire:model.live="ejercicio">
                @foreach ($aniosDisponibles as $anio)
                    <option value="{{ $anio }}">{{ $anio }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select label="Mes" name="mes" wire:model.live="mes">
                <option value="">Todo el año</option>
                @foreach ($mesesDelAnio as $numero => $nombre)
                    <option value="{{ $numero }}">{{ $nombre }}</option>
                @endforeach
            </x-ui.select>
        </div>

        <div class="space-y-3">
            <x-ui.input label="Desde" name="desde" type="date" wire:model="desde" />
            <x-ui.input label="Hasta" name="hasta" type="date" wire:model="hasta" />
        </div>

        <div class="border-t border-gray-100 pt-4 dark:border-gray-800">
            <x-ui.button type="button" wire:click="aplicarFiltro" class="w-full justify-center">
                Aplicar
            </x-ui.button>
        </div>
    </x-ui.slide-over>

    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Indicadores clave de desempeño
    </div>

    {{-- 1. KPIs principales --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-tile
            variant="accent"
            label="Total de tickets"
            :value="number_format($totalTickets)"
            color="primary"
            :hint="$periodoFiltroTexto"
        >
            @if ($tendenciaTotalTickets !== null)
                <x-ui.badge color="indigo">
                    {{ $tendenciaTotalTickets['pct'] >= 0 ? '↑' : '↓' }}
                    {{ number_format(abs($tendenciaTotalTickets['pct']), 1) }}% vs primer mes del periodo
                </x-ui.badge>
            @endif
        </x-ui.stat-tile>
        <x-ui.stat-tile
            variant="accent"
            label="Tickets completados"
            :value="number_format($completados)"
            color="success"
            :hint="$totalTickets > 0 ? number_format($completados / $totalTickets * 100, 1).'% tasa de cierre' : 'Sin datos'"
        >
            @if ($totalTickets > 0)
                @php $tasaCierre = $completados / $totalTickets * 100; @endphp
                @if ($tasaCierre >= 90)
                    <x-ui.badge color="emerald">✓ Alta efectividad</x-ui.badge>
                @elseif ($tasaCierre < 70)
                    <x-ui.badge color="red">↓ Tasa de cierre por mejorar</x-ui.badge>
                @endif
            @endif
        </x-ui.stat-tile>
        <x-ui.stat-tile
            variant="accent"
            label="SLA vencido"
            :value="number_format($ticketsSlaVencidos)"
            color="danger"
            :hint="$totalTickets > 0 ? number_format($ticketsSlaVencidos / $totalTickets * 100, 1).'% del total' : 'Sin datos'"
        >
            @if ($pctSlaCumplido !== null)
                <x-ui.badge :color="$pctSlaCumplido >= $metaSlaPct ? 'emerald' : 'red'">
                    {{ $pctSlaCumplido >= $metaSlaPct ? '✓ Cumple la meta' : '↓ Por debajo de la meta' }}
                </x-ui.badge>
            @endif
        </x-ui.stat-tile>
        <x-ui.stat-tile
            variant="accent"
            label="Tiempo mediano de resolución"
            :value="$medianaResolucionHoras !== null ? number_format($medianaResolucionHoras, 1).' h' : 'Sin datos'"
            color="warning"
            hint="Mediana del periodo seleccionado"
        >
            @if ($tendenciaTiempoResolucion !== null)
                <x-ui.badge :color="$tendenciaTiempoResolucion['pct'] <= 0 ? 'emerald' : 'red'">
                    {{ $tendenciaTiempoResolucion['pct'] <= 0 ? '↓' : '↑' }}
                    {{ number_format(abs($tendenciaTiempoResolucion['pct']), 1) }}%
                    {{ $tendenciaTiempoResolucion['pct'] <= 0 ? 'más rápido' : 'más lento' }} vs primer mes
                </x-ui.badge>
            @endif
        </x-ui.stat-tile>
    </div>

    {{--
        2. KPIs secundarios — Incidentes/Solicitudes/Requerimientos son
        clicables: mismo cross-filtering que la gráfica de tipo de
        solicitud por mes (seleccionarTipoSolicitud()), con el mismo
        toggle (clic de nuevo sobre el ya activo lo quita). Tickets
        combinados no participa del cross-filtering (no es una dimensión
        filtrable en esta iteración).
    --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-tile
            variant="accent"
            label="Incidentes"
            :value="number_format($incidentes)"
            color="danger"
            :hint="$totalTickets > 0 ? number_format($incidentes / $totalTickets * 100, 1).'% del total' : 'Sin datos'"
            wire:click="seleccionarTipoSolicitud('Incidente')"
            class="cursor-pointer transition hover:ring-2 hover:ring-danger/40 {{ $tipoSolicitudFiltro === 'Incidente' ? 'ring-2 ring-danger/60' : '' }}"
        />
        <x-ui.stat-tile
            variant="accent"
            label="Solicitudes"
            :value="number_format($solicitudes)"
            color="info"
            :hint="$totalTickets > 0 ? number_format($solicitudes / $totalTickets * 100, 1).'% del total' : 'Sin datos'"
            wire:click="seleccionarTipoSolicitud('Solicitud')"
            class="cursor-pointer transition hover:ring-2 hover:ring-info/40 {{ $tipoSolicitudFiltro === 'Solicitud' ? 'ring-2 ring-info/60' : '' }}"
        />
        <x-ui.stat-tile
            variant="accent"
            label="Requerimientos"
            :value="number_format($requerimientos)"
            color="primary"
            :hint="$totalTickets > 0 ? number_format($requerimientos / $totalTickets * 100, 1).'% del total' : 'Sin datos'"
            wire:click="seleccionarTipoSolicitud('Requerimiento')"
            class="cursor-pointer transition hover:ring-2 hover:ring-primary/40 {{ $tipoSolicitudFiltro === 'Requerimiento' ? 'ring-2 ring-primary/60' : '' }}"
        />
        <x-ui.stat-tile
            variant="accent"
            label="Tickets combinados"
            :value="number_format($combinados)"
            color="warning"
            hint="Fusionados a otro folio en SDP"
        />
    </div>

    {{--
        3. Tendencia mensual — `wire:ignore` en las 5 gráficas de esta pantalla
        es OBLIGATORIO, no cosmético: ECharts inyecta su `<canvas>` por JS,
        fuera de la plantilla Blade (que siempre renderiza el div vacío). Sin
        `wire:ignore`, cualquier respuesta de Livewire que NO cambie el
        `wire:key` de una gráfica (ej. "Aplicar", que solo despacha un evento
        de cierre) igual hace un morph de sus hijos contra el HTML del
        servidor — que está vacío — y borra el canvas ya dibujado sin volver
        a correr `x-init` (el `wire:key` no cambió, Livewire lo trata como el
        mismo elemento). `wire:ignore` le dice a Livewire que nunca toque el
        contenido de este nodo en un patch; cuando el `wire:key` SÍ cambia
        (nuevos datos), Livewire igual reemplaza el nodo completo —
        `wire:ignore` no protege contra eso, así que la reinicialización con
        datos nuevos sigue funcionando igual. Encontrado en producción
        (2026-09-19): reproducido siempre que se usa el botón "Aplicar" del
        panel de filtros, la variante inversa del bug de los toasts de esta
        misma sesión (ahí el problema era que Livewire NO reemplazaba el
        nodo cuando debía; aquí es que SÍ lo parchea cuando no debía).
    --}}
    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Volumen y distribución
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        {{-- 3. Tendencia mensual — el doble de ancha que sus vecinas (lg:col-span-2 de 4), el resto de la fila igual de dividida entre categoría y SLA. --}}
        <x-ui.card padding="p-5" class="lg:col-span-2">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Evolución de tickets {{ $granularidadTexto }}</h2>

            <div
                wire:key="chart-tendencia-{{ $periodoKey }}"
                wire:ignore
                x-data="{
                    chart: null,
                    async init() { this.chart = await window.initChart(this.$el, @js($tendenciaOption)); },
                    destroy() { this.chart?.dispose(); },
                }"
                class="h-72"
            ></div>
        </x-ui.card>

        {{--
            4. Dona de categorías — sin leyenda propia de ECharts (removida
            en categoriaOption(), ver Ejecutivo.php): el total va al centro
            de la dona vía el `title` de ECharts, y la participación de cada
            categoría se lee en la tabla de al lado, no en callouts
            alrededor del donut ni en una leyenda aparte.
        --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Categorías principales</h2>

            <div class="flex items-center gap-4">
                <div
                    wire:key="chart-categoria-{{ $periodoKey }}"
                    wire:ignore
                    x-data="{
                        chart: null,
                        async init() {
                            this.chart = await window.initChart(this.$el, @js($categoriaOption));
                            this.chart.onClick((params) => $wire.seleccionarCategoria(params.name));
                        },
                        destroy() { this.chart?.dispose(); },
                    }"
                    class="h-32 w-32 shrink-0"
                ></div>

                @php
                    // Paleta CATEGÓRICA de 15 tonos (ver --color-chart-1..15
                    // en app.css) — nunca success/warning/danger: una
                    // categoría no tiene un juicio de bien/mal que comunicar
                    // con su color. Se escribe como array literal (no
                    // generado con un loop) a propósito: Tailwind escanea el
                    // código fuente buscando literales de clase, "bg-chart-
                    // {$n}" interpolado nunca generaría la utilidad.
                    $puntoColor = [
                        0 => 'bg-chart-1', 1 => 'bg-chart-2', 2 => 'bg-chart-3', 3 => 'bg-chart-4', 4 => 'bg-chart-5',
                        5 => 'bg-chart-6', 6 => 'bg-chart-7', 7 => 'bg-chart-8', 8 => 'bg-chart-9', 9 => 'bg-chart-10',
                        10 => 'bg-chart-11', 11 => 'bg-chart-12', 12 => 'bg-chart-13', 13 => 'bg-chart-14', 14 => 'bg-chart-15',
                    ];
                @endphp
                <table class="w-full min-w-0 text-sm">
                    <tbody>
                        @foreach ($categoriaTabla as $fila)
                            <tr wire:key="categoria-tabla-{{ $loop->index }}" class="cursor-pointer" wire:click="seleccionarCategoria(@js($fila['etiqueta']))">
                                <td class="w-2.5 py-1 pr-2">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full {{ $puntoColor[$fila['colorIndex']] }}"></span>
                                </td>
                                <td class="truncate py-1 pr-2 text-gray-700 dark:text-gray-300">{{ $fila['etiqueta'] }}</td>
                                <td class="py-1 pr-2 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($fila['total']) }}</td>
                                <td class="py-1 text-right text-gray-400 dark:text-gray-500">{{ $fila['pct'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        {{-- 5. Cumplimiento de SLA por mes --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Cumplimiento de SLA {{ $granularidadTexto }}</h2>

            <div
                wire:key="chart-sla-mes-{{ $periodoKey }}"
                wire:ignore
                x-data="{
                    chart: null,
                    async init() { this.chart = await window.initChart(this.$el, @js($slaPorMesOption)); },
                    destroy() { this.chart?.dispose(); },
                }"
                class="h-56"
            ></div>

            <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                <span class="text-xs text-gray-500 dark:text-gray-400">Promedio del periodo</span>
                <span class="text-lg font-bold text-warning">
                    {{ $pctSlaCumplido !== null ? $pctSlaCumplido.'%' : 'Sin datos' }}
                </span>
            </div>
            @if ($pctSlaCumplido !== null && $pctSlaCumplido < $metaSlaPct)
                <p class="mt-1 text-xs text-danger">
                    ⚠ Meta corporativa: {{ $metaSlaPct }}% · Brecha: {{ number_format($pctSlaCumplido - $metaSlaPct, 1) }}pp
                </p>
            @endif
        </x-ui.card>
    </div>

    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Desglose operativo
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- 6. Áreas con mayor demanda --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Áreas con mayor demanda</h2>

            <div
                wire:key="chart-departamento-{{ $periodoKey }}"
                wire:ignore
                x-data="{
                    chart: null,
                    async init() {
                        this.chart = await window.initChart(this.$el, @js($departamentoOption));
                        this.chart.onClick((params) => $wire.seleccionarDepartamento(params.name));
                    },
                    destroy() { this.chart?.dispose(); },
                }"
                class="h-72"
            ></div>
        </x-ui.card>

        {{-- 7. Tipo de solicitud por periodo --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Tipo de solicitud {{ $granularidadTexto }}</h2>

            <div
                wire:key="chart-tipo-mes-{{ $periodoKey }}"
                wire:ignore
                x-data="{
                    chart: null,
                    async init() {
                        this.chart = await window.initChart(this.$el, @js($tipoPorMesOption));
                        {{--
                            El clic selecciona el TIPO (nombre de la serie
                            apilada: Solicitud/Incidente/Requerimiento), no
                            el mes (params.name sería el mes clickeado) — el
                            periodo lo sigue controlando el filtro de fecha,
                            no este clic.
                        --}}
                        this.chart.onClick((params) => $wire.seleccionarTipoSolicitud(params.seriesName));
                    },
                    destroy() { this.chart?.dispose(); },
                }"
                class="h-72"
            ></div>
        </x-ui.card>
    </div>

    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        Escalamiento y detalle por categoría
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- 8. Distribución por nivel de atención --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Distribución por nivel de atención</h2>

            <x-ui.table :headers="['Nivel', 'Tickets', 'Proporción', '%']" :empty="$nivelDistribucion->isEmpty()" empty-title="Sin tickets en el rango seleccionado">
                @foreach ($nivelDistribucion as $fila)
                    <tr wire:key="nivel-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800">
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $fila['etiqueta'] }}</td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $fila['total'] }}</td>
                        <td class="py-2">
                            <div class="h-2 w-full max-w-32 rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-2 rounded-full bg-primary" style="width: {{ $fila['pct'] }}%"></div>
                            </div>
                        </td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $fila['pct'] }}%</td>
                    </tr>
                @endforeach
            </x-ui.table>

            @if ($fortalezaOperativaNivel !== null)
                <x-ui.alert variant="success" class="mt-4">
                    <p class="font-semibold">✓ Fortaleza operativa</p>
                    <p class="mt-0.5">
                        {{ $fortalezaOperativaNivel['pct'] }}% de los tickets con nivel asignado se resolvieron sin
                        necesidad de escalar a un grupo especialista o proveedor externo.
                    </p>
                </x-ui.alert>
            @endif
        </x-ui.card>

        {{-- 9. Categorías por periodo (heatmap) --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Categorías {{ $granularidadTexto }}</h2>

            @if (empty($heatmap['filas']))
                <x-ui.empty-state title="Sin tickets en el rango seleccionado" />
            @else
                @php
                    // Un tono CATEGÓRICO por FILA (categoría), cíclico —
                    // dentro de una fila la intensidad va de más clara
                    // (valor bajo) a más fuerte (valor alto) del MISMO
                    // tono, nunca mezclando dos colores en una misma fila.
                    // Nunca success/warning/danger: ver el comentario junto
                    // a TOKENS_COLOR_CATEGORIA en Ejecutivo.php.
                    // [0]=más fuerte (texto blanco) ... [3]=más clara.
                    $tonosPorIndice = [
                        0 => ['bg-chart-1', 'bg-chart-1/50', 'bg-chart-1/25', 'bg-chart-1/10'],
                        1 => ['bg-chart-2', 'bg-chart-2/50', 'bg-chart-2/25', 'bg-chart-2/10'],
                        2 => ['bg-chart-3', 'bg-chart-3/50', 'bg-chart-3/25', 'bg-chart-3/10'],
                        3 => ['bg-chart-4', 'bg-chart-4/50', 'bg-chart-4/25', 'bg-chart-4/10'],
                        4 => ['bg-chart-5', 'bg-chart-5/50', 'bg-chart-5/25', 'bg-chart-5/10'],
                        5 => ['bg-chart-6', 'bg-chart-6/50', 'bg-chart-6/25', 'bg-chart-6/10'],
                        6 => ['bg-chart-7', 'bg-chart-7/50', 'bg-chart-7/25', 'bg-chart-7/10'],
                        7 => ['bg-chart-8', 'bg-chart-8/50', 'bg-chart-8/25', 'bg-chart-8/10'],
                        8 => ['bg-chart-9', 'bg-chart-9/50', 'bg-chart-9/25', 'bg-chart-9/10'],
                        9 => ['bg-chart-10', 'bg-chart-10/50', 'bg-chart-10/25', 'bg-chart-10/10'],
                        10 => ['bg-chart-11', 'bg-chart-11/50', 'bg-chart-11/25', 'bg-chart-11/10'],
                        11 => ['bg-chart-12', 'bg-chart-12/50', 'bg-chart-12/25', 'bg-chart-12/10'],
                        12 => ['bg-chart-13', 'bg-chart-13/50', 'bg-chart-13/25', 'bg-chart-13/10'],
                        13 => ['bg-chart-14', 'bg-chart-14/50', 'bg-chart-14/25', 'bg-chart-14/10'],
                        14 => ['bg-chart-15', 'bg-chart-15/50', 'bg-chart-15/25', 'bg-chart-15/10'],
                    ];
                @endphp
                <x-ui.table :headers="array_merge(['Categoría'], $heatmap['meses'])">
                    @foreach ($heatmap['filas'] as $fila)
                        @php $tonos = $tonosPorIndice[$fila['colorIndex']]; @endphp
                        <tr wire:key="heatmap-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800">
                            <td class="py-2 pr-3 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $fila['etiqueta'] }}</td>
                            @foreach ($fila['valores'] as $valor)
                                @php
                                    $intensidad = $fila['max'] > 0 ? $valor / $fila['max'] : 0;
                                    [$fondo, $texto] = match (true) {
                                        $valor === 0 => ['', 'text-gray-300 dark:text-gray-600'],
                                        $intensidad >= 0.75 => [$tonos[0], 'text-white font-semibold'],
                                        $intensidad >= 0.5 => [$tonos[1], 'text-white'],
                                        $intensidad >= 0.25 => [$tonos[2], 'text-gray-900 dark:text-gray-100'],
                                        default => [$tonos[3], 'text-gray-700 dark:text-gray-300'],
                                    };
                                @endphp
                                <td class="p-1 text-center text-sm">
                                    <div class="rounded-md px-2 py-1.5 {{ $fondo }} {{ $texto }}">{{ $valor }}</div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </x-ui.table>

                <div class="mt-3 flex items-center gap-4 text-xs text-gray-400 dark:text-gray-500">
                    <span>Tonalidad:</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-chart-1/10"></span>Bajo</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-chart-1/50"></span>Medio</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-chart-1"></span>Alto</span>
                </div>
            @endif
        </x-ui.card>
    </div>

    {{-- 10. Hallazgos estratégicos --}}
    <x-ui.card padding="p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Hallazgos estratégicos</h2>
            <span class="text-xs text-gray-400 dark:text-gray-500">Detección por reglas simples, no machine learning</span>
        </div>

        @if (empty($hallazgos))
            <x-ui.empty-state
                icon="light-bulb"
                title="Sin hallazgos para el rango seleccionado"
                description="Amplía el rango de fechas (se necesitan al menos 2 meses con datos para algunos hallazgos)."
            />
        @else
            @php
                // Mismo vocabulario de color que x-ui.stat-tile/x-ui.badge —
                // cada hallazgo trae su propio icono/color fijo por REGLA
                // (ver hallazgos() en Ejecutivo.php) para que la sección se
                // lea con variedad temática en vez de un lightbulb repetido.
                $iconoColores = [
                    'primary' => 'bg-primary/10 text-primary',
                    'success' => 'bg-success/10 text-success',
                    'danger' => 'bg-danger/10 text-danger',
                    'warning' => 'bg-warning/10 text-warning',
                    'info' => 'bg-info/10 text-info',
                ];
            @endphp
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($hallazgos as $hallazgo)
                    <div wire:key="hallazgo-{{ $loop->index }}" class="flex gap-3 rounded-lg border border-gray-100 p-4 dark:border-gray-800">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $iconoColores[$hallazgo['color']] ?? $iconoColores['primary'] }}">
                            <x-dynamic-component :component="'heroicon-o-'.$hallazgo['icono']" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $hallazgo['titulo'] }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $hallazgo['texto'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    {{-- 11. Resumen mensual --}}
    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Resumen {{ $granularidadTexto }}</h2>

        <x-ui.table
            :headers="[ucfirst(str_replace('por ', '', $granularidadTexto)), 'Tickets', 'Completados', 'Vencidos', '% SLA', 'T. mediano res.', 'Incidentes', 'Solicitudes']"
            :empty="$resumenMensual->isEmpty()"
            empty-title="Sin tickets en el rango seleccionado"
        >
            @foreach ($resumenMensual as $fila)
                @php
                    $negrita = $fila['esTotal'] ? 'font-semibold' : '';
                    $slaColor = match (true) {
                        $fila['pctSla'] === null => 'text-gray-400 dark:text-gray-500',
                        $fila['pctSla'] >= $metaSlaPct => 'bg-success/10 text-success',
                        $fila['pctSla'] >= $metaSlaPct - 10 => 'bg-warning/10 text-warning',
                        default => 'bg-danger/10 text-danger',
                    };
                @endphp
                <tr
                    wire:key="resumen-mes-{{ $loop->index }}"
                    class="border-b border-gray-50 dark:border-gray-800 {{ $fila['esTotal'] ? 'bg-primary/5 dark:bg-primary/10' : '' }}"
                >
                    <td class="py-2 {{ $negrita }} text-gray-900 dark:text-gray-100">{{ $fila['etiqueta'] }}</td>
                    <td class="py-2 {{ $negrita }} {{ $fila['esTotal'] ? 'text-primary' : 'text-gray-700 dark:text-gray-300' }}">{{ number_format($fila['total']) }}</td>
                    <td class="py-2 {{ $negrita }} text-success">{{ number_format($fila['completados']) }}</td>
                    <td class="py-2 {{ $negrita }} text-danger">{{ number_format($fila['vencidos']) }}</td>
                    <td class="py-2 {{ $negrita }}">
                        @if ($fila['pctSla'] !== null)
                            <span class="inline-block rounded-full px-2 py-0.5 {{ $slaColor }}">{{ $fila['pctSla'] }}%</span>
                        @else
                            <span class="text-gray-400 dark:text-gray-500">Sin datos</span>
                        @endif
                    </td>
                    <td class="py-2 {{ $negrita }} text-gray-700 dark:text-gray-300">
                        {{ $fila['medianaHoras'] !== null ? number_format($fila['medianaHoras'], 1).' h' : 'Sin datos' }}{{ $fila['medianaPocoConfiable'] ? '*' : '' }}
                    </td>
                    <td class="py-2 {{ $negrita }} text-danger">{{ number_format($fila['incidentes']) }}</td>
                    <td class="py-2 {{ $negrita }} text-info">{{ number_format($fila['solicitudes']) }}</td>
                </tr>
            @endforeach
        </x-ui.table>

        @if (! empty($resumenMensualNotas))
            <div class="mt-2 space-y-0.5">
                @foreach ($resumenMensualNotas as $nota)
                    <p wire:key="resumen-nota-{{ $loop->index }}" class="text-xs text-gray-400 dark:text-gray-500">* {{ $nota }}</p>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    <x-ui.help-modal titulo="Dashboard Ejecutivo" :pdf-url="route('mesaservicio.ayuda.pdf', 'dashboard-ejecutivo')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('dashboard-ejecutivo')])
    </x-ui.help-modal>
</div>
