<?php

namespace Modules\MesaServicio\Livewire;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Modules\MesaServicio\Services\PatronesDetector;

/**
 * Dashboard de Mesa de Servicio (Fase 2) — conteos del día + alerta de SLA
 * de primera respuesta, todo calculado on-demand sobre sdp_tickets (sin
 * tabla propia de alertas ni de métricas cacheadas). Primer consumidor real
 * de x-ui.stat-tile en el repo.
 *
 * "Atendidos hoy" (ajuste de criterio, ver docs/mesaservicio-progreso.md):
 * tickets cuyo estado local es tipo "completado" Y (completed_time cae hoy,
 * o —si SDP no trae completed_time para ese ticket— la fila local se
 * actualizó hoy). No se pudo confirmar con el usuario si "atendido" debe
 * leerse estrictamente por completed_time de SDP o por la fecha en que el
 * sync detectó el cambio; se optó por el criterio más permisivo (con
 * fallback) para no subcontar tickets que SDP marca como completados sin
 * traer completed_time poblado.
 *
 * "Hallazgos del día" (Fase 3, ver Modules\MesaServicio\Services\PatronesDetector
 * para el detalle de las reglas): top categorías + picos, calculado sobre
 * TODOS los tickets del día sin filtrar por $soloNivel1 — a propósito. El
 * toggle de nivel 1 existe para acotar el desempeño de un subconjunto de
 * técnicos; los patrones de categoría son un fenómeno de la mesa completa
 * ("¿qué está pasando hoy?"), filtrarlos por nivel 1 les quitaría sentido.
 *
 * "Sincronizar ahora" (agregado 2026-09-11): `sdp:sync-tickets` dejó de
 * correr automático cada 5 minutos (ver MesaServicioServiceProvider — un
 * log sin rotar llenó el disco del servidor de producción con esa
 * frecuencia) y ahora se dispara solo a mano, desde este botón o por
 * consola. Corre el comando de forma síncrona dentro del propio request de
 * Livewire — aceptable mientras el volumen de tickets sea bajo; si algún día
 * la sincronización tarda lo suficiente para acercarse al timeout de
 * PHP-FPM, hay que moverla a un job encolado en vez de llamarla directo aquí.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public bool $soloNivel1 = false;

    public bool $sincronizando = false;

    public function sincronizar(): void
    {
        $this->sincronizando = true;

        try {
            Artisan::call('sdp:sync-tickets');

            session()->flash('status', trim(Artisan::output()) ?: 'Sincronización completada.');
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo sincronizar: '.$e->getMessage());
        } finally {
            $this->sincronizando = false;
        }
    }

    private function baseQuery(): Builder
    {
        return SdpTicket::query()->when($this->soloNivel1, fn (Builder $q) => $q->deTecnicosNivel1());
    }

    private function creadosHoy(): int
    {
        return $this->baseQuery()->whereDate('created_time', today())->count();
    }

    private function atendidosHoy(): int
    {
        return $this->baseQuery()
            ->whereHas('ticketStatus', fn (Builder $q) => $q->where('tipo', SdpTicketStatus::TIPO_COMPLETADO))
            ->where(function (Builder $q) {
                $q->whereDate('completed_time', today())
                    ->orWhere(function (Builder $q2) {
                        $q2->whereNull('completed_time')->whereDate('updated_at', today());
                    });
            })
            ->count();
    }

    private function pendientes(): int
    {
        return $this->baseQuery()
            ->whereHas('ticketStatus', fn (Builder $q) => $q->where('tipo', SdpTicketStatus::TIPO_EN_CURSO))
            ->count();
    }

    /**
     * Tope de 10 filas en la tabla de detalle de la alerta — solo para no
     * volcar cientos de filas en pantalla, el conteo total (badge) sí
     * refleja el total real sin tope.
     *
     * @return Collection<int, SdpTicket>
     */
    private function slaVencidosDetalle(): Collection
    {
        return $this->baseQuery()
            ->slaPrimeraRespuestaVencida()
            ->orderBy('created_time')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        $patrones = new PatronesDetector();

        return view('mesaservicio::livewire.dashboard', [
            'creadosHoy' => $this->creadosHoy(),
            'atendidosHoy' => $this->atendidosHoy(),
            'pendientes' => $this->pendientes(),
            'slaVencidosCount' => $this->baseQuery()->slaPrimeraRespuestaVencida()->count(),
            'slaVencidosDetalle' => $this->slaVencidosDetalle(),
            'topCategorias' => $patrones->topCategorias(),
            'picos' => $patrones->picos(),
            'historicoSuficiente' => $patrones->historicoSuficiente(),
            'minimoDiasHistorial' => PatronesDetector::MINIMO_DIAS_HISTORIAL,
        ]);
    }
}
