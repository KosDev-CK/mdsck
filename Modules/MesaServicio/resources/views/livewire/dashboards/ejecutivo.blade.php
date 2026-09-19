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
        <span>Periodo: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $resumenPeriodo }}</span></span>
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

    {{-- 1. KPIs principales --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-tile label="Total tickets" :value="$totalTickets" icon="ticket" color="primary" />
        <x-ui.stat-tile label="Completados" :value="$completados" icon="check-circle" color="success" />
        <x-ui.stat-tile
            label="% SLA cumplido"
            :value="$pctSlaCumplido !== null ? $pctSlaCumplido.'%' : 'Sin datos'"
            icon="shield-check"
            :color="$pctSlaCumplido === null ? 'primary' : ($pctSlaCumplido >= $metaSlaPct ? 'success' : 'danger')"
            :hint="'Meta de referencia: '.$metaSlaPct.'%'"
        />
        <x-ui.stat-tile
            label="Tiempo mediano de resolución"
            :value="$medianaResolucionHoras !== null ? number_format($medianaResolucionHoras, 1).' h' : 'Sin datos'"
            icon="clock"
            color="info"
        />
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
            label="Incidentes"
            :value="$incidentes"
            icon="bolt"
            color="danger"
            wire:click="seleccionarTipoSolicitud('Incidente')"
            class="cursor-pointer transition hover:ring-2 hover:ring-danger/40 {{ $tipoSolicitudFiltro === 'Incidente' ? 'ring-2 ring-danger/60' : '' }}"
        />
        <x-ui.stat-tile
            label="Solicitudes"
            :value="$solicitudes"
            icon="inbox-stack"
            color="info"
            wire:click="seleccionarTipoSolicitud('Solicitud')"
            class="cursor-pointer transition hover:ring-2 hover:ring-info/40 {{ $tipoSolicitudFiltro === 'Solicitud' ? 'ring-2 ring-info/60' : '' }}"
        />
        <x-ui.stat-tile
            label="Requerimientos"
            :value="$requerimientos"
            icon="squares-plus"
            color="primary"
            wire:click="seleccionarTipoSolicitud('Requerimiento')"
            class="cursor-pointer transition hover:ring-2 hover:ring-primary/40 {{ $tipoSolicitudFiltro === 'Requerimiento' ? 'ring-2 ring-primary/60' : '' }}"
        />
        <x-ui.stat-tile
            label="Tickets combinados"
            :value="$combinados"
            icon="document-duplicate"
            color="warning"
            hint="Fusionados a otro folio en SDP"
        />
    </div>

    {{-- 3. Tendencia mensual --}}
    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Tendencia mensual de tickets creados</h2>

        <div
            wire:key="chart-tendencia-{{ $periodoKey }}"
            x-data="{
                chart: null,
                async init() { this.chart = await window.initChart(this.$el, @js($tendenciaOption)); },
                destroy() { this.chart?.dispose(); },
            }"
            class="h-72"
        ></div>
    </x-ui.card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- 4. Dona de categorías --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Distribución por categoría</h2>

            <div
                wire:key="chart-categoria-{{ $periodoKey }}"
                x-data="{
                    chart: null,
                    async init() {
                        this.chart = await window.initChart(this.$el, @js($categoriaOption));
                        this.chart.onClick((params) => $wire.seleccionarCategoria(params.name));
                    },
                    destroy() { this.chart?.dispose(); },
                }"
                class="h-72"
            ></div>
        </x-ui.card>

        {{-- 5. Cumplimiento de SLA por mes --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Cumplimiento de SLA por mes</h2>

            <div
                wire:key="chart-sla-mes-{{ $periodoKey }}"
                x-data="{
                    chart: null,
                    async init() { this.chart = await window.initChart(this.$el, @js($slaPorMesOption)); },
                    destroy() { this.chart?.dispose(); },
                }"
                class="h-72"
            ></div>
        </x-ui.card>

        {{-- 6. Áreas con mayor demanda --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Áreas con mayor demanda</h2>

            <div
                wire:key="chart-departamento-{{ $periodoKey }}"
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

        {{-- 7. Tipo de solicitud por mes --}}
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Tipo de solicitud por mes</h2>

            <div
                wire:key="chart-tipo-mes-{{ $periodoKey }}"
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

    {{-- 8. Distribución por nivel de atención --}}
    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Distribución por nivel de atención</h2>

        <x-ui.table :headers="['Nivel', 'Tickets', '%']" :empty="$nivelDistribucion->isEmpty()" empty-title="Sin tickets en el rango seleccionado">
            @foreach ($nivelDistribucion as $fila)
                <tr wire:key="nivel-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $fila['etiqueta'] }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $fila['total'] }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $fila['pct'] }}%</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    {{-- 9. Categorías por mes (heatmap) --}}
    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Categorías por mes</h2>

        @if (empty($heatmap['filas']))
            <x-ui.empty-state title="Sin tickets en el rango seleccionado" />
        @else
            <x-ui.table :headers="array_merge(['Categoría'], $heatmap['meses'])">
                @foreach ($heatmap['filas'] as $fila)
                    <tr wire:key="heatmap-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800">
                        <td class="py-2 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $fila['etiqueta'] }}</td>
                        @foreach ($fila['valores'] as $valor)
                            @php
                                $intensidad = $fila['max'] > 0 ? $valor / $fila['max'] : 0;
                                $clase = match (true) {
                                    $valor === 0 => 'text-gray-300 dark:text-gray-600',
                                    $intensidad >= 0.75 => 'bg-primary/40 font-semibold text-gray-900 dark:text-gray-100',
                                    $intensidad >= 0.5 => 'bg-primary/25 text-gray-900 dark:text-gray-100',
                                    $intensidad >= 0.25 => 'bg-primary/15 text-gray-700 dark:text-gray-200',
                                    default => 'bg-primary/10 text-gray-600 dark:text-gray-300',
                                };
                            @endphp
                            <td class="py-2 text-center text-sm {{ $clase }}">{{ $valor }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </x-ui.table>
        @endif
    </x-ui.card>

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
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($hallazgos as $hallazgo)
                    <div wire:key="hallazgo-{{ $loop->index }}" class="flex gap-3 rounded-lg border border-gray-100 p-4 dark:border-gray-800">
                        <x-heroicon-o-light-bulb class="h-5 w-5 shrink-0 text-primary" />
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
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Resumen mensual</h2>

        <x-ui.table
            :headers="['Mes', 'Total', 'Completados', '% SLA', 'Mediana resolución']"
            :empty="$resumenMensual->isEmpty()"
            empty-title="Sin tickets en el rango seleccionado"
        >
            @foreach ($resumenMensual as $fila)
                @php
                    $celda = $fila['esTotal']
                        ? 'py-2 font-semibold text-gray-900 dark:text-gray-100'
                        : 'py-2 text-gray-500 dark:text-gray-400';
                @endphp
                <tr wire:key="resumen-mes-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="{{ $fila['esTotal'] ? $celda : 'py-2 font-medium text-gray-900 dark:text-gray-100' }}">{{ $fila['etiqueta'] }}</td>
                    <td class="{{ $celda }}">{{ $fila['total'] }}</td>
                    <td class="{{ $celda }}">{{ $fila['completados'] }}</td>
                    <td class="{{ $celda }}">
                        {{ $fila['pctSla'] !== null ? $fila['pctSla'].'%' : 'Sin datos' }}
                    </td>
                    <td class="{{ $celda }}">
                        {{ $fila['medianaHoras'] !== null ? number_format($fila['medianaHoras'], 1).' h' : 'Sin datos' }}
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.help-modal titulo="Dashboard Ejecutivo" :pdf-url="route('mesaservicio.ayuda.pdf', 'dashboard-ejecutivo')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('dashboard-ejecutivo')])
    </x-ui.help-modal>
</div>
