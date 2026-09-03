<?php

namespace Modules\GestionTI\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\GestionTI\Models\TipoAviso;

/**
 * Reutiliza la infraestructura de notificaciones del core (tabla nativa
 * `notifications`, `App\Livewire\Notifications\Bell`, Reverb+Echo) — mismo
 * patrón exacto de `App\Notifications\AdminMessageNotification`, con `mail`
 * agregado porque un aviso de este módulo también debe llegar por correo.
 *
 * Nota de forma de `toArray()`: se usan las claves 'title'/'message' (no
 * 'mensaje') porque `Bell` ya sabe renderizar genéricamente esas 2 claves
 * (ver resources/views/livewire/notifications/bell.blade.php) — usar un
 * nombre distinto habría dejado el mensaje vacío en la campanita.
 */
class AvisoNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected TipoAviso $tipoAviso,
        protected string $mensaje,
        protected string $entidadRelacionada,
        protected int $entidadId,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->tipoAviso->descripcion)
            ->line($this->mensaje);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->tipoAviso->descripcion,
            'message' => $this->mensaje,
            'tipo_aviso_codigo' => $this->tipoAviso->codigo,
            'entidad_relacionada' => $this->entidadRelacionada,
            'entidad_id' => $this->entidadId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
