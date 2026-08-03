<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(protected Invitation $invitation, protected string $rawToken)
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
        $url = route('invitations.accept', ['token' => $this->rawToken]);

        return (new MailMessage)
            ->subject('Invitación de acceso')
            ->greeting('Hola '.$this->invitation->name.',')
            ->line('Se te ha invitado a ingresar a la aplicación.')
            ->action('Aceptar invitación', $url)
            ->line('Este enlace vence en '.config('security.invitation_ttl_days').' días.');
    }
}
