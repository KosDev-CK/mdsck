<?php

namespace Modules\GestionTI\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\GestionTI\Models\SolicitudProveedor;

/**
 * Envío/reenvío al proveedor de una Solicitud a Proveedor (ver
 * docs/gestionti-progreso.md) — enviado desde
 * `Compras\SolicitudesProveedor::enviarAProveedor()` con
 * `Mail::to($proveedor->contacto_correo)->send(...)`, no una `Notification`
 * (el destinatario es externo — un proveedor, no un `User` del sistema — así
 * que no aplica la infraestructura de notificaciones del core). Vista
 * Markdown (`gestionti::mail.solicitud-proveedor`), mismo mailer ya
 * configurado del proyecto (`graph`/`smtp` según `MAIL_MAILER`), sin nada
 * nuevo que configurar.
 *
 * Adjunta el mismo PDF que genera `SolicitudProveedorPdfController` (misma
 * vista `gestionti::pdf.solicitud-proveedor`) — el proveedor lo imprime y
 * lo entrega junto con la mercancía, para impresión/entrega.
 */
class SolicitudProveedorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudProveedor $solicitud)
    {
    }

    public function build(): self
    {
        return $this->subject("Solicitud de compra {$this->solicitud->folio}")
            ->markdown('gestionti::mail.solicitud-proveedor', [
                'solicitud' => $this->solicitud,
            ]);
    }

    /**
     * API de adjuntos "nueva" de Mailable (en vez de `attachData()` dentro
     * de `build()`) a propósito: `Mail::fake()` nunca llama a `build()`
     * (ver `Illuminate\Support\Testing\Fakes\MailFake::sendMail()`), así
     * que un adjunto armado ahí sería imposible de verificar en tests. Este
     * método sí se evalúa bajo `hasAttachment()`/`assertSent()`, por eso
     * vive aparte.
     *
     * @return array<int, \Illuminate\Mail\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => Pdf::loadView('gestionti::pdf.solicitud-proveedor', [
                    'solicitud' => $this->solicitud,
                ])->output(),
                "solicitud-proveedor-{$this->solicitud->folio}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
