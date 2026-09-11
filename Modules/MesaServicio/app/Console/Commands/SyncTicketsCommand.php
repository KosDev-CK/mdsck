<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\MesaServicio\Console\Commands\Concerns\PaginatesSdpResults;
use Modules\MesaServicio\Models\SdpSyncState;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Models\SdpTicket;
use Modules\MesaServicio\Models\SdpTicketStatus;
use Modules\MesaServicio\Services\SdpClient;

/**
 * Sincroniza el espejo local de tickets (sdp_tickets) desde SDP, incremental
 * por marca de agua de "last_updated_time" (SdpSyncState::KEY_TICKETS). Sin
 * marca de agua (primera corrida) trae todo el histórico sin filtro de
 * fecha.
 *
 * Campos y formato de "last_updated_time"/search_criteria verificados contra
 * la instancia real (mdsLandIT, EU) en esta sesión: cada objeto de fecha
 * viene como {"value": "<epoch ms>", "display_value": "..."}, y
 * ['field' => 'last_updated_time', 'condition' => 'after', 'value' => <ms>]
 * es aceptado y filtra correctamente (confirmado con una llamada real vía
 * tinker, reflejado de vuelta en list_info.search_criteria de la respuesta).
 */
class SyncTicketsCommand extends Command
{
    use PaginatesSdpResults;

    protected $signature = 'sdp:sync-tickets';

    protected $description = 'Sincroniza el espejo local de tickets (sdp_tickets) desde ServiceDesk Plus, de forma incremental por marca de agua';

    /**
     * Campos pedidos explícitamente a SDP — todos verificados contra la
     * instancia real (mdsLandIT, EU) en esta sesión, no son un listado a
     * ciegas.
     */
    private const FIELDS_REQUIRED = [
        'id', 'display_id', 'subject', 'requester', 'technician', 'status',
        'category', 'subcategory', 'priority', 'urgency', 'impact',
        'department', 'site', 'mode', 'group', 'request_type',
        'created_time', 'responded_time', 'resolved_time', 'completed_time',
        'due_by_time', 'last_updated_time', 'is_first_response_overdue',
        'is_overdue', 'resolution',
    ];

    public function handle(SdpClient $client): int
    {
        $watermark = SdpSyncState::get(SdpSyncState::KEY_TICKETS);

        // Pequeño margen de seguridad (1 segundo) restando al leer la marca
        // de agua: no se pudo confirmar contra la documentación si la
        // condición "after" de SDP es estrictamente exclusiva o incluyente
        // en el milisegundo exacto — este margen evita perder por un pelo
        // un ticket actualizado justo en el instante de la corrida anterior,
        // a costa de reprocesar (de forma idempotente, sin duplicar) algún
        // ticket ya sincronizado.
        $searchCriteria = $watermark
            ? [[
                'field' => 'last_updated_time',
                'condition' => 'after',
                'value' => (string) $watermark->clone()->subSecond()->getTimestampMs(),
            ]]
            : [];

        $startIndex = 1;
        $rowCount = 100;
        $total = 0;
        $maxLastUpdatedMs = $watermark?->getTimestampMs() ?? 0;

        do {
            try {
                $payload = $client->listRequests($searchCriteria, self::FIELDS_REQUIRED, $startIndex, $rowCount);
            } catch (\Throwable $e) {
                $this->error("No se pudo obtener tickets: {$e->getMessage()}");

                return self::FAILURE;
            }

            $requests = $payload['requests'] ?? [];

            foreach ($requests as $ticket) {
                if (empty($ticket['id'])) {
                    continue;
                }

                $this->upsertTicket($ticket);
                $total++;

                $lastUpdatedMs = (int) ($ticket['last_updated_time']['value'] ?? 0);

                if ($lastUpdatedMs > $maxLastUpdatedMs) {
                    $maxLastUpdatedMs = $lastUpdatedMs;
                }
            }

            $listInfo = $payload['list_info'] ?? [];
            $hasMore = $this->hasMoreRows($listInfo, $startIndex, $rowCount, count($requests));

            $startIndex += $rowCount;
        } while ($hasMore);

        // Solo se avanza la marca de agua si la corrida completa (todas las
        // páginas) terminó sin excepciones y vio al menos un ticket con
        // last_updated_time válido — cero resultados no es un error, pero
        // tampoco debe mover la marca de agua "hacia atrás" a 0.
        if ($maxLastUpdatedMs > 0) {
            SdpSyncState::set(SdpSyncState::KEY_TICKETS, Carbon::createFromTimestampMs($maxLastUpdatedMs));
        }

        $this->info("Tickets sincronizados: {$total}.");

        return self::SUCCESS;
    }

