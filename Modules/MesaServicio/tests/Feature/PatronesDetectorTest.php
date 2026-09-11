<?php

namespace Modules\MesaServicio\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Services\PatronesDetector;
use Tests\TestCase;

class PatronesDetectorTest extends TestCase
{
    use RefreshDatabase;

    private int $sdpIdSeq = 1;

    private function ticket(string $categoria, \DateTimeInterface|string $creadoEl): SdpTicket
    {
        return SdpTicket::create([
            'sdp_id' => 'tk-'.$this->sdpIdSeq++,
            'asunto' => 'Ticket de prueba',
            'categoria' => $categoria,
            'created_time' => $creadoEl,
        ]);
    }

    /**
     * Siembra N días distintos de histórico (uno por día hacia atrás desde
     * ayer) con $conteoPorDia tickets de $categoria en cada uno, para poder
     * superar MINIMO_DIAS_HISTORIAL sin repetir el mismo día dos veces.
     */
    private function sembrarHistorico(string $categoria, int $dias, int $conteoPorDia): void
    {
        for ($i = 1; $i <= $dias; $i++) {
            for ($j = 0; $j < $conteoPorDia; $j++) {
                $this->ticket($categoria, now()->subDays($i));
            }
        }
    }

    public function test_top_categorias_agrupa_y_ordena_por_conteo_descendente(): void
    {
        $this->ticket('Red', now());
        $this->ticket('Red', now());
        $this->ticket('Red', now());
        $this->ticket('Hardware', now());
        $this->ticket('Hardware', now());
        $this->ticket('Software', now());
        // No cuenta: de ayer.
        $this->ticket('Red', now()->subDay());

        $top = (new PatronesDetector())->topCategorias();

        $this->assertSame(['Red', 'Hardware', 'Software'], $top->pluck('categoria')->all());
        $this->assertSame([3, 2, 1], $top->pluck('conteo')->all());
    }

    public function test_top_categorias_respeta_el_limite(): void
    {
        $this->ticket('A', now());
        $this->ticket('B', now());
        $this->ticket('C', now());

        $top = (new PatronesDetector())->topCategorias(limite: 2);

        $this->assertCount(2, $top);
    }

    public function test_sin_historico_suficiente_no_hay_picos_ni_error(): void
    {
        // Solo 2 días de histórico, muy por debajo de MINIMO_DIAS_HISTORIAL.
        $this->sembrarHistorico('Red', dias: 2, conteoPorDia: 1);
        $this->ticket('Red', now());
        $this->ticket('Red', now());
        $this->ticket('Red', now());
        $this->ticket('Red', now());
        $this->ticket('Red', now());

        $detector = new PatronesDetector();

        $this->assertFalse($detector->historicoSuficiente());
        $this->assertTrue($detector->picos()->isEmpty());
    }

    public function test_detecta_pico_cuando_hoy_supera_el_umbral_del_promedio(): void
    {
        // 10 días de histórico, promedio de 2 tickets/día para "Red".
        $this->sembrarHistorico('Red', dias: 10, conteoPorDia: 2);

        // Hoy: 5 tickets de Red (>= 1.5 * 2 = 3, y >= mínimo de 3 hoy).
        for ($i = 0; $i < 5; $i++) {
            $this->ticket('Red', now());
        }

        $picos = (new PatronesDetector())->picos();

        $this->assertCount(1, $picos);
        $this->assertSame('Red', $picos->first()['categoria']);
        $this->assertSame(5, $picos->first()['hoy']);
        $this->assertSame(2.0, $picos->first()['promedio']);
    }

    public function test_no_detecta_pico_cuando_el_conteo_de_hoy_es_normal(): void
    {
        // 10 días de histórico, promedio de 4 tickets/día para "Red".
        $this->sembrarHistorico('Red', dias: 10, conteoPorDia: 4);

        // Hoy: 4 tickets, justo el promedio, no un pico.
        for ($i = 0; $i < 4; $i++) {
            $this->ticket('Red', now());
        }

        $picos = (new PatronesDetector())->picos();

        $this->assertTrue($picos->isEmpty());
    }

    public function test_no_detecta_pico_si_el_volumen_de_hoy_es_ruido_minimo(): void
    {
        // Categoría nunca vista antes (promedio 0) pero con muy pocos
        // tickets hoy (< MINIMO_TICKETS_HOY) — no debe marcarse como pico.
        $this->sembrarHistorico('Red', dias: 10, conteoPorDia: 1);
        $this->ticket('Impresoras', now());

        $picos = (new PatronesDetector())->picos();

        $this->assertTrue($picos->isEmpty());
    }

    public function test_categoria_nueva_sin_historico_no_causa_division_por_cero(): void
    {
        // Suficientes días de histórico en general (para otra categoría),
        // pero "Impresoras" nunca apareció antes de hoy.
        $this->sembrarHistorico('Red', dias: 10, conteoPorDia: 1);
        for ($i = 0; $i < 5; $i++) {
            $this->ticket('Impresoras', now());
        }

        $detector = new PatronesDetector();

        $this->assertTrue($detector->historicoSuficiente());
        // No debe reventar y "Impresoras" no debe aparecer como pico
        // (promedio histórico 0, se trata como categoría nueva, no anomalía).
        $picos = $detector->picos();
        $this->assertFalse($picos->pluck('categoria')->contains('Impresoras'));
    }
}
