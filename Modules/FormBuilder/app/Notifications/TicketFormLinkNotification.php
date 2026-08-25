<?php

namespace Modules\FormBuilder\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\FormBuilder\Models\TicketFormLink;

class TicketFormLinkNotification extends Notification
{
    use Queueable;

    public function __construct(protected TicketFormLink $link, protected string $rawToken)
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
        $url = route('formbuilder.public.fill', ['token' => $this->rawToken]);

        return (new MailMessage)
            ->subject('Formulario pendiente — Ticket '.$this->link->ticket_number)
            ->greeting('Hola,')
            ->line('Se te solicita llenar el formulario "'.$this->link->form->name.'" relacionado con el ticket '.$this->link->ticket_number.'.')
            ->line('Al abrir el enlace se te pedirá confirmar este mismo correo antes de mostrar el formulario.')
            ->action('Llenar formulario', $url)
            ->line('Este enlace vence en '.config('security.ticket_link_ttl_hours').' horas y solo puede usarse una vez.');
    }
}