    private function upsertTicket(array $ticket): void
    {
        $technicianId = $this->resolveTechnicianId($ticket['technician'] ?? null);

        $statusName = $ticket['status']['name'] ?? null;
        $statusId = $statusName !== null
            ? SdpTicketStatus::where('nombre', $statusName)->value('id')
            : null;

        SdpTicket::updateOrCreate(
            ['sdp_id' => $ticket['id']],
            [
                'display_id' => $ticket['display_id'] ?? null,
                'asunto' => $ticket['subject'] ?? '',
                'sdp_technician_id' => $technicianId,
                'sdp_ticket_status_id' => $statusId,
                'estado_nombre' => $statusName,
                'categoria' => $ticket['category']['name'] ?? null,
                'subcategoria' => $ticket['subcategory']['name'] ?? null,
                'solicitante_nombre' => $ticket['requester']['name'] ?? null,
                'solicitante_correo' => $ticket['requester']['email_id'] ?? null,
                'departamento' => $ticket['department']['name'] ?? null,
                'sitio' => $ticket['site']['name'] ?? null,
                'prioridad' => $ticket['priority']['name'] ?? null,
                'urgencia' => $ticket['urgency']['name'] ?? null,
                'impacto' => $ticket['impact']['name'] ?? null,
                'tipo_solicitud' => $ticket['request_type']['name'] ?? null,
                'modo' => $ticket['mode']['name'] ?? null,
                'grupo' => $ticket['group']['name'] ?? null,
                // created_time siempre debería venir en el payload real — el
                // fallback a now() es puramente defensivo (evitar un NOT NULL
                // roto en un caso que no debería ocurrir nunca).
                'created_time' => $this->parseEpochMs($ticket['created_time']['value'] ?? null) ?? now(),
                'responded_time' => $this->parseEpochMs($ticket['responded_time']['value'] ?? null),
                'resolved_time' => $this->parseEpochMs($ticket['resolved_time']['value'] ?? null),
                'completed_time' => $this->parseEpochMs($ticket['completed_time']['value'] ?? null),
                'due_time' => $this->parseEpochMs($ticket['due_by_time']['value'] ?? null),
                'primera_respuesta_vencida' => (bool) ($ticket['is_first_response_overdue'] ?? false),
                'vencido' => (bool) ($ticket['is_overdue'] ?? false),
                'resolucion' => $ticket['resolution']['content'] ?? null,
                'raw_payload' => $ticket,
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Crea el técnico al vuelo si no existe todavía (mismo dato embebido que
     * ya usa sdp:sync-technicians) — deliberadamente sin 'activo' ni
     * 'es_nivel_1' en el array de atributos, igual cuidado que
     * SyncTechniciansCommand: este comando no es responsable de la
     * activación/inactivación del catálogo de técnicos (eso lo decide
     * exclusivamente sdp:sync-technicians), y 'es_nivel_1' es el único campo
     * editado manualmente desde la pantalla del catálogo, nunca se
     * sobreescribe desde un comando de sync.
     */
    private function resolveTechnicianId(?array $technician): ?int
    {
        if (empty($technician['id'])) {
            return null;
        }

        return SdpTechnician::updateOrCreate(
            ['sdp_id' => $technician['id']],
            [
                'nombre' => $technician['name'] ?? '',
                'correo' => $technician['email_id'] ?? null,
                'puesto' => $technician['job_title'] ?? null,
            ]
        )->id;
    }

    private function parseEpochMs(null|int|string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $value);
    }
}
