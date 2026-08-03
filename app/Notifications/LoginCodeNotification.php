<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $code)
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
        return (new MailMessage)
            ->subject('Tu código de acceso')
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Usa el siguiente código para iniciar sesión:')
            ->line(new \Illuminate\Support\HtmlString('<div style="font-size:28px;font-weight:bold;letter-spacing:6px;text-align:center;margin:16px 0;">'.$this->code.'</div>'))
            ->line('Este código vence en '.config('security.login_code_ttl_minutes').' minutos.')
            ->line('Si tú no solicitaste este código, ignora este correo.');
    }
}
