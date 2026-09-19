<?php

namespace Modules\MesaServicio\Livewire\Dashboards;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\MesaServicio\Models\SdpSlaDefinition;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;

/**
 * Dashboard Ejecutivo — pensado para dirección: resumen de alto nivel,
 * KPIs, tendencias y hallazgos (el "qué" resumido, sin el detalle operativo
 * del día a día que sí muestra el Dashboard de Operación,
 * `Modules\MesaServicio\Livewire\Dashboard`). Inspirado en un mockup
 * estático de referencia compartido por el usuario, adaptado a los campos
 * reales de `sdp_tickets`.
 *
 * Criterio de inclusión de tickets "combinados" (fusionados a otro folio
 * por SDP, `combinado_con_display_id` no nulo): se sigue el mismo
 * precedente ya establecido por `Slas::ticketsEnRango()` — NINGÚN conteo
 * general de esta pantalla los excluye (Total, Completados, categoría,
 * departamento, tendencia mensual, tipo de solicitud, nivel), porque sí
 * representaron trabajo real. Solo se excluyen de los cálculos DERIVADOS de
 * SLA/tiempo de resolución (ver `ticketsEnRangoExcluyendoCombinados()`),
 * igual que en la pantalla de Cumplimiento de SLA.
 *
 * Todas las consultas de agrupación por mes usan `SUBSTR(created_time, 1, 7)`
 * en vez de `YEAR()`/`MONTH()` (funciones nativas de MySQL, la base de
 * producción) a propósito: la suite de pruebas corre contra SQLite en
 * memoria (ver phpunit.xml), que no tiene `YEAR()`/`MONTH()` pero sí
 * soporta `SUBSTR()` — mismo espíritu portátil que ya usa
 * `PatronesDetector` con `DATE(created_time)`. El resultado ('YYYY-MM') es
 * directamente ordenable como texto y se traduce a una etiqueta legible con
 * `etiquetaPeriodo()`.
 */
#[Layout('layouts.app')]
class Ejecutivo extends Component
{
    /**
     * Meta de referencia para el % de cumplimiento de SLA mostrada como
     * línea en la gráfica de barras por mes — valor tomado tal cual del
     * mockup original compartido por el usuario. No existe ninguna fuente
     * de "meta oficial" en el sistema hoy; es un valor de referencia,
     * ajustable a futuro si dirección define un objetivo distinto.
     */
    public const META_SLA_PCT = 80.0;

    /** Categorías individuales mostradas en la dona antes de agrupar el resto en "Otras". */
    private const TOP_CATEGORIAS = 8;

    /** Departamentos individuales mostrados en la barra horizontal de demanda. */
    private const TOP_DEPARTAMENTOS = 8;

    /** Categorías individuales mostradas como filas del heatmap antes de agrupar el resto en "Otras". */
    private const TOP_CATEGORIAS_HEATMAP = 8;

    /**
     * Centinela usado para distinguir "el usuario hizo clic en el valor
     * NULL real" (`categoria`/`departamento` son nullable en `sdp_tickets`,
     * mostrados como "Sin categoría"/"Sin departamento" agregando los NULL)
     * de "sin filtro activo" (`null` en las propiedades de abajo). No puede
     * colisionar con un valor real de esas columnas.
     */
    private const SIN_DATO = '__sin_dato__';

    /**
     * `$desde`/`$hasta` son el filtro real (único usado por
     * `ticketsEnRango()`) — `$ejercicio`/`$mes` son atajos de UI que solo
     * rellenan esos dos campos al cambiar (ver `aplicarSelectorRapido()`),
     * no un modo aparte: los 4 controles conviven siempre visibles en el
     * panel lateral (ver `slide-over.blade.php`), sin pestañas ni ocultar
     * unos u otros — decisión explícita del usuario tras una primera
     * versión con 3 modos mutuamente excluyentes que resultó confusa.
     */
    public string $desde = '';

    public string $hasta = '';

    /**
     * 1 a 12, o `null` = "Todo el año" (atajo hacia el ejercicio completo
     * de `$ejercicio`). Cambiar este selector recalcula `$desde`/`$hasta`
     * de inmediato (ver `updatedMes()`); el usuario puede seguir ajustando
     * `$desde`/`$hasta` a mano después, ya que son los campos que
     * realmente se usan.
     */
    public ?int $mes = null;

    /** Año calendario (enero a diciembre — mdsLandIT no maneja año fiscal distinto). */
    public int $ejercicio = 2000;

    /**
     * Filtros de cross-filtering (estilo Power BI) disparados al hacer clic
     * en un elemento de una gráfica — se ACUMULAN sobre el filtro de periodo
     * (`$desde`/`$hasta`), no lo reemplazan, y son independientes entre sí
     * (AND). `null` = sin filtro activo en esa dimensión;
     * {@see self::SIN_DATO} = filtrar explícitamente por el valor NULL de
     * la columna. No se resetean al cambiar el periodo — solo
     * `limpiarFiltrosSeleccion()` los limpia.
     */
    public ?string $categoriaFiltro = null;

