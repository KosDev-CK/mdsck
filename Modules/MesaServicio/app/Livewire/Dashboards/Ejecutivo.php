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
     * Modo de acotamiento del periodo — mutuamente excluyentes. `'rango'`
     * usa `$desde`/`$hasta` libres (comportamiento original de esta
     * pantalla); `'mes'` y `'ejercicio'` existen para que dirección pueda
     * comparar mes contra mes o año contra año sin tener que calcular a
     * mano el primer/último día del periodo. Viven en un panel lateral
     * (ver `slide-over.blade.php`) en vez de sueltos arriba del contenido,
     * porque a futuro se van a sumar más filtros a esta pantalla.
     */
    public string $tipoPeriodo = 'rango';

    public string $desde = '';

    public string $hasta = '';

    /** 1 a 12. Usado solo cuando `tipoPeriodo === 'mes'`. */
    public int $mes = 1;

    /**
     * Año calendario (enero a diciembre — mdsLandIT no maneja año fiscal
     * distinto). Usado como el año del mes elegido cuando
     * `tipoPeriodo === 'mes'`, y como el año completo cuando
     * `tipoPeriodo === 'ejercicio'`.
     */
    public int $ejercicio = 2000;

    public function mount(): void
    {
        $this->desde = now()->startOfYear()->toDateString();
        $this->hasta = now()->toDateString();
        $this->ejercicio = now()->year;
        $this->mes = now()->month;
    }

    private function inicio(): Carbon
    {
        return match ($this->tipoPeriodo) {
            'mes' => Carbon::create($this->ejercicio, $this->mes, 1)->startOfMonth(),
            'ejercicio' => Carbon::create($this->ejercicio, 1, 1)->startOfYear(),
            default => Carbon::parse($this->desde)->startOfDay(),
        };
    }

    private function fin(): Carbon
    {
        return match ($this->tipoPeriodo) {
            'mes' => Carbon::create($this->ejercicio, $this->mes, 1)->endOfMonth(),
            'ejercicio' => Carbon::create($this->ejercicio, 1, 1)->endOfYear(),
            default => Carbon::parse($this->hasta)->endOfDay(),
        };
    }

    /**
     * Clave compuesta usada como `wire:key` de las 6 gráficas ECharts —
     * deben re-inicializarse (destroy + init) cada vez que el periodo
     * efectivo cambia, sin importar de qué modo venga (antes solo incluía
     * `$desde`/`$hasta`, lo que dejaba las gráficas "congeladas" al cambiar
     * de modo sin tocar esas dos propiedades).
     */
    private function periodoKey(): string
    {
        return "{$this->tipoPeriodo}-{$this->desde}-{$this->hasta}-{$this->mes}-{$this->ejercicio}";
    }

    /**
     * Texto corto siempre visible junto al título, para no perder contexto
     * de qué se está filtrando cuando el panel lateral está cerrado.
     */
    private function resumenPeriodo(): string
    {
        return match ($this->tipoPeriodo) {
            'mes' => $this->etiquetaPeriodo(sprintf('%04d-%02d', $this->ejercicio, $this->mes), 'F Y'),
            'ejercicio' => 'Ejercicio '.$this->ejercicio,
            default => $this->inicio()->translatedFormat('j M Y').' – '.$this->fin()->translatedFormat('j M Y'),
        };
    }

    /**
     * Rango real de años con datos, para poblar los `<select>` de año en
     * los modos "mes" y "ejercicio" — 2022 (el año más antiguo observado
     * hoy en producción) no está garantizado a futuro, así que se calcula
     * a partir del dato real en vez de hardcodearse.
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
     */
    private function ticketsEnRango(): Builder
    {
        return SdpTicket::query()->whereBetween('created_time', [$this->inicio(), $this->fin()]);
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
     * Cálculo de cumplimiento de SLA sobre una colección de tickets, SIN
     * agrupar por técnico/categoría (a diferencia de
     * `Slas::calcularCumplimiento()`) — un solo bucket agregado. Mismo
     * criterio de inclusión/exclusión: por ticket se resuelve su
     * `SdpSlaDefinition` aplicable vía `paraPrioridad()`; sin definición
     * aplicable, o sin el timestamp necesario todavía, el ticket se excluye
     * de ese numerador/denominador (no cuenta ni a favor ni en contra).
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
            $definicion = SdpSlaDefinition::paraPrioridad($ticket->prioridad);

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
     * `<x-ui.slide-over>` también escucha este evento). Los `wire:model`
     * (sin `.live`) de los controles del panel ya viajaron con esta misma
     * request antes de que el método se ejecute, así que no hace falta
     * recalcular nada aquí — el siguiente `render()` ya usa los valores
     * nuevos.
     */
    public function aplicarFiltro(): void
    {
        $this->dispatch('close-filtros-periodo');
    }

    public function render()
    {
        $ticketsTotal = $this->ticketsEnRango()->with('ticketStatus')->get();
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
            'periodoKey' => $this->periodoKey(),
            'aniosDisponibles' => $this->aniosDisponibles(),
            'mesesDelAnio' => $this->mesesDelAnio(),
        ]);
    }
}
