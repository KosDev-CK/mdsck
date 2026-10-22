<?php

namespace Modules\GestionTI\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Modules\GestionTI\Models\SolicitudProveedor;

/**
 * Avisa a quien creó una Solicitud a Proveedores dirigida a un proveedor
 * INTERNO (un área) que esa área confirmó la entrega, para que haga la
 * Recepción. Mismo patrón y claves `title`/`message` que `AvisoNotification`
 * (la campanita las renderiza genéricamente).
 */
class EntregaInternaConfirmadaNotification extends Notification
{
    use Queueable;

    public function __construct(protected SolicitudProveedor $solicitud, protected string $confirmadaPor)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $area = $this->solicitud->vendor?->nombre_comercial ?? 'El área';

        return [
            'title' => 'Entrega confirmada — '.$this->solicitud->folio,
            'message' => "{$area} confirmó la entrega de la solicitud {$this->solicitud->folio} ({$this->confirmadaPor}). Ya puedes hacer la Recepción.",
            'entidad_relacionada' => 'SolicitudProveedor',
            'entidad_id' => $this->solicitud->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
