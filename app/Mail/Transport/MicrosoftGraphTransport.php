<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\TextPart;

/**
 * Sends mail through the Microsoft Graph "sendMail" endpoint using an
 * app-only (client credentials) Azure AD token, instead of SMTP — Microsoft
 * has been retiring SMTP AUTH with basic auth, and Graph is the replacement
 * they recommend. One Azure App Registration (its own tenant/client
 * id/secret) is expected per site, configured via config/services.php.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $sender,
        private readonly ?string $proxy = null,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('El transporte de Microsoft Graph solo puede enviar mensajes Symfony\Component\Mime\Email.');
        }

        $response = $this->httpClient()
            ->withToken($this->accessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$this->sender}/sendMail", [
                'message' => $this->buildMessagePayload($email),
                'saveToSentItems' => false,
            ]);

        if ($response->failed()) {
            throw new TransportException("Microsoft Graph rechazó el envío ({$response->status()}): {$response->body()}");
        }
    }

    protected function accessToken(): string
    {
        return Cache::remember(
            "microsoft-graph-mail-token:{$this->tenantId}:{$this->clientId}",
            now()->addMinutes(50),
            function () {
                $response = $this->httpClient()
                    ->asForm()
                    ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => 'https://graph.microsoft.com/.default',
                    ]);

                if ($response->failed()) {
                    throw new TransportException("No se pudo obtener el token de Azure AD ({$response->status()}): {$response->body()}");
                }

                return $response->json('access_token');
            }
        );
    }

    /**
     * Cliente HTTP para hablar con Azure AD/Graph — pasa por un forward proxy
     * (AZURE_MAIL_HTTP_PROXY) cuando este servidor no tiene salida directa a
     * internet. Ver docs/correo-oauth2-azure.md.
     */
    protected function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->proxy
            ? Http::withOptions(['proxy' => $this->proxy])
            : Http::withOptions([]);
    }

    protected function buildMessagePayload(Email $email): array
    {
        return [
            'subject' => (string) $email->getSubject(),
            'body' => [
                'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
            ],
            'from' => $this->addressPayload($email->getFrom()[0] ?? null),
            'toRecipients' => $this->addressListPayload($email->getTo()),
            'ccRecipients' => $this->addressListPayload($email->getCc()),
            'bccRecipients' => $this->addressListPayload($email->getBcc()),
            'replyTo' => $this->addressListPayload($email->getReplyTo()),
            'attachments' => $this->attachmentsPayload($email),
        ];
    }

    protected function addressListPayload(array $addresses): array
    {
        return array_values(array_filter(array_map($this->addressPayload(...), $addresses)));
    }

    protected function addressPayload(?Address $address): ?array
    {
        if (! $address) {
            return null;
        }

        return [
            'emailAddress' => array_filter([
                'address' => $address->getAddress(),
                'name' => $address->getName() ?: null,
            ]),
        ];
    }

    protected function attachmentsPayload(Email $email): array
    {
        return array_map(fn (TextPart $attachment) => [
            '@odata.type' => '#microsoft.graph.fileAttachment',
            'name' => $attachment->getName() ?? 'attachment',
            'contentType' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            'contentBytes' => base64_encode($attachment->getBody()),
        ], $email->getAttachments());
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
