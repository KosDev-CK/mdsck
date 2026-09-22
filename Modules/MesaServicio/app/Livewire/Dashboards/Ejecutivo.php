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
 * Todas las consultas de agrupación por periodo usan
 * `SUBSTR(created_time, 1, N)` en vez de `YEAR()`/`MONTH()`/`HOUR()`
 * (funciones nativas de MySQL, la base de producción) a propósito: la
 * suite de pruebas corre contra SQLite en memoria (ver phpunit.xml), que
 * no tiene esas funciones pero sí soporta `SUBSTR()` — mismo espíritu
 * portátil que ya usa `PatronesDetector` con `DATE(created_time)`. El
 * resultado ('YYYY-MM', 'YYYY-MM-DD' o 'YYYY-MM-DD HH' según
 * {@see self::granularidad()}) es directamente ordenable como texto y se
 * traduce a una etiqueta legible con `etiquetaGranular()`.
 *
 * Las 5 piezas "por periodo" (tendencia, cumplimiento de SLA, tipo de
 * solicitud, categorías y resumen) hacen zoom automático de granularidad
 * según el ancho real de `$desde`/`$hasta` — mes para rangos largos, día
 * para un mes o menos, hora para un solo día (ver `granularidad()`) — para
 * que un mes seleccionado no se vea como una sola barra sin detalle.
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
     * Rango de fechas crudo del periodo activo ("1 Ene 2026 – 22 Sep
     * 2026") — usado como último recurso por {@see self::periodoFiltroTexto()}
     * cuando el rango no calza limpio con un año/mes/día completo, y
     * todavía cubierto por su propio test (`resumenPeriodo` en la vista).
     */
    private function resumenPeriodo(): string
    {
        return $this->inicio()->translatedFormat('j M Y').' – '.$this->fin()->translatedFormat('j M Y');
    }

    /**
     * Texto corto siempre visible junto al título (y como hint del KPI
     * "Total de tickets"), para no perder contexto de qué se está
     * filtrando cuando el panel lateral está cerrado — a pedido explícito
     * del usuario, un año/mes/día completo se muestra COMO TAL ("Año:
     * 2026", "Mes: Septiembre 2026", "Día: 10 de septiembre de 2026"),
     * nunca como el rango de fechas crudo (más difícil de leer de un
     * vistazo). Se deriva de los valores REALES de `$desde`/`$hasta`, no
     * solo de `$mes`/`$ejercicio` (esos son atajos de UI que pueden
     * desincronizarse si el usuario edita las fechas a mano después de
     * usarlos) — `$mes`/`$ejercicio` solo se usan como pista adicional
     * para decidir entre "Mes: X" y un rango personalizado cuando el
     * ancho ya calza con un mes completo. Un rango que no calza limpio
     * con año/mes/día completo (ej. "15 Mar – 20 Abr", editado a mano)
     * cae de vuelta a `resumenPeriodo()` — no hay una sola palabra que lo
     * represente sin perder información.
     */
    private function periodoFiltroTexto(): string
    {
        if ($this->desde === $this->hasta) {
            return 'Día: '.Carbon::parse($this->desde)->translatedFormat('j \d\e F \d\e Y');
        }

        $inicio = Carbon::parse($this->desde);

        $esMesCompleto = $this->mes !== null
            && $this->desde === $inicio->copy()->startOfMonth()->toDateString()
            && $this->hasta === $inicio->copy()->endOfMonth()->toDateString();

        if ($esMesCompleto) {
            return 'Mes: '.ucfirst($inicio->translatedFormat('F \d\e Y'));
        }

        $esAnioCompleto = $this->mes === null
            && $this->desde === $inicio->copy()->startOfYear()->toDateString()
            && (
                $this->hasta === $inicio->copy()->endOfYear()->toDateString()
                || $this->hasta === now()->toDateString()
            );

        if ($esAnioCompleto) {
            return 'Año: '.$inicio->year;
        }

        return 'Periodo: '.$this->resumenPeriodo();
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
     * Granularidad temporal de las 5 piezas "por periodo" del dashboard
     * (tendencia, cumplimiento de SLA, tipo de solicitud, categorías por
     * periodo y resumen) — se deriva SIEMPRE del ancho real de
     * `$desde`/`$hasta`, nunca de qué control de la UI lo produjo (el
     * atajo Año/Mes o editar las fechas a mano dan el mismo resultado si
     * el rango es equivalente). No afecta a `hallazgos()`, que sigue
     * agrupando siempre por mes (son insights de "vista de alto nivel",
     * independientes del zoom de las gráficas — se auto-omiten igual si
     * el rango no tiene los 2+ meses que necesitan).
     *
     * - Un solo día (Desde = Hasta) → 'hora' (00h a 23h de ese día).
     * - Hasta 31 días (~ un mes)    → 'dia'.
     * - Más de 31 días              → 'mes' (comportamiento original).
     */
    private function granularidad(): string
    {
        if ($this->desde === $this->hasta) {
            return 'hora';
        }

        $dias = Carbon::parse($this->desde)->diffInDays(Carbon::parse($this->hasta)) + 1;

        return $dias <= 31 ? 'dia' : 'mes';
    }

    /** Longitud de `SUBSTR(created_time, 1, N)` para agrupar EN SQL según la granularidad activa. */
    private function longitudSubstrPeriodo(): int
    {
        return match ($this->granularidad()) {
            'hora' => 13, // 'YYYY-MM-DD HH'
            'dia' => 10,  // 'YYYY-MM-DD'
            'mes' => 7,   // 'YYYY-MM'
        };
    }

    /** Mismo agrupador que {@see self::longitudSubstrPeriodo()} pero para `Carbon::format()` sobre una colección ya cargada en PHP. */
    private function formatoPeriodoPhp(): string
    {
        return match ($this->granularidad()) {
            'hora' => 'Y-m-d H',
            'dia' => 'Y-m-d',
            'mes' => 'Y-m',
        };
    }

    /**
     * Etiqueta legible de una clave de periodo cruda (tal como la produce
     * `longitudSubstrPeriodo()`/`formatoPeriodoPhp()`), según la
     * granularidad activa — a diferencia de {@see self::etiquetaPeriodo()}
     * (fija en formato de mes, usada solo por `hallazgos()`), esta se
     * ajusta sola.
     */
    private function etiquetaGranular(string $periodo, bool $completo = false): string
    {
        return match ($this->granularidad()) {
            'hora' => Carbon::createFromFormat('Y-m-d H', $periodo)->format('H:00'),
            'dia' => ucfirst(Carbon::createFromFormat('Y-m-d', $periodo)->translatedFormat($completo ? 'j \d\e F' : 'j M')),
            'mes' => ucfirst(Carbon::createFromFormat('Y-m-d', $periodo.'-01')->translatedFormat($completo ? 'F Y' : 'M Y')),
        };
    }

    /**
     * Sufijo "por mes"/"por día"/"por hora" para los títulos de las 5
     * piezas dinámicas en la vista — para que el título de cada tarjeta
     * siempre sea consistente con la granularidad real de sus datos (ver
     * `granularidad()`).
     */
    private function granularidadTexto(): string
    {
        return match ($this->granularidad()) {
            'hora' => 'por hora',
            'dia' => 'por día',
            'mes' => 'por mes',
        };
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
     * Tendencia de 2 puntos (primer mes vs último mes con tickets dentro del
     * rango seleccionado) para el indicador debajo del KPI "Total de
     * tickets" — a propósito NO es el promedio ni una serie completa (esa ya
     * la muestra la gráfica de "Evolución mensual"), solo el extremo inicial
     * contra el final, igual de simple que los indicadores del mockup de
     * referencia pero calculado sobre datos reales en vez de fijo.
     *
     * @param  Collection<int, SdpTicket>  $ticketsTotal
     * @return array{pct: float}|null null si hay menos de 2 meses con
     *     tickets en el rango, o el mes inicial no tiene tickets (división
     *     indefinida) — en ese caso la tarjeta simplemente no muestra
     *     indicador de tendencia.
     */
    private function tendenciaTotalTickets(Collection $ticketsTotal): ?array
    {
        $porMes = $ticketsTotal->groupBy(fn (SdpTicket $t) => $t->created_time->format('Y-m'))->sortKeys();

        if ($porMes->count() < 2) {
            return null;
        }

        $primero = $porMes->first()->count();
        $ultimo = $porMes->last()->count();

        if ($primero === 0) {
            return null;
        }

        return ['pct' => round(($ultimo - $primero) / $primero * 100, 1)];
    }

    /**
     * Mismo espíritu que {@see self::tendenciaTotalTickets()} pero sobre la
     * mediana de horas de resolución del primer y último mes del rango
     * (menor es mejor — un `pct` negativo significa que el último mes
     * resolvió más rápido que el primero).
     *
     * @param  Collection<int, SdpTicket>  $ticketsSla
     * @return array{pct: float}|null null si hay menos de 2 meses con
     *     mediana calculable en ambos extremos.
     */
    private function tendenciaTiempoResolucion(Collection $ticketsSla): ?array
    {
        $porMes = $ticketsSla->groupBy(fn (SdpTicket $t) => $t->created_time->format('Y-m'))->sortKeys();

        if ($porMes->count() < 2) {
            return null;
        }

        $primero = $this->tiempoMedianoResolucionHoras($porMes->first());
        $ultimo = $this->tiempoMedianoResolucionHoras($porMes->last());

        if ($primero === null || $ultimo === null || $primero == 0.0) {
            return null;
        }

        return ['pct' => round(($ultimo - $primero) / $primero * 100, 1)];
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
     * Conteo de tickets creados por periodo dentro del rango (mes/día/hora
     * según `granularidad()`) — agrupado en SQL (ver docblock de la clase
     * sobre por qué SUBSTR y no YEAR()/MONTH()).
     */
    private function tendenciaOption(): array
    {
        $longitud = $this->longitudSubstrPeriodo();

        $filas = $this->ticketsEnRango()
            ->selectRaw("SUBSTR(created_time, 1, {$longitud}) as periodo, COUNT(*) as total")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get();

        return [
            'tooltip' => ['trigger' => 'axis'],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $filas->map(fn ($f) => $this->etiquetaGranular($f->periodo))->all(),
            ],
            'yAxis' => ['type' => 'value'],
            'series' => [[
                'type' => 'line',
                'smooth' => true,
                'symbol' => 'circle',
                'symbolSize' => 8,
                'lineStyle' => ['width' => 3],
                // El relleno real (degradado de color a transparente) lo
                // arma `conDegradadoDeArea()` en charts.js con el color
                // semántico ya resuelto en runtime — aquí solo se declara
                // que la serie SÍ lleva área, sin fijar un color de marca.
                'areaStyle' => [],
                'label' => [
                    'show' => true,
                    'position' => 'top',
                    'fontSize' => 18,
                    'fontWeight' => 'bold',
                ],
                'data' => $filas->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ]],
        ];
    }

    /**
     * Top N categorías + "Otras" agregando el resto — base compartida por
     * `categoriaOption()` (la dona) y la tabla de participación que la
     * acompaña en la vista, para no calcularlo dos veces ni arriesgar que
     * ambas se desincronicen.
     *
     * @return Collection<string,int> etiqueta => total, ordenado desc
     */
    private function desgloseCategoria(): Collection
    {
        $conteos = $this->ticketsEnRango()
            ->selectRaw("COALESCE(categoria, 'Sin categoría') as etiqueta, COUNT(*) as total")
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->pluck('total', 'etiqueta');

        return $this->topMasOtras($conteos, self::TOP_CATEGORIAS);
    }

    /**
     * Filas listas para la tabla de participación junto a la dona —
     * `colorIndex` es la posición dentro de la paleta CATEGÓRICA de 6
     * colores que ya usa `buildTheme()` en charts.js (ver
     * {@see self::TOKENS_COLOR_CATEGORIA}, en ese orden y cíclica), para
     * que el punto de color de cada fila coincida con el color real que
     * ECharts le asignó a esa rebanada de la dona sin tener que mandar un
     * hexadecimal fijo desde PHP.
     *
     * @return array<int, array{etiqueta:string, total:int, pct:float, colorIndex:int}>
     */
    private function desgloseCategoriaTabla(Collection $desglose): array
    {
        $total = $desglose->sum();
        $numColores = count(self::TOKENS_COLOR_CATEGORIA);

        return $desglose->keys()->values()
            ->map(fn (string $etiqueta, int $indice) => [
                'etiqueta' => $etiqueta,
                'total' => $desglose->get($etiqueta),
                'pct' => $total > 0 ? round($desglose->get($etiqueta) / $total * 100, 1) : 0.0,
                'colorIndex' => $indice % $numColores,
            ])
            ->all();
    }

    /**
     * Dona de categorías — sin leyenda ni etiquetas externas (esas las
     * muestra la tabla de participación en la vista, ver
     * `desgloseCategoriaTabla()`); el total del periodo se muestra al
     * centro vía el componente `title` de ECharts, superpuesto en el hueco
     * de la dona.
     */
    private function categoriaOption(Collection $desglose): array
    {
        $total = $desglose->sum();

        return [
            'tooltip' => ['trigger' => 'item'],
            'title' => [
                'text' => number_format($total),
                'subtext' => 'tickets',
                'left' => 'center',
                'top' => 'center',
                'textStyle' => ['fontSize' => 20, 'fontWeight' => 'bold'],
                'subtextStyle' => ['fontSize' => 11],
            ],
            'series' => [[
                'type' => 'pie',
                'radius' => ['62%', '85%'],
                'label' => ['show' => false],
                'data' => $desglose->map(fn ($total, $etiqueta) => ['name' => $etiqueta, 'value' => $total])->values()->all(),
            ]],
        ];
    }

    /**
     * Los 15 tokens de la paleta CATEGÓRICA (mismo orden que
     * `categoricalColors()` en charts.js — las 3 paletas de 5 colores que el
     * usuario entregó vía Adobe Color) tal cual, SIN resolver — el nombre de
     * la custom property de `app.css`, no un hex. PHP no puede saber el hex
     * real; `initChart()` resuelve estos nombres a su valor real en runtime
     * vía `resolveSemanticTokens()`. Usados por `departamentoOption()`
     * (degradado del color fuerte a una versión clara del MISMO color,
     * cíclico entre departamentos) — a propósito NUNCA success/warning/danger
     * (el semáforo rojo/ámbar/verde de estado): un departamento cualquiera no
     * tiene un juicio de "bien/mal" que comunicar, solo necesita distinguirse
     * de los demás — mezclar ambas paletas le daría una lectura de semáforo
     * que no existe (ver también `slaPorMesOption()`, que sí usa el semáforo
     * real porque ahí el color SÍ es un juicio contra la meta).
     */
    private const TOKENS_COLOR_CATEGORIA = [
        '--color-chart-1', '--color-chart-2', '--color-chart-3', '--color-chart-4', '--color-chart-5',
        '--color-chart-6', '--color-chart-7', '--color-chart-8', '--color-chart-9', '--color-chart-10',
        '--color-chart-11', '--color-chart-12', '--color-chart-13', '--color-chart-14', '--color-chart-15',
    ];

    /**
     * % de cumplimiento de SLA (resolución) por mes, barra horizontal — un
     * mes se colorea según qué tan lejos está de {@see self::META_SLA_PCT}
     * (cumple / dentro de 10pp / más de 10pp por debajo), no con colores
     * fijos por mes. El promedio del periodo y la brecha contra la meta se
     * muestran debajo de la gráfica en la vista (`$pctSlaCumplido`/
     * `$metaSlaPct`, ya calculados en `render()`), no como una segunda
     * serie aquí.
     *
     * @param  Collection<int, SdpTicket>  $ticketsSla
     */
    private function slaPorMesOption(Collection $ticketsSla): array
    {
        $porMes = $ticketsSla->groupBy(fn (SdpTicket $t) => $t->created_time->format($this->formatoPeriodoPhp()))->sortKeys();

        $filas = $porMes->map(function (Collection $grupo, string $periodo) {
            $pct = $this->calcularCumplimiento($grupo)['resolucion']['pct'] ?? 0.0;

            return [
                'etiqueta' => $this->etiquetaGranular($periodo),
                'pct' => $pct,
                'color' => match (true) {
                    $pct >= self::META_SLA_PCT => '--color-success',
                    $pct >= self::META_SLA_PCT - 10 => '--color-warning',
                    default => '--color-danger',
                },
            ];
        })->values()->reverse()->values(); // reverse: Enero queda arriba en el eje Y categórico.

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow'], 'valueFormatter' => '{value}%'],
            'grid' => ['left' => '22%', 'right' => '10%', 'top' => 8, 'bottom' => 8, 'containLabel' => false],
            'xAxis' => ['type' => 'value', 'max' => 100, 'show' => false],
            'yAxis' => ['type' => 'category', 'data' => $filas->pluck('etiqueta')->all()],
            'series' => [[
                'type' => 'bar',
                'showBackground' => true,
                'barWidth' => '55%',
                'itemStyle' => ['borderRadius' => 4],
                'label' => [
                    'show' => true,
                    'position' => 'insideRight',
                    'color' => '#fff',
                    'fontWeight' => 'bold',
                    'formatter' => '{c}%',
                ],
                'data' => $filas->map(fn (array $f) => ['value' => $f['pct'], 'itemStyle' => ['color' => $f['color']]])->all(),
            ]],
        ];
    }

    /**
     * Barra horizontal de departamentos con más tickets (top N, sin agregar
     * "Otras") — cada barra es UN solo color CATEGÓRICO (cíclico sobre
     * {@see self::TOKENS_COLOR_CATEGORIA}, una barra por departamento), en
     * degradado del color fuerte a una versión más clara del MISMO color
     * (35% de opacidad, ver `resolveSemanticTokens()` en charts.js) — nunca
     * una mezcla entre dos colores distintos dentro de la misma barra.
     */
    private function departamentoOption(): array
    {
        $conteos = $this->ticketsEnRango()
            ->selectRaw("COALESCE(departamento, 'Sin departamento') as etiqueta, COUNT(*) as total")
            ->groupBy('etiqueta')
            ->orderByDesc('total')
            ->limit(self::TOP_DEPARTAMENTOS)
            ->pluck('total', 'etiqueta')
            ->reverse(); // ascendente: la barra más grande queda arriba en un eje Y categórico.

        $numTokens = count(self::TOKENS_COLOR_CATEGORIA);

        $datos = $conteos->values()
            ->map(function (int $total, int $indice) use ($numTokens) {
                $token = self::TOKENS_COLOR_CATEGORIA[$indice % $numTokens];

                return [
                    'value' => $total,
                    'itemStyle' => [
                        'borderRadius' => 4,
                        'color' => [
                            'type' => 'linear', 'x' => 0, 'y' => 0, 'x2' => 1, 'y2' => 0,
                            'colorStops' => [
                                ['offset' => 0, 'color' => $token],
                                ['offset' => 1, 'color' => $token.'/35'],
                            ],
                        ],
                    ],
                ];
            })
            ->all();

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'grid' => ['left' => '28%', 'right' => '8%', 'top' => 8, 'bottom' => 8],
            'xAxis' => ['type' => 'value', 'show' => false],
            'yAxis' => ['type' => 'category', 'data' => $conteos->keys()->all()],
            'series' => [[
                'type' => 'bar',
                'showBackground' => true,
                'barWidth' => '60%',
                'label' => [
                    'show' => true,
                    'position' => 'insideRight',
                    'color' => '#fff',
                    'fontWeight' => 'bold',
                ],
                'data' => $datos,
            ]],
        ];
    }

    /** Tipo de solicitud por periodo (mes/día/hora), barra apilada. */
    private function tipoPorMesOption(): array
    {
        $longitud = $this->longitudSubstrPeriodo();

        $filas = $this->ticketsEnRango()
            ->whereNotNull('tipo_solicitud')
            ->selectRaw("SUBSTR(created_time, 1, {$longitud}) as periodo, tipo_solicitud, COUNT(*) as total")
            ->groupBy('periodo', 'tipo_solicitud')
            ->orderBy('periodo')
            ->get();

        $periodos = $filas->pluck('periodo')->unique()->sort()->values();
        $etiquetas = $periodos->map(fn ($p) => $this->etiquetaGranular($p));

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
     * "Fortaleza operativa" — % de los tickets CON nivel asignado que se
     * resolvieron sin escalar a un grupo especialista o proveedor externo,
     * es decir, todo lo que no cayó en el nivel 3 del catálogo de SDP
     * (confirmado contra datos reales: el nivel de escalamiento externo
     * siempre empieza con el prefijo `"3."` — ej. "3. Escalado a grupo
     * especialista o a proveedor" — se detecta por el prefijo numérico, no
     * el texto completo, porque SDP puede reeditar la redacción del
     * catálogo sin tocar su numeración). "Sin nivel" se excluye de ambos
     * lados de la proporción — no es "resuelto sin escalar", es "sin dato
     * todavía" (ver docblock de `distribucionPorNivel()`), así que
     * mezclarlo con la proporción real inflaría el % artificialmente en
     * rangos donde la mayoría de los tickets aún no tienen nivel
     * sincronizado.
     *
     * @param  Collection<int, array{etiqueta:string, total:int, pct:float}>  $nivelDistribucion
     * @return array{pct: float}|null null si ningún ticket del rango tiene
     *     nivel asignado todavía.
     */
    private function fortalezaOperativaNivel(Collection $nivelDistribucion): ?array
    {
        $conNivelAsignado = $nivelDistribucion->reject(fn (array $fila) => $fila['etiqueta'] === 'Sin nivel');
        $totalConNivel = $conNivelAsignado->sum('total');

        if ($totalConNivel === 0) {
            return null;
        }

        $escalados = $conNivelAsignado->first(fn (array $fila) => str_starts_with($fila['etiqueta'], '3.'));

        return ['pct' => round(($totalConNivel - ($escalados['total'] ?? 0)) / $totalConNivel * 100, 1)];
    }

    /**
     * Matriz categoría (top N + "Otras") x periodo (mes/día/hora), con
     * conteo por celda y el máximo de cada fila (para la intensidad de
     * color en la vista).
     *
     * @return array{meses: array<int,string>, filas: array<int, array{etiqueta:string, valores:array<int,int>, max:int}>}
     */
    private function heatmapCategoriasPorMes(): array
    {
        $longitud = $this->longitudSubstrPeriodo();

        $filas = $this->ticketsEnRango()
            ->selectRaw("COALESCE(categoria, 'Sin categoría') as etiqueta, SUBSTR(created_time, 1, {$longitud}) as periodo, COUNT(*) as total")
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

        // Cada fila (categoría) se pinta en un tono CATEGÓRICO distinto —
        // ver la vista — cíclico sobre los 6 tokens de
        // {@see self::TOKENS_COLOR_CATEGORIA}, igual que
        // desgloseCategoriaTabla()/departamentoOption() — nunca el semáforo
        // success/warning/danger, que aquí no representaría ningún juicio
        // real (ver el comentario junto a esa constante).
        $numColoresHeatmap = count(self::TOKENS_COLOR_CATEGORIA);
        $matriz = $matriz->values()->map(fn (array $fila, int $indice) => [...$fila, 'colorIndex' => $indice % $numColoresHeatmap]);

        return [
            'meses' => $periodos->map(fn ($p) => $this->etiquetaGranular($p))->all(),
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
     * Cada hallazgo trae su propio `icono` (nombre corto, sin el prefijo
     * `heroicon-o-` — lo agrega la vista) y `color` (mismo vocabulario que
     * `x-ui.stat-tile`/`x-ui.badge`: primary/success/warning/danger/info),
     * fijos por REGLA (no por el valor calculado) para que la sección se
     * lea con variedad temática en vez de repetir el mismo ícono/color en
     * las 6 tarjetas — la lista completa de hallazgos sigue siendo 100%
     * dinámica en contenido, solo la presentación de cada tipo es fija.
     *
     * @param  Collection<int, SdpTicket>  $ticketsTotal
     * @param  Collection<int, SdpTicket>  $ticketsSla
     * @return array<int, array{titulo:string, texto:string, icono:string, color:string}>
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
                    'icono' => 'trophy',
                    'color' => 'info',
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
                    'icono' => 'building-office-2',
                    'color' => 'primary',
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
                'icono' => 'arrow-trending-up',
                'color' => 'success',
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
                    'icono' => 'check-badge',
                    'color' => 'success',
                ];
                $hallazgos[] = [
                    'titulo' => 'Mes con oportunidad de mejora en SLA',
                    'texto' => $this->etiquetaPeriodo($peorKey, 'F Y').' tuvo el cumplimiento más bajo del periodo: '.$pctPorMes->get($peorKey)['pct'].'%.',
                    'icono' => 'exclamation-triangle',
                    'color' => 'danger',
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
                    'icono' => 'document-duplicate',
                    'color' => 'warning',
                ];
            }
        }

        return $hallazgos;
    }

    /**
     * Tamaño de muestra mínimo para considerar "confiable" la mediana de
     * resolución de un mes en el resumen mensual — por debajo de esto el
     * dato sigue siendo real (no se oculta ni se inventa), pero se marca
     * con una nota dinámica (ver `resumenMensualNotas()`) porque una
     * mediana sobre 1-4 tickets es fácilmente arrastrada por un solo caso
     * atípico.
     */
    private const MUESTRA_MINIMA_MEDIANA = 5;

    /**
     * Resumen mensual con las mismas métricas del KPI principal (total,
     * completados, vencidos de SLA, % SLA, tiempo mediano, incidentes,
     * solicitudes) calculadas por mes, más una fila final "Total" que
     * recalcula el agregado real sobre todo el rango (no es la
     * suma/promedio de las filas mensuales).
     *
     * @param  Collection<int, SdpTicket>  $ticketsTotal
     * @param  Collection<int, SdpTicket>  $ticketsSla
     * @return Collection<int, array{etiqueta:string, total:int, completados:int, vencidos:int, pctSla:?float, medianaHoras:?float, muestraMediana:int, medianaPocoConfiable:bool, incidentes:int, solicitudes:int, esTotal:bool}>
     */
    private function resumenMensual(Collection $ticketsTotal, Collection $ticketsSla): Collection
    {
        $construirFila = function (Collection $grupoTotal, Collection $grupoSla, string $etiqueta, bool $esTotal): array {
            $cumplimiento = $this->calcularCumplimiento($grupoSla);
            $muestraMediana = $grupoSla
                ->filter(fn (SdpTicket $t) => ($t->resolved_time ?? $t->completed_time) !== null)
                ->count();

            return [
                'etiqueta' => $etiqueta,
                'total' => $grupoTotal->count(),
                'completados' => $grupoTotal->filter(fn (SdpTicket $t) => $t->ticketStatus?->tipo === SdpTicketStatus::TIPO_COMPLETADO)->count(),
                'vencidos' => $cumplimiento['resolucion']['evaluables'] - $cumplimiento['resolucion']['cumplidas'],
                'pctSla' => $cumplimiento['resolucion']['pct'],
                'medianaHoras' => $this->tiempoMedianoResolucionHoras($grupoSla),
                'muestraMediana' => $muestraMediana,
                'medianaPocoConfiable' => $muestraMediana > 0 && $muestraMediana < self::MUESTRA_MINIMA_MEDIANA,
                'incidentes' => $grupoTotal->where('tipo_solicitud', 'Incidente')->count(),
                'solicitudes' => $grupoTotal->where('tipo_solicitud', 'Solicitud')->count(),
                'esTotal' => $esTotal,
            ];
        };

        $porMesTotal = $ticketsTotal->groupBy(fn (SdpTicket $t) => $t->created_time->format($this->formatoPeriodoPhp()))->sortKeys();
        $porMesSla = $ticketsSla->groupBy(fn (SdpTicket $t) => $t->created_time->format($this->formatoPeriodoPhp()));

        $filas = $porMesTotal->map(fn (Collection $grupoTotal, string $periodo) => $construirFila(
            $grupoTotal,
            $porMesSla->get($periodo, collect()),
            $this->etiquetaGranular($periodo, completo: true),
            false
        ))->values();

        $filas->push($construirFila($ticketsTotal, $ticketsSla, 'Total', true));

        return $filas;
    }

    /**
     * Notas dinámicas debajo de la tabla de resumen mensual — una por cada
     * mes cuya mediana de resolución se calculó sobre una muestra menor a
     * {@see self::MUESTRA_MINIMA_MEDIANA}. A diferencia del mockup de
     * referencia (que traía una nota fija hardcodeada sobre un mes
     * puntual), esta lista sale vacía cuando ningún mes del rango
     * seleccionado califica — nunca un texto fijo sin relación con los
     * datos reales.
     *
     * @param  Collection<int, array{etiqueta:string, medianaPocoConfiable:bool, muestraMediana:int, esTotal:bool}>  $filas
     * @return array<int, string>
     */
    private function resumenMensualNotas(Collection $filas): array
    {
        return $filas
            ->reject(fn (array $fila) => $fila['esTotal'])
            ->filter(fn (array $fila) => $fila['medianaPocoConfiable'])
            ->map(fn (array $fila) => "{$fila['etiqueta']}: mediana de resolución calculada sobre solo {$fila['muestraMediana']} ticket(s) con tiempo registrado — dato poco representativo.")
            ->values()
            ->all();
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
        $desgloseCategoria = $this->desgloseCategoria();
        $nivelDistribucion = $this->distribucionPorNivel($ticketsTotal);

        return view('mesaservicio::livewire.dashboards.ejecutivo', [
            'totalTickets' => $ticketsTotal->count(),
            'completados' => $ticketsTotal->filter(fn (SdpTicket $t) => $t->ticketStatus?->tipo === SdpTicketStatus::TIPO_COMPLETADO)->count(),
            'pctSlaCumplido' => $cumplimientoGlobal['resolucion']['pct'],
            // Tickets evaluables de SLA que NO cumplieron resolución dentro
            // del tiempo definido — el complemento de $pctSlaCumplido, no un
            // conteo nuevo: mismo numerador/denominador ya calculados por
            // calcularCumplimiento(), solo restados en vez de divididos.
            'ticketsSlaVencidos' => $cumplimientoGlobal['resolucion']['evaluables'] - $cumplimientoGlobal['resolucion']['cumplidas'],
            'medianaResolucionHoras' => $this->tiempoMedianoResolucionHoras($ticketsSla),
            'tendenciaTotalTickets' => $this->tendenciaTotalTickets($ticketsTotal),
            'tendenciaTiempoResolucion' => $this->tendenciaTiempoResolucion($ticketsSla),
            'incidentes' => $ticketsTotal->where('tipo_solicitud', 'Incidente')->count(),
            'solicitudes' => $ticketsTotal->where('tipo_solicitud', 'Solicitud')->count(),
            'requerimientos' => $ticketsTotal->where('tipo_solicitud', 'Requerimiento')->count(),
            'combinados' => $ticketsTotal->filter(fn (SdpTicket $t) => $t->combinado_con_display_id !== null)->count(),
            'tendenciaOption' => $this->tendenciaOption(),
            'categoriaOption' => $this->categoriaOption($desgloseCategoria),
            'categoriaTabla' => $this->desgloseCategoriaTabla($desgloseCategoria),
            'slaPorMesOption' => $this->slaPorMesOption($ticketsSla),
            'departamentoOption' => $this->departamentoOption(),
            'tipoPorMesOption' => $this->tipoPorMesOption(),
            'nivelDistribucion' => $nivelDistribucion,
            'fortalezaOperativaNivel' => $this->fortalezaOperativaNivel($nivelDistribucion),
            'heatmap' => $this->heatmapCategoriasPorMes(),
            'hallazgos' => $this->hallazgos($ticketsTotal, $ticketsSla),
            'resumenMensual' => $resumenMensual = $this->resumenMensual($ticketsTotal, $ticketsSla),
            'resumenMensualNotas' => $this->resumenMensualNotas($resumenMensual),
            'metaSlaPct' => self::META_SLA_PCT,
            'resumenPeriodo' => $this->resumenPeriodo(),
            'periodoFiltroTexto' => $this->periodoFiltroTexto(),
            'granularidadTexto' => $this->granularidadTexto(),
            'filtrosActivos' => $this->filtrosActivos(),
            'periodoKey' => $this->periodoKey(),
            'aniosDisponibles' => $this->aniosDisponibles(),
            'mesesDelAnio' => $this->mesesDelAnio(),
        ]);
    }
}
