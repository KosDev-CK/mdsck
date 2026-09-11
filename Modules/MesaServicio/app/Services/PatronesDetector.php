<?php

namespace Modules\MesaServicio\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\MesaServicio\Models\SdpTicket;

/**
 * Detección de patrones y picos de tickets (Fase 3) — reglas simples sobre
 * `sdp_tickets`, explícitamente SIN infraestructura de machine learning.
 *
 * Se calcula on-demand cada vez que se pide (mismo criterio que el resto de
 * `Dashboard::render()`: sin cache ni tabla de métricas precalculadas). Ver
 * docs/mesaservicio-progreso.md, sección Fase 3, para la justificación de
 * esta decisión frente a precalcular el análisis en el job de sync de
 * 5 minutos.
 *
 * Reglas (documentadas también en docs/mesaservicio-progreso.md como
 * "ajustes de criterio tomados sin poder confirmar antes"):
 * - Ventana de histórico: últimos VENTANA_DIAS días completos, sin contar
 *   el día de hoy.
 * - El promedio histórico de una categoría se calcula dividiendo su total
 *   en la ventana entre los días DISTINTOS que sí tienen algún ticket
 *   registrado (no entre VENTANA_DIAS fijo) — evita subestimar el promedio
 *   (y por lo tanto sobredetectar picos) cuando el módulo lleva poco
 *   tiempo sincronizando.
 * - No se intenta detectar picos si hay menos de MINIMO_DIAS_HISTORIAL
 *   días distintos con datos en la ventana — evita falsos picos por
 *   historial insuficiente.
 * - Una categoría se marca como "pico" si hoy tiene al menos
 *   MINIMO_TICKETS_HOY tickets (evita ruido de categorías con volumen
 *   ínfimo, ej. "1 hoy vs. 0.3 de promedio") Y el conteo de hoy es al
 *   menos UMBRAL_PICO veces el promedio histórico.
 * - Si el promedio histórico de una categoría es 0 (nunca antes vista en
 *   la ventana), no se marca como pico — es una categoría nueva, no una
 *   anomalía de volumen, y evita división por cero.
 */
class PatronesDetector
{
    /** Días de histórico a considerar para el promedio (sin contar hoy). */
    public const VENTANA_DIAS = 30;

    /** Días distintos con datos que hacen falta antes de intentar detectar picos. */
    public const MINIMO_DIAS_HISTORIAL = 7;

    /** Hoy debe ser al menos esta proporción del promedio histórico para considerarse "pico". */
    public const UMBRAL_PICO = 1.5;

    /** Tickets mínimos hoy en una categoría para evitar ruido de categorías con volumen ínfimo. */
    public const MINIMO_TICKETS_HOY = 3;

    /**
     * Top categorías del día por conteo de tickets creados hoy.
     *
     * @return Collection<int, object{categoria: string, conteo: int}>
     */
    public function topCategorias(int $limite = 5): Collection
    {
        return SdpTicket::query()
            ->whereDate('created_time', today())
            ->whereNotNull('categoria')
            ->selectRaw('categoria, count(*) as conteo')
            ->groupBy('categoria')
            ->orderByDesc('conteo')
            ->limit($limite)
            ->get();
    }

    /**
     * Si hay suficiente histórico (ver MINIMO_DIAS_HISTORIAL) como para que
     * comparar contra el promedio tenga sentido.
     */
    public function historicoSuficiente(): bool
    {
        return $this->diasConDatos() >= self::MINIMO_DIAS_HISTORIAL;
    }

    /**
     * Categorías cuyo conteo de hoy supera el umbral de pico respecto a su
     * promedio histórico diario. Colección vacía (sin error) si no hay
     * histórico suficiente o si ninguna categoría superó el umbral.
     *
     * @return Collection<int, array{categoria: string, hoy: int, promedio: float, ratio: float}>
     */
    public function picos(): Collection
    {
        if (! $this->historicoSuficiente()) {
            return collect();
        }

        $diasConDatos = $this->diasConDatos();
        $conteoHistoricoPorCategoria = $this->historicoQuery()
            ->whereNotNull('categoria')
            ->selectRaw('categoria, count(*) as total')
            ->groupBy('categoria')
            ->pluck('total', 'categoria');

        return $this->topCategorias(limite: 100)
            ->filter(fn (object $fila) => $fila->conteo >= self::MINIMO_TICKETS_HOY)
            ->map(function (object $fila) use ($conteoHistoricoPorCategoria, $diasConDatos) {
                $totalHistorico = (int) ($conteoHistoricoPorCategoria[$fila->categoria] ?? 0);
                $promedio = $totalHistorico / $diasConDatos;

                return [
                    'categoria' => $fila->categoria,
                    'hoy' => (int) $fila->conteo,
                    'promedio' => round($promedio, 1),
                    'ratio' => $promedio > 0 ? round($fila->conteo / $promedio, 2) : null,
                ];
            })
            ->filter(fn (array $fila) => $fila['promedio'] > 0 && $fila['hoy'] >= self::UMBRAL_PICO * $fila['promedio'])
            ->values();
    }

    private function inicioVentana(): Carbon
    {
        return today()->subDays(self::VENTANA_DIAS);
    }

    private function historicoQuery(): Builder
    {
        return SdpTicket::query()
            ->where('created_time', '>=', $this->inicioVentana())
            ->where('created_time', '<', today());
    }

    private function diasConDatos(): int
    {
        return $this->historicoQuery()
            ->selectRaw('DATE(created_time) as dia')
            ->distinct()
            ->pluck('dia')
            ->count();
    }
}
