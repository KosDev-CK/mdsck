<?php

namespace Modules\MesaServicio\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Modules\MesaServicio\Models\SdpReport;

/**
 * Aviso simple de "ya se generó el cierre diario" — solo el resumen, sin
 * adjuntar el Excel (el encargo original explícitamente separa "Excel +
 * historial navegable" de "notificación", el usuario descarga desde
 * /mesa-servicio/reportes). Se envía tanto a los usuarios con el rol
 * "Supervisor Mesa de Servicio" (Notification::send) como a cada correo
 * suelto de sdp_report_recipient_emails (Notification::route('mail', ...)),
 * ver Modules\MesaServicio\Console\Commands\DailyCloseCommand.
 */
class CierreDiarioGeneradoNotification extends Notification
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
        $periodo = $this->report->periodo->translatedFormat('d/m/Y');
        $metricas = $this->report->resumen_metricas;
        $total = $metricas['total'] ?? 0;

        $mail = (new MailMessage)
            ->subject("Cierre diario de Mesa de Servicio — {$periodo}")
            ->greeting('Hola,')
            ->line("Se generó el cierre diario de Mesa de Servicio correspondiente al {$periodo}.")
            ->line("Total de tickets del día: {$total}.");

        if (Route::has('mesaservicio.reportes.index')) {
            $mail->action('Ver reportes', route('mesaservicio.reportes.index'));
        }

        return $mail->line('El archivo Excel con el detalle completo está disponible para descargar desde la pantalla de Reportes.');
    }
}