    public ?string $departamentoFiltro = null;

    public ?string $tipoSolicitudFiltro = null;

    /**
     * Cache en memoria (una sola vez por request, no persiste entre
     * requests — es una propiedad privada, Livewire solo hidrata las
     * públicas) de las definiciones de SLA activas. Ver
     * `resolverSlaDefinicion()`.
     */
    private ?Collection $slaDefinicionesActivas = null;

    public function mount(): void
    {
        $this->desde = now()->startOfYear()->toDateString();
        $this->hasta = now()->toDateString();
        $this->ejercicio = now()->year;
    }

    /** Recalcula `$desde`/`$hasta` cuando cambia el selector de año. */
    public function updatedEjercicio(): void
    {
        $this->aplicarSelectorRapido();
    }

    /** Recalcula `$desde`/`$hasta` cuando cambia el selector de mes. */
    public function updatedMes(): void
    {
        $this->aplicarSelectorRapido();
    }

    private function aplicarSelectorRapido(): void
    {
        $this->desde = ($this->mes !== null
            ? Carbon::create($this->ejercicio, $this->mes, 1)->startOfMonth()
            : Carbon::create($this->ejercicio, 1, 1)->startOfYear()
        )->toDateString();

        $this->hasta = ($this->mes !== null
            ? Carbon::create($this->ejercicio, $this->mes, 1)->endOfMonth()
            : Carbon::create($this->ejercicio, 1, 1)->endOfYear()
        )->toDateString();
    }

    private function inicio(): Carbon
    {
        return Carbon::parse($this->desde)->startOfDay();
    }

    private function fin(): Carbon
    {
        return Carbon::parse($this->hasta)->endOfDay();
    }

    /**
     * Clave compuesta usada como `wire:key` de las 6 gráficas ECharts —
     * deben re-inicializarse (destroy + init) cada vez que `$desde`/`$hasta`
     * cambian, sin importar si vino de editar las fechas a mano o de un
     * atajo de año/mes (que ya escribe en esos mismos dos campos). También
     * incluye los 3 filtros de cross-filtering: sin esto, un clic que
     * cambia los datos de una gráfica no la reinicializa (Livewire hace
     * morph del nodo existente en vez de reemplazarlo) y Alpine nunca
     * vuelve a correr `x-init`, dejando la gráfica "congelada" con los
     * datos viejos aunque el servidor ya haya recalculado todo.
     */
    private function periodoKey(): string
    {
        return implode('-', [
            $this->desde,
            $this->hasta,
            $this->categoriaFiltro ?? '',
            $this->departamentoFiltro ?? '',
            $this->tipoSolicitudFiltro ?? '',
        ]);
    }

    /**
     * Texto corto siempre visible junto al título, para no perder contexto
     * de qué se está filtrando cuando el panel lateral está cerrado.
     */
    private function resumenPeriodo(): string
    {
        return $this->inicio()->translatedFormat('j M Y').' – '.$this->fin()->translatedFormat('j M Y');
    }

    /**
     * Rango real de años con datos, para poblar el `<select>` de año —
     * 2022 (el año más antiguo observado hoy en producción) no está
     * garantizado a futuro, así que se calcula a partir del dato real en
     * vez de hardcodearse.
     *
     * @return array<int, int> años ascendentes, de más antiguo a hoy
     */
    private function aniosDisponibles(): array
    {
        $minimo = (int) (optional(SdpTicket::min('created_time'))->year ?? now()->year);
        $maximo = now()->year;

        return range(min($minimo, $maximo), $maximo);
    }

