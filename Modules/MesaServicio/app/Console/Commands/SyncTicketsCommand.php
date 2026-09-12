<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\TicketFormLink;
use Modules\FormBuilder\Notifications\TicketFormLinkNotification;
use Modules\MesaServicio\Console\Commands\Concerns\PaginatesSdpResults;
use Modules\MesaServicio\Models\SdpSurveyLink;
use Modules\MesaServicio\Models\SdpSurveySetting;
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
 *
 * Fase 6 — encuestas de satisfacción: por cada ticket sincronizado que
 * resuelve a un SdpTicketStatus de tipo "completado" y que todavía no tiene
 * un SdpSurveyLink (así se detecta "por primera vez", en vez de diffear el
 * estado anterior contra el nuevo — más simple y a prueba de que el ticket
 * cambie de estado varias veces en corridas futuras), se genera un
 * TicketFormLink del formulario configurado en SdpSurveySetting y se envía
 * por correo reusando Modules\FormBuilder\Notifications\TicketFormLinkNotification
 * tal cual (sin tocar Modules/FormBuilder). Si no hay formulario configurado
 * (form_id null, valor por defecto) o el ticket no trae solicitante_correo,
 * se omite en silencio. Cualquier error al generar/enviar la encuesta de UN
 * ticket se registra en el log y NO aborta el resto de la sincronización del
 * batch — ver dispatchSurveyIfNewlyCompleted().
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
        // Resuelto una sola vez por corrida (no cambia entre tickets del
        // mismo batch): null si no hay encuesta configurada o si el formulario
        // configurado ya no existe/no está publicado — en ambos casos el
        // disparo automático queda desactivado sin fallar el sync.
        $surveyForm = $this->resolveSurveyForm();

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

                $localTicket = $this->upsertTicket($ticket);
                $total++;

                $this->dispatchSurveyIfNewlyCompleted($localTicket, $surveyForm);

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

    private function upsertTicket(array $ticket): SdpTicket
    {
        $technicianId = $this->resolveTechnicianId($ticket['technician'] ?? null);

        $statusName = $ticket['status']['name'] ?? null;
        $statusId = $statusName !== null
            ? SdpTicketStatus::where('nombre', $statusName)->value('id')
            : null;

        return SdpTicket::updateOrCreate(
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

    /**
     * Formulario de encuesta configurado (Livewire\Catalogos\Destinatarios),
     * ya resuelto a una instancia publicada — null si no hay nada configurado
     * o si el formulario configurado ya no existe/dejó de estar publicado.
     * Se resuelve una sola vez por corrida (no por ticket).
     */
    private function resolveSurveyForm(): ?Form
    {
        $formId = SdpSurveySetting::current()->form_id;

        return $formId ? Form::wherePublished()->find($formId) : null;
    }

    /**
     * Genera y envía la encuesta de satisfacción de un ticket la primera vez
     * que se sincroniza en estado "completado". "Primera vez" se detecta por
     * la ausencia de un SdpSurveyLink para este ticket (no por diffear el
     * estado anterior contra el nuevo) — más simple/robusto y evita reenviar
     * si el ticket vuelve a sincronizarse ya completado en corridas futuras.
     *
     * Envuelto en su propio try/catch: un fallo generando/enviando la
     * encuesta de ESTE ticket se registra en el log y no debe abortar el
     * resto de la sincronización del batch.
     */
    private function dispatchSurveyIfNewlyCompleted(SdpTicket $ticket, ?Form $surveyForm): void
    {
        if (! $surveyForm) {
            return;
        }

        try {
            if ($ticket->ticketStatus?->tipo !== SdpTicketStatus::TIPO_COMPLETADO) {
                return;
            }

            if (empty($ticket->solicitante_correo)) {
                return;
            }

            if (SdpSurveyLink::where('sdp_ticket_id', $ticket->id)->exists()) {
                return;
            }

            [$rawToken, $hash] = TicketFormLink::generateToken();

            DB::transaction(function () use ($surveyForm, $ticket, $rawToken, $hash) {
                $link = TicketFormLink::create([
                    'form_id' => $surveyForm->id,
                    'ticket_number' => $ticket->display_id ?: (string) $ticket->sdp_id,
                    'recipient_email' => $ticket->solicitante_correo,
                    'token_hash' => $hash,
                    'expires_at' => now()->addHours(config('security.ticket_link_ttl_hours')),
                    // Lo dispara este comando, no un usuario interno — a
                    // diferencia de Links\Send::generateLink() (created_by =
                    // auth()->id()), aquí no hay un usuario autenticado.
                    // ticket_form_links.created_by ya es nullable (ver su
                    // migración en Modules/FormBuilder), así que no hizo
                    // falta ninguna migración adicional para permitir esto.
                    'created_by' => null,
                ]);

                SdpSurveyLink::create([
                    'ticket_form_link_id' => $link->id,
                    'sdp_ticket_id' => $ticket->id,
                    'sdp_technician_id' => $ticket->sdp_technician_id,
                ]);

                Notification::route('mail', $link->recipient_email)
                    ->notify(new TicketFormLinkNotification($link, $rawToken));
            });
        } catch (\Throwable $e) {
            Log::error('No se pudo generar/enviar la encuesta de satisfacción del ticket.', [
                'sdp_ticket_id' => $ticket->id,
                'sdp_id' => $ticket->sdp_id,
                'exception' => $e,
            ]);
        }
    }
}
