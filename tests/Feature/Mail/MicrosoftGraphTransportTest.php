<?php

namespace Tests\Feature\Mail;

use App\Mail\Transport\MicrosoftGraphTransport;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MicrosoftGraphTransportTest extends TestCase
{
    protected function makeTransport(): MicrosoftGraphTransport
    {
        return new MicrosoftGraphTransport(
            tenantId: 'tenant-123',
            clientId: 'client-abc',
            clientSecret: 'secret-xyz',
            sender: 'web.master@ck.com.mx',
        );
    }

    protected function makeEmail(): Email
    {
        return (new Email)
            ->from('web.master@ck.com.mx')
            ->to('destino@example.com')
            ->cc('copia@example.com')
            ->subject('Asunto de prueba')
            ->html('<p>Hola</p>')
            ->attach('contenido del archivo', 'nota.txt', 'text/plain');
    }

    public function test_it_sends_an_email_through_microsoft_graph(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'a-token', 'expires_in' => 3599]),
            'graph.microsoft.com/*' => Http::response('', 202),
        ]);

        $this->makeTransport()->send($this->makeEmail());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'login.microsoftonline.com/tenant-123/oauth2/v2.0/token')) {
                return false;
            }

            return $request['grant_type'] === 'client_credentials'
                && $request['client_id'] === 'client-abc'
                && $request['client_secret'] === 'secret-xyz'
                && $request['scope'] === 'https://graph.microsoft.com/.default';
        });

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://graph.microsoft.com/v1.0/users/web.master@ck.com.mx/sendMail') {
                return false;
            }

            $message = $request->data()['message'];

            return $request->hasHeader('Authorization', 'Bearer a-token')
                && $message['subject'] === 'Asunto de prueba'
                && $message['body']['contentType'] === 'HTML'
                && $message['toRecipients'][0]['emailAddress']['address'] === 'destino@example.com'
                && $message['ccRecipients'][0]['emailAddress']['address'] === 'copia@example.com'
                && $message['attachments'][0]['name'] === 'nota.txt'
                && $message['attachments'][0]['contentBytes'] === base64_encode('contenido del archivo');
        });
    }

    public function test_it_reuses_the_cached_token_across_sends(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'a-token', 'expires_in' => 3599]),
            'graph.microsoft.com/*' => Http::response('', 202),
        ]);

        $transport = $this->makeTransport();
        $transport->send($this->makeEmail());
        $transport->send($this->makeEmail());

        Http::assertSentCount(3); // 1 token request + 2 sendMail requests
    }

    public function test_it_throws_when_the_token_request_fails(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(TransportException::class);

        $this->makeTransport()->send($this->makeEmail());
    }

    public function test_it_throws_when_graph_rejects_the_message(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'a-token', 'expires_in' => 3599]),
            'graph.microsoft.com/*' => Http::response(['error' => ['message' => 'ErrorSendAsDenied']], 403),
        ]);

        $this->expectException(TransportException::class);

        $this->makeTransport()->send($this->makeEmail());
    }
}