    /** @return array<int, string> 1 => 'Enero', ..., 12 => 'Diciembre' */
    private function mesesDelAnio(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $mes) => [$mes => ucfirst(Carbon::create(2000, $mes, 1)->translatedFormat('F'))])
            ->all();
    }

    /**
     * Base de tickets CREADOS dentro del rango — incluye combinados (ver
     * docblock de la clase). Es la base de todos los conteos generales y de
     * la colección que `render()` carga una sola vez en memoria; de ahí se
     * deriva, filtrando en PHP (mismo criterio que `Slas::ticketsEnRango()`,
     * solo que ya con la colección cargada en vez de una segunda consulta),
     * el subconjunto sin combinados usado en los cálculos de SLA/tiempo de
     * resolución.
     *
     * Los 3 filtros de cross-filtering (categoría/departamento/tipo de
     * solicitud) se aplican aquí encima del periodo, cada uno en su propio
     * `when()` independiente para que se combinen entre sí con AND —
     * `null` en la propiedad correspondiente significa "sin filtro activo
     * en esa dimensión" y no toca la query.
     */
    private function ticketsEnRango(): Builder
    {
        return SdpTicket::query()
            ->whereBetween('created_time', [$this->inicio(), $this->fin()])
            ->when(
                $this->categoriaFiltro !== null,
                fn (Builder $q) => $this->categoriaFiltro === self::SIN_DATO
                    ? $q->whereNull('categoria')
                    : $q->where('categoria', $this->categoriaFiltro)
            )
            ->when(
                $this->departamentoFiltro !== null,
                fn (Builder $q) => $this->departamentoFiltro === self::SIN_DATO
                    ? $q->whereNull('departamento')
                    : $q->where('departamento', $this->departamentoFiltro)
            )
            ->when(
                $this->tipoSolicitudFiltro !== null,
                fn (Builder $q) => $q->where('tipo_solicitud', $this->tipoSolicitudFiltro)
            );
    }

    private function etiquetaPeriodo(string $periodo, string $formato = 'M Y'): string
    {
        // Se ancla explícitamente al día 1 del mes (en vez de dejar que
        // Carbon::createFromFormat('Y-m', ...) herede el día actual del
        // servidor) para no desbordar a otro mes cuando hoy es un día que
        // no existe en el mes destino (ej. hoy 31, mes destino con 30 días).
        return ucfirst(Carbon::createFromFormat('Y-m-d', $periodo.'-01')->translatedFormat($formato));
    }

    /**
     * Mismo criterio de resolución que `SdpSlaDefinition::paraPrioridad()`
     * (coincidencia exacta de prioridad primero, luego el catch-all de
     * prioridad `null`), pero contra una colección cargada UNA sola vez
     * por request en vez de 1-2 consultas por llamada.
     *
     * Encontrado en producción (2026-09-19): `calcularCumplimiento()` se
     * llama sobre miles de tickets (el histórico completo del año,
     * ~68k filas), y cada llamada a `paraPrioridad()` original pegaba a la
     * base de datos — decenas de miles de consultas en una sola petición,
     * suficiente para agotar el `max_execution_time` de 30s de PHP-FPM. Las
     * definiciones de SLA son un catálogo pequeño (unas pocas filas) que no
     * cambia durante la request, así que cachearlas aquí es seguro.
     */
    private function resolverSlaDefinicion(?string $prioridad): ?SdpSlaDefinition
    {
        $this->slaDefinicionesActivas ??= SdpSlaDefinition::where('activo', true)->get();

        if ($prioridad !== null && $prioridad !== '') {
            $especifica = $this->slaDefinicionesActivas->firstWhere('prioridad', $prioridad);

            if ($especifica) {
                return $especifica;
            }
        }

        return $this->slaDefinicionesActivas->firstWhere('prioridad', null);
    }

    /**
     * Cálculo de cumplimiento de SLA sobre una colección de tickets, SIN
     * agrupar por técnico/categoría (a diferencia de
     * `Slas::calcularCumplimiento()`) — un solo bucket agregado. Mismo
     * criterio de inclusión/exclusión: por ticket se resuelve su
     * `SdpSlaDefinition` aplicable vía `resolverSlaDefinicion()`; sin
     * definición aplicable, o sin el timestamp necesario todavía, el
     * ticket se excluye de ese numerador/denominador (no cuenta ni a
     * favor ni en contra).
     *
     * @param  Collection<int, SdpTicket>  $tickets
     * @return array{
     *     primera_respuesta: array{evaluables:int,cumplidas:int,pct:?float},
     *     resolucion: array{evaluables:int,cumplidas:int,pct:?float}
     * }
     */
    private function calcularCumplimiento(Collection $tickets): array
    {
        $evaluablesRespuesta = 0;
        $cumplidasRespuesta = 0;
        $evaluablesResolucion = 0;
        $cumplidasResolucion = 0;

        /** @var SdpTicket $ticket */
        foreach ($tickets as $ticket) {
            $definicion = $this->resolverSlaDefinicion($ticket->prioridad);

            if (! $definicion) {
                continue;
            }

            if ($definicion->tiempo_primera_respuesta_minutos !== null && $ticket->responded_time !== null) {
                $evaluablesRespuesta++;
                $minutos = abs($ticket->created_time->diffInMinutes($ticket->responded_time));

                if ($minutos <= $definicion->tiempo_primera_respuesta_minutos) {
                    $cumplidasRespuesta++;
                }
            }

            $tiempoResolucion = $ticket->resolved_time ?? $ticket->completed_time;

            if ($definicion->tiempo_resolucion_minutos !== null && $tiempoResolucion !== null) {
                $evaluablesResolucion++;
                $minutos = abs($ticket->created_time->diffInMinutes($tiempoResolucion));

                if ($minutos <= $definicion->tiempo_resolucion_minutos) {
                    $cumplidasResolucion++;
                }
            }
        }

        return [
            'primera_respuesta' => [
                'evaluables' => $evaluablesRespuesta,
                'cumplidas' => $cumplidasRespuesta,
                'pct' => $evaluablesRespuesta > 0 ? round($cumplidasRespuesta / $evaluablesRespuesta * 100, 1) : null,
            ],
            'resolucion' => [
                'evaluables' => $evaluablesResolucion,
                'cumplidas' => $cumplidasResolucion,
                'pct' => $evaluablesResolucion > 0 ? round($cumplidasResolucion / $evaluablesResolucion * 100, 1) : null,
            ],
        ];
    }

    /**
     * Mediana (no promedio) en horas de `resolved_time ?? completed_time`
     * menos `created_time`, sobre los tickets de la colección que sí tienen
     * ese timestamp poblado. Calculada en PHP sobre una Collection
     * ordenada — mismo criterio de "cargar la colección del rango en
     * memoria" que ya usa `Slas::calcularCumplimiento()`.
     *
     * @param  Collection<int, SdpTicket>  $tickets
     */
    private function tiempoMedianoResolucionHoras(Collection $tickets): ?float
    {
        $horas = $tickets
            ->map(function (SdpTicket $ticket) {
                $tiempoResolucion = $ticket->resolved_time ?? $ticket->completed_time;

                return $tiempoResolucion ? abs($ticket->created_time->diffInMinutes($tiempoResolucion)) / 60 : null;
            })
            ->filter(fn (?float $horas) => $horas !== null)
            ->sort()
            ->values();

        if ($horas->isEmpty()) {
            return null;
        }

        $total = $horas->count();
        $mitad = intdiv($total, 2);

        $mediana = $total % 2 === 1
            ? $horas[$mitad]
            : ($horas[$mitad - 1] + $horas[$mitad]) / 2;

        return round($mediana, 1);
    }

    /**
     * @param  Collection<string,int>  $conteosOrdenadosDesc  etiqueta => total, YA ordenado desc
     * @return Collection<string,int>
     */
    private function topMasOtras(Collection $conteosOrdenadosDesc, int $top): Collection
    {
        $principales = $conteosOrdenadosDesc->take($top);
        $resto = $conteosOrdenadosDesc->slice($top)->sum();

        return $resto > 0 ? $principales->put('Otras', $resto) : $principales;
    }

    /**
     * Conteo de tickets creados por mes dentro del rango — agrupado en SQL
     * (ver docblock de la clase sobre por qué SUBSTR y no YEAR()/MONTH()).
     */
    private function tendenciaOption(): array
    {
        $filas = $this->ticketsEnRango()
            ->selectRaw("SUBSTR(created_time, 1, 7) as periodo, COUNT(*) as total")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get();

        return [
            'tooltip' => ['trigger' => 'axis'],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $filas->map(fn ($f) => $this->etiquetaPeriodo($f->periodo))->all(),
            ],
            'yAxis' => ['type' => 'value'],
            'series' => [[
                'type' => 'line',
                'smooth' => true,
                'areaStyle' => ['opacity' => 0.15],
                'data' => $filas->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ]],
        ];
    }

    /** Dona de categorías: top N + "Otras" agregando el resto. */
    private function categoriaOption(): array
    {
        $conteos = $this->ticketsEnRango()
            ->selectRaw("COALESCE(categoria, 'Sin categoría') as etiqueta, COUNT(*) as total")
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->pluck('total', 'etiqueta');

        $datos = $this->topMasOtras($conteos, self::TOP_CATEGORIAS);

        return [
            'tooltip' => ['trigger' => 'item'],
            'legend' => ['bottom' => 0, 'type' => 'scroll'],
            'series' => [[
                'type' => 'pie',
                'radius' => ['45%', '70%'],
                'label' => ['formatter' => '{b}: {d}%'],
                'data' => $datos->map(fn ($total, $etiqueta) => ['name' => $etiqueta, 'value' => $total])->values()->all(),
            ]],
        ];
    }

    /**
     * % de cumplimiento de SLA (resolución) por mes + línea de meta fija.
     *
     * @param  Collection<int, SdpTicket>  $ticketsSla
     */
    private function slaPorMesOption(Collection $ticketsSla): array
    {
        $porMes = $ticketsSla->groupBy(fn (SdpTicket $t) => $t->created_time->format('Y-m'))->sortKeys();

        $etiquetas = $porMes->keys()->map(fn ($p) => $this->etiquetaPeriodo($p))->all();
        $pcts = $porMes->map(fn (Collection $grupo) => $this->calcularCumplimiento($grupo)['resolucion']['pct']);

        return [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['data' => ['% SLA cumplido', 'Meta'], 'bottom' => 0],
            'xAxis' => ['type' => 'category', 'data' => $etiquetas],
            'yAxis' => ['type' => 'value', 'max' => 100, 'axisLabel' => ['formatter' => '{value}%']],
            'series' => [
                [
                    'name' => '% SLA cumplido',
                    'type' => 'bar',
                    // Mes sin tickets evaluables (pct null) se muestra como 0
                    // en la barra — no hay forma de representar "sin dato" en
                    // una barra sin dejar un hueco engañoso; el tooltip de
                    // ECharts igual muestra el valor crudo.
                    'data' => $pcts->map(fn ($pct) => $pct ?? 0)->values()->all(),
                ],
                [
                    'name' => 'Meta',
                    'type' => 'line',
                    'symbol' => 'none',
                    'data' => array_fill(0, $porMes->count(), self::META_SLA_PCT),
                ],
            ],
        ];
    }

    /** Barra horizontal de departamentos con más tickets (top N, sin agregar "Otras"). */
    private function departamentoOption(): array
    {
        $conteos = $this->ticketsEnRango()
            ->selectRaw("COALESCE(departamento, 'Sin departamento') as etiqueta, COUNT(*) as total")
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->limit(self::TOP_DEPARTAMENTOS)
            ->pluck('total', 'etiqueta')
            ->reverse(); // ascendente: la barra más grande queda arriba en un eje Y categórico.

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'grid' => ['left' => '28%', 'right' => '6%'],
            'xAxis' => ['type' => 'value'],
            'yAxis' => ['type' => 'category', 'data' => $conteos->keys()->all()],
            'series' => [[
                'type' => 'bar',
                'data' => $conteos->values()->all(),
            ]],
        ];
    }

    /** Tipo de solicitud por mes, barra apilada. */
    private function tipoPorMesOption(): array
    {
        $filas = $this->ticketsEnRango()
            ->whereNotNull('tipo_solicitud')
            ->selectRaw("SUBSTR(created_time, 1, 7) as periodo, tipo_solicitud, COUNT(*) as total")
            ->groupBy('periodo', 'tipo_solicitud')
            ->orderBy('periodo')
            ->get();

        $periodos = $filas->pluck('periodo')->unique()->sort()->values();
        $etiquetas = $periodos->map(fn ($p) => $this->etiquetaPeriodo($p));

        $tipos = ['Solicitud', 'Incidente', 'Requerimiento'];

        $series = collect($tipos)->map(function (string $tipo) use ($filas, $periodos) {
            $porPeriodo = $filas->where('tipo_solicitud', $tipo)->keyBy('periodo');

            return [
                'name' => $tipo,
                'type' => 'bar',
                'stack' => 'total',
                'data' => $periodos->map(fn ($p) => (int) ($porPeriodo->get($p)->total ?? 0))->all(),
            ];
        })->values()->all();

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'legend' => ['bottom' => 0],
            'xAxis' => ['type' => 'category', 'data' => $etiquetas->all()],
            'yAxis' => ['type' => 'value'],
            'series' => $series,
        ];
    }

    /**
     * Distribución por nivel de atención — incluye una fila "Sin nivel"
     * para los NULL (esperado hoy: el campo se agregó a la sincronización
     * el 2026-09-17 y el sync es incremental). Calculado sobre la
     * colección ya cargada, sin consulta adicional.
     *
     * @param  Collection<int, SdpTicket>  $ticketsTotal
     * @return Collection<int, array{etiqueta:string, total:int, pct:float}>
     */
    private function distribucionPorNivel(Collection $ticketsTotal): Collection
    {
        $total = $ticketsTotal->count();

        return $ticketsTotal
            ->groupBy(fn (SdpTicket $t) => $t->nivel ?: 'Sin nivel')
            ->map(fn (Collection $grupo, string $etiqueta) => [
                'etiqueta' => $etiqueta,
                'total' => $grupo->count(),
                'pct' => $total > 0 ? round($grupo->count() / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Matriz categoría (top N + "Otras") x mes, con conteo por celda y el
     * máximo de cada fila (para la intensidad de color en la vista).
     *
     * @return array{meses: array<int,string>, filas: array<int, array{etiqueta:string, valores:array<int,int>, max:int}>}
     */
    private function heatmapCategoriasPorMes(): array
    {
        $filas = $this->ticketsEnRango()
            ->selectRaw("COALESCE(categoria, 'Sin categoría') as etiqueta, SUBSTR(created_time, 1, 7) as periodo, COUNT(*) as total")
            ->groupBy('etiqueta', 'periodo')
            ->get();

        $totalesPorCategoria = $filas->groupBy('etiqueta')
            ->map(fn (Collection $g) => $g->sum('total'))
            ->sortDesc();

        $topCategorias = $totalesPorCategoria->take(self::TOP_CATEGORIAS_HEATMAP)->keys();
        $categoriasRestantes = $totalesPorCategoria->slice(self::TOP_CATEGORIAS_HEATMAP)->keys();

        $periodos = $filas->pluck('periodo')->unique()->sort()->values();

        $construirFila = function (string $etiqueta, Collection $filasEtiqueta) use ($periodos) {
            $porPeriodo = $filasEtiqueta->groupBy('periodo')->map(fn (Collection $g) => $g->sum('total'));
            $valores = $periodos->map(fn ($p) => (int) ($porPeriodo->get($p) ?? 0))->all();

            return [
                'etiqueta' => $etiqueta,
                'valores' => $valores,
                'max' => max($valores ?: [0]) ?: 1,
            ];
        };

        $matriz = $topCategorias
            ->map(fn (string $etiqueta) => $construirFila($etiqueta, $filas->where('etiqueta', $etiqueta)))
            ->values();

        if ($categoriasRestantes->isNotEmpty()) {
            $filasOtras = $filas->whereIn('etiqueta', $categoriasRestantes->all());
            $matriz->push($construirFila('Otras', $filasOtras));
        }

        return [
            'meses' => $periodos->map(fn ($p) => $this->etiquetaPeriodo($p))->all(),
            'filas' => $matriz->all(),
        ];
    }

    /**
     * "Hallazgos Estratégicos" — 4 a 6 insights por reglas simples sobre el
     * rango seleccionado (mismo espíritu que `PatronesDetector`, pero sobre
     * el rango completo en vez de "hoy"). Una regla que no aplica (rango
     * corto, sin datos suficientes) simplemente se omite, sin generar una
     * tarjeta vacía ni dividir por cero.
     *
     * @param  Collection<int, SdpTicket>  $ticketsTotal
     * @param  Collection<int, SdpTicket>  $ticketsSla
     * @return array<int, array{titulo:string, texto:string}>
     */
    private function hallazgos(Collection $ticketsTotal, Collection $ticketsSla): array
    {
        $hallazgos = [];
        $total = $ticketsTotal->count();

        if ($total > 0) {
            $topCategoria = $ticketsTotal
                ->groupBy(fn (SdpTicket $t) => $t->categoria ?: 'Sin categoría')
                ->map->count()
                ->sortDesc();

            if ($topCategoria->isNotEmpty()) {
                $etiqueta = $topCategoria->keys()->first();
                $conteo = $topCategoria->first();
                $pct = round($conteo / $total * 100, 1);

                $hallazgos[] = [
                    'titulo' => 'Categoría con más tickets',
                    'texto' => "\"{$etiqueta}\" concentra {$conteo} tickets ({$pct}% del total) en el periodo seleccionado.",
                ];
            }

            $topDepartamento = $ticketsTotal
                ->groupBy(fn (SdpTicket $t) => $t->departamento ?: 'Sin departamento')
                ->map->count()
                ->sortDesc();

            if ($topDepartamento->isNotEmpty()) {
                $etiqueta = $topDepartamento->keys()->first();
                $conteo = $topDepartamento->first();
                $pct = round($conteo / $total * 100, 1);

                $hallazgos[] = [
                    'titulo' => 'Área con más demanda',
                    'texto' => "\"{$etiqueta}\" generó {$conteo} tickets ({$pct}% del total) en el periodo seleccionado.",
                ];
            }
        }

        $porMes = $ticketsTotal->groupBy(fn (SdpTicket $t) => $t->created_time->format('Y-m'));

        if ($porMes->count() >= 2) {
            $totalesPorMes = $porMes->map->count();
            $promedio = $totalesPorMes->avg();
            $mesPicoKey = $totalesPorMes->sortDesc()->keys()->first();
            $conteoMesPico = $totalesPorMes->get($mesPicoKey);

            $hallazgos[] = [
                'titulo' => 'Mes con más tickets creados',
                'texto' => $this->etiquetaPeriodo($mesPicoKey, 'F Y')." tuvo {$conteoMesPico} tickets creados, frente a un promedio de ".number_format($promedio, 1).' por mes en el periodo seleccionado.',
            ];
        }

        $porMesSla = $ticketsSla->groupBy(fn (SdpTicket $t) => $t->created_time->format('Y-m'));
        $pctPorMes = $porMesSla
            ->map(fn (Collection $grupo) => $this->calcularCumplimiento($grupo)['resolucion'])
            ->filter(fn (array $c) => $c['pct'] !== null);

        if ($pctPorMes->count() >= 2) {
            $mejorKey = $pctPorMes->sortByDesc(fn (array $c) => $c['pct'])->keys()->first();
            $peorKey = $pctPorMes->sortBy(fn (array $c) => $c['pct'])->keys()->first();

            if ($mejorKey !== $peorKey) {
                $hallazgos[] = [
                    'titulo' => 'Mejor mes de cumplimiento de SLA',
                    'texto' => $this->etiquetaPeriodo($mejorKey, 'F Y').' tuvo el mejor cumplimiento del periodo: '.$pctPorMes->get($mejorKey)['pct'].'%.',
                ];
                $hallazgos[] = [
                    'titulo' => 'Mes con oportunidad de mejora en SLA',
                    'texto' => $this->etiquetaPeriodo($peorKey, 'F Y').' tuvo el cumplimiento más bajo del periodo: '.$pctPorMes->get($peorKey)['pct'].'%.',
                ];
            }
        }

        if ($total > 0) {
            $combinados = $ticketsTotal->filter(fn (SdpTicket $t) => $t->combinado_con_display_id !== null)->count();

            if ($combinados > 0) {
                $pctCombinados = round($combinados / $total * 100, 1);

                $hallazgos[] = [
                    'titulo' => 'Tickets combinados',
                    'texto' => "{$combinados} tickets ({$pctCombinados}%) se fusionaron a otro folio en ServiceDesk Plus durante el periodo — representan trabajo real que no aparece como folio independiente en el conteo simple.",
                ];
            }
        }

        return $hallazgos;
    }

    /**
     * Resumen mensual con las mismas métricas del KPI principal (total,
     * completados, % SLA, tiempo mediano) calculadas por mes, más una fila
     * final "Total" que recalcula el agregado real sobre todo el rango (no
     * es la suma/promedio de las filas mensuales).
     *
     * @param  Collection<int, SdpTicket>  $ticketsTotal
     * @param  Collection<int, SdpTicket>  $ticketsSla
     * @return Collection<int, array{etiqueta:string, total:int, completados:int, pctSla:?float, medianaHoras:?float, esTotal:bool}>
     */
    private function resumenMensual(Collection $ticketsTotal, Collection $ticketsSla): Collection
    {
        $porMesTotal = $ticketsTotal->groupBy(fn (SdpTicket $t) => $t->created_time->format('Y-m'))->sortKeys();
        $porMesSla = $ticketsSla->groupBy(fn (SdpTicket $t) => $t->created_time->format('Y-m'));

        $filas = $porMesTotal->map(function (Collection $grupoTotal, string $periodo) use ($porMesSla) {
            $grupoSla = $porMesSla->get($periodo, collect());
            $cumplimiento = $this->calcularCumplimiento($grupoSla);

            return [
                'etiqueta' => $this->etiquetaPeriodo($periodo, 'F Y'),
                'total' => $grupoTotal->count(),
                'completados' => $grupoTotal->filter(fn (SdpTicket $t) => $t->ticketStatus?->tipo === SdpTicketStatus::TIPO_COMPLETADO)->count(),
                'pctSla' => $cumplimiento['resolucion']['pct'],
                'medianaHoras' => $this->tiempoMedianoResolucionHoras($grupoSla),
                'esTotal' => false,
            ];
        })->values();

        $cumplimientoTotal = $this->calcularCumplimiento($ticketsSla);

        $filas->push([
            'etiqueta' => 'Total',
            'total' => $ticketsTotal->count(),
            'completados' => $ticketsTotal->filter(fn (SdpTicket $t) => $t->ticketStatus?->tipo === SdpTicketStatus::TIPO_COMPLETADO)->count(),
            'pctSla' => $cumplimientoTotal['resolucion']['pct'],
            'medianaHoras' => $this->tiempoMedianoResolucionHoras($ticketsSla),
            'esTotal' => true,
        ]);

        return $filas;
    }

    /**
     * Cierra el panel de filtros desde el servidor tras aplicar (el propio
     * `<x-ui.slide-over>` también escucha este evento). `$desde`/`$hasta`
     * ya están al día en este punto — o porque el usuario los editó
     * directamente (`wire:model` sin `.live`, viajan con esta misma
     * request), o porque un cambio previo en año/mes ya los recalculó vía
     * `updatedEjercicio()`/`updatedMes()` — así que no hace falta
     * recalcular nada aquí.
     */
    public function aplicarFiltro(): void
    {
        $this->dispatch('close-filtros-periodo');
    }

    /**
     * Cross-filtering estilo Power BI: clic en un elemento de una gráfica
     * filtra TODO el dashboard, además del periodo (ver docblock de las
     * propiedades `*Filtro`). Toggle: clic en el valor ya seleccionado lo
     * quita. "Otras" es un bucket agregado (categorías fuera del top N),
     * no un valor real de la columna — un clic ahí se ignora.
     */
    public function seleccionarCategoria(string $valor): void
    {
        if ($valor === 'Otras') {
            return;
        }

        $real = $valor === 'Sin categoría' ? self::SIN_DATO : $valor;

        $this->categoriaFiltro = $this->categoriaFiltro === $real ? null : $real;
    }

    public function seleccionarDepartamento(string $valor): void
    {
        if ($valor === 'Otras') {
            return;
        }

        $real = $valor === 'Sin departamento' ? self::SIN_DATO : $valor;

        $this->departamentoFiltro = $this->departamentoFiltro === $real ? null : $real;
    }

    /**
     * `tipo_solicitud` no tiene agregados "Otras"/"Sin dato" en esta
     * pantalla (ni la gráfica apilada ni las tarjetas KPI secundarias los
     * muestran) — el valor recibido siempre es directamente filtrable.
     */
    public function seleccionarTipoSolicitud(string $valor): void
    {
        $this->tipoSolicitudFiltro = $this->tipoSolicitudFiltro === $valor ? null : $valor;
    }

    /** Limpia los 3 filtros de cross-filtering sin tocar el periodo (`$desde`/`$hasta`). */
    public function limpiarFiltrosSeleccion(): void
    {
        $this->categoriaFiltro = null;
        $this->departamentoFiltro = null;
        $this->tipoSolicitudFiltro = null;
    }

    /**
     * Filtros de cross-filtering activos, listos para pintarse como chips
     * removibles — traduce {@see self::SIN_DATO} de vuelta a la etiqueta
     * legible que el usuario originalmente clickeó. El `metodo`/`valor` de
     * cada chip es exactamente lo que hay que volver a pasarle al método
     * `seleccionar*` correspondiente para des-seleccionarlo (mismo patrón
     * de toggle que un segundo clic en la gráfica).
     *
     * @return array<int, array{etiqueta:string, metodo:string, valor:string}>
     */
    private function filtrosActivos(): array
    {
        $filtros = [];

        if ($this->categoriaFiltro !== null) {
            $valor = $this->categoriaFiltro === self::SIN_DATO ? 'Sin categoría' : $this->categoriaFiltro;
            $filtros[] = ['etiqueta' => "Categoría: {$valor}", 'metodo' => 'seleccionarCategoria', 'valor' => $valor];
        }

        if ($this->departamentoFiltro !== null) {
            $valor = $this->departamentoFiltro === self::SIN_DATO ? 'Sin departamento' : $this->departamentoFiltro;
            $filtros[] = ['etiqueta' => "Departamento: {$valor}", 'metodo' => 'seleccionarDepartamento', 'valor' => $valor];
        }

        if ($this->tipoSolicitudFiltro !== null) {
            $filtros[] = ['etiqueta' => "Tipo: {$this->tipoSolicitudFiltro}", 'metodo' => 'seleccionarTipoSolicitud', 'valor' => $this->tipoSolicitudFiltro];
        }

        return $filtros;
    }

    /**
     * Columnas realmente usadas sobre los modelos de `$ticketsTotal`/
     * `$ticketsSla` en toda la clase (auditado con grep antes de escribir
     * esto, no es una lista a ojo). `sdp_ticket_status_id` es la FK que
     * necesita el eager load de `ticketStatus`, no se usa directo.
     *
     * Encontrado en producción (2026-09-19): con el histórico completo de
     * 2026 ya sincronizado, cargar el `SELECT *` completo de miles de
     * tickets — incluyendo `raw_payload` (el JSON íntegro de cada ticket
     * en SDP, potencialmente grande) y el resto de columnas que esta
     * pantalla nunca toca — agotaba el `memory_limit` de PHP-FPM (128MB) y
     * tiraba el dashboard con 500. Acotar el `SELECT` a solo estas 12
     * columnas es la corrección real; no es una optimización opcional.
     */
    private const COLUMNAS_TICKET_EJECUTIVO = [
        'id', 'created_time', 'responded_time', 'resolved_time', 'completed_time',
        'prioridad', 'nivel', 'categoria', 'departamento', 'tipo_solicitud',
        'combinado_con_display_id', 'sdp_ticket_status_id',
    ];

    public function render()
    {
        $ticketsTotal = $this->ticketsEnRango()
            ->select(self::COLUMNAS_TICKET_EJECUTIVO)
            ->with('ticketStatus')
            ->get();
        $ticketsSla = $ticketsTotal->whereNull('combinado_con_display_id')->values();

        $cumplimientoGlobal = $this->calcularCumplimiento($ticketsSla);

        return view('mesaservicio::livewire.dashboards.ejecutivo', [
            'totalTickets' => $ticketsTotal->count(),
            'completados' => $ticketsTotal->filter(fn (SdpTicket $t) => $t->ticketStatus?->tipo === SdpTicketStatus::TIPO_COMPLETADO)->count(),
            'pctSlaCumplido' => $cumplimientoGlobal['resolucion']['pct'],
            'medianaResolucionHoras' => $this->tiempoMedianoResolucionHoras($ticketsSla),
            'incidentes' => $ticketsTotal->where('tipo_solicitud', 'Incidente')->count(),
            'solicitudes' => $ticketsTotal->where('tipo_solicitud', 'Solicitud')->count(),
            'requerimientos' => $ticketsTotal->where('tipo_solicitud', 'Requerimiento')->count(),
            'combinados' => $ticketsTotal->filter(fn (SdpTicket $t) => $t->combinado_con_display_id !== null)->count(),
            'tendenciaOption' => $this->tendenciaOption(),
            'categoriaOption' => $this->categoriaOption(),
            'slaPorMesOption' => $this->slaPorMesOption($ticketsSla),
            'departamentoOption' => $this->departamentoOption(),
            'tipoPorMesOption' => $this->tipoPorMesOption(),
            'nivelDistribucion' => $this->distribucionPorNivel($ticketsTotal),
            'heatmap' => $this->heatmapCategoriasPorMes(),
            'hallazgos' => $this->hallazgos($ticketsTotal, $ticketsSla),
            'resumenMensual' => $this->resumenMensual($ticketsTotal, $ticketsSla),
            'metaSlaPct' => self::META_SLA_PCT,
            'resumenPeriodo' => $this->resumenPeriodo(),
            'filtrosActivos' => $this->filtrosActivos(),
            'periodoKey' => $this->periodoKey(),
            'aniosDisponibles' => $this->aniosDisponibles(),
            'mesesDelAnio' => $this->mesesDelAnio(),
        ]);
    }
}
