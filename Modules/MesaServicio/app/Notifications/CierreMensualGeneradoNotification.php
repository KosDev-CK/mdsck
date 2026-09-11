<?php

namespace Modules\MesaServicio\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Modules\MesaServicio\Models\SdpReport;

/**
 * Aviso simple de "ya se generó el cierre mensual" — calcada de
 * Modules\MesaServicio\Notifications\CierreDiarioGeneradoNotification (Fase 4)
 * en vez de generalizar una sola clase parametrizada por tipo: se decidió así
 * porque el cierre mensual tiene un dato adicional propio (la métrica de
 * folios combinados) que no aplica al diario, y una clase por tipo de cierre
 * es más simple de leer/editar a futuro que una clase genérica con
 * condicionales por `$report->tipo` — mismo criterio de "una clase por
 * variante" que ya usa el resto del módulo (ej. Livewire\Catalogos\Tecnicos
 * vs. Livewire\Catalogos\Destinatarios, pantallas distintas en vez de una
 * genérica parametrizada). Ver docs/mesaservicio-progreso.md, Fase 5.
 */
class CierreMensualGeneradoNotification extends Notification
{
    use Queueable;

    public function __construct(protected SdpReport $report)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $periodo = $this->report->periodo->translatedFormat('F Y');
        $metricas = $this->report->resumen_metricas;
        $total = $metricas['total'] ?? 0;
        $estimadoCombinados = $metricas['estimado_combinados'] ?? null;

        $mail = (new MailMessage)
            ->subject("Cierre mensual de Mesa de Servicio — {$periodo}")
            ->greeting('Hola,')
            ->line("Se generó el cierre mensual de Mesa de Servicio correspondiente a {$periodo}.")
            ->line("Total de tickets del mes: {$total}.");

        if ($estimadoCombinados !== null) {
            $mail->line("Estimado de tickets combinados por SDP (folios fusionados): {$estimadoCombinados}.");
        }

        if (Route::has('mesaservicio.reportes.index')) {
            $mail->action('Ver reportes', route('mesaservicio.reportes.index'));
        }

        return $mail->line('El archivo Excel con el detalle completo está disponible para descargar desde la pantalla de Reportes.');
    }
}
