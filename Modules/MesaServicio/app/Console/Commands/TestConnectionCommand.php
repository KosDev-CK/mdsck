<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Modules\MesaServicio\Services\SdpClient;

class TestConnectionCommand extends Command
{
    protected $signature = 'sdp:test-connection';

    protected $description = 'Verifica la conexión OAuth2 con ServiceDesk Plus Cloud pidiendo un ticket de prueba';

    /**
     * No existe scope OAuth2 dedicado a "technicians" en la API v3 de SDP
     * (confirmado contra la Zoho API Console y la documentación oficial) —
     * la conexión se valida contra "requests", que sí tiene scope propio y
     * ya trae el objeto técnico embebido en cada ticket.
     */
    public function handle(SdpClient $client): int
    {
        try {
            $payload = $client->listRequests(rowCount: 1);
        } catch (\Throwable $e) {
            $this->error("No se pudo conectar con ServiceDesk Plus: {$e->getMessage()}");

            return self::FAILURE;
        }

        $total = $payload['list_info']['total_count'] ?? null;
        $primerTicket = $payload['requests'][0]['subject'] ?? null;

        $this->info(match (true) {
            $total !== null => "Conexión exitosa. Total de tickets en la instancia: {$total}.",
            $primerTicket !== null => "Conexión exitosa. Primer ticket recibido: {$primerTicket}.",
            default => 'Conexión exitosa.',
        });

        return self::SUCCESS;
    }
}
