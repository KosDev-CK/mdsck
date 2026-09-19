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
use Modules\MesaServicio\Models\SdpSite;
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
 * ['field' => 'last_updated_time', 'condition' => 'greater than', 'value' => <ms>]
 * es aceptado y filtra correctamente (confirmado con una llamada real vía
 * tinker, reflejado de vuelta en list_info.search_criteria de la respuesta).
 *
 * IMPORTANTE (2026-09-17): la condición "after" que se usó originalmente
 * aquí NO es un valor válido de search_criteria en SDP — SDP la acepta sin
 * error y la refleja de vuelta en list_info.search_criteria, pero
 * SILENCIOSAMENTE NO filtra nada (confirmado contra la instancia real vía
 * tinker: con "after" se seguían recibiendo tickets de 2022 aun filtrando
 * por una fecha de corte de septiembre 2026). Las condiciones documentadas
 * por SDP son: is, is not, lesser than, greater than, lesser or equal,
 * greater or equal, contains, not contains, starts with, ends with, between.
 * "greater than" fue probado con la misma forma de campo/valor y sí
 * filtra correctamente. No reintroducir "after"/"before".
 *
 * Opción `--desde=Y-m-d`: resincronización manual acotada (backfill de
 * prueba o correctivo), pensada para NO esperar el histórico completo de la
 * primera corrida. Cuando se usa, reemplaza por completo el criterio de
 * búsqueda (no se combina con la marca de agua) por
 * ['field' => 'created_time', 'condition' => 'greater than', 'value' => <ms del
 * inicio del día indicado>] — mismo formato de search_criteria ya usado por
 * la marca de agua y por SyncTechniciansCommand — y al terminar NO se toca
 * SdpSyncState::KEY_TICKETS, para no adelantar ni alterar la marca de agua
 * incremental real con una corrida manual/acotada.
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
 *
 * Fase 8 — folios combinados vía historial por-ticket: por cada ticket que
 * este comando efectivamente upsertea en la corrida (es decir, cada ticket
 * cuyo last_updated_time cambió — una fusión SIEMPRE toca ese campo en el
 * ticket ABSORBENTE), se consulta su historial
 * (SdpClient::getRequestHistory()) buscando entradas
 * `operation === 'merge_with'`. `description` de esa entrada es el
 * display_id del ticket ABSORBIDO — si existe localmente (se capturó antes
 * de fusionarse), se marca con el estado local "Combinado"
 * (SdpTicketStatus::TIPO_COMPLETADO), `completed_time` = el timestamp del
 * evento de fusión, y se registra qué ticket lo absorbió
 * (combinado_con_display_id) y cuándo se detectó (combinado_detectado_en).
 * Si el ticket absorbido nunca se sincronizó localmente, no hay nada que
 * marcar (no se crea un placeholder). Ver detectMergesFromHistory() — mismo
 * criterio de resiliencia por ticket que dispatchSurveyIfNewlyCompleted():
 * un fallo aquí se registra en el log y no aborta el resto del batch.
 */
class SyncTicketsCommand extends Command
{
    use PaginatesSdpResults;

    protected $signature = 'sdp:sync-tickets {--desde= : Fecha (Y-m-d) desde la cual sincronizar manualmente, ignorando la marca de agua incremental — para una resincronización acotada (backfill de prueba o correctivo)}';

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
        // Fase 8 — campos nuevos confirmados contra la instancia real.
        'item', 'service_category', 'level', 'assigned_time', 'time_elapsed',
        // Fase 8 (Parte 3) — campos personalizados (UDF), ver mapeo completo
        // en upsertTicket().
        'udf_fields',
    ];

    public function handle(SdpClient $client): int
    {
        // Resuelto una sola vez por corrida (no cambia entre tickets del
        // mismo batch): null si no hay encuesta configurada o si el formulario
        // configurado ya no existe/no está publicado — en ambos casos el
        // disparo automático queda desactivado sin fallar el sync.
        $surveyForm = $this->resolveSurveyForm();

        // Fase 8 — resuelto una sola vez por corrida, igual criterio que
        // $surveyForm: null si el estado local "Combinado" todavía no está
        // sembrado (module:seed pendiente) — en ese caso se sigue marcando
        // estado_nombre='Combinado' y las columnas combinado_*, solo queda
        // sin FK de estado hasta que se siembre.
        $combinadoStatusId = SdpTicketStatus::where('nombre', 'Combinado')->value('id');

        $desde = $this->option('desde');

        if ($desde !== null) {
            try {
                $desdeInicioDia = Carbon::parse($desde)->startOfDay();
            } catch (\Throwable) {
                $this->error("Fecha inválida: {$desde}. Usa el formato Y-m-d.");

                return self::FAILURE;
            }
        }

        $watermark = SdpSyncState::get(SdpSyncState::KEY_TICKETS);

        // Pequeño margen de seguridad (1 segundo) restando al leer la marca
        // de agua: no se pudo confirmar contra la documentación si la
        // condición "greater than" de SDP es estrictamente exclusiva o
        // incluyente en el milisegundo exacto — este margen evita perder
        // por un pelo un ticket actualizado justo en el instante de la
        // corrida anterior, a costa de reprocesar (de forma idempotente,
        // sin duplicar) algún ticket ya sincronizado.
        //
        // --desde toma el control por completo del criterio de búsqueda
        // (backfill manual acotado) e ignora la marca de agua guardada, sin
        // combinarla con esta.
        $searchCriteria = match (true) {
            $desde !== null => [[
                'field' => 'created_time',
                'condition' => 'greater than',
                'value' => (string) $desdeInicioDia->getTimestampMs(),
            ]],
            (bool) $watermark => [[
                'field' => 'last_updated_time',
                'condition' => 'greater than',
                'value' => (string) $watermark->clone()->subSecond()->getTimestampMs(),
            ]],
            default => [],
        };

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
                $this->detectMergesFromHistory($client, $localTicket, $combinadoStatusId);

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
        // tampoco debe mover la marca de agua "hacia atrás" a 0. Una corrida
        // manual acotada con --desde nunca toca la marca de agua incremental.
        if ($desde === null && $maxLastUpdatedMs > 0) {
            SdpSyncState::set(SdpSyncState::KEY_TICKETS, Carbon::createFromTimestampMs($maxLastUpdatedMs));
        }

        $this->info("Tickets sincronizados: {$total}.");

        return self::SUCCESS;
    }

    private function upsertTicket(array $ticket): SdpTicket
    {
        $technicianId = $this->resolveTechnicianId($ticket['technician'] ?? null);
        $siteId = $this->resolveSiteId($ticket['site'] ?? null);

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
                // Fase 8 — campos nuevos confirmados.
                'articulo' => $ticket['item']['name'] ?? null,
                'categoria_servicio' => $ticket['service_category']['name'] ?? null,
                'nivel' => $ticket['level']['name'] ?? null,
                'resuelto_por' => $ticket['resolution']['submitted_by']['name'] ?? null,
                'solicitante_nombre' => $ticket['requester']['name'] ?? null,
                'solicitante_correo' => $ticket['requester']['email_id'] ?? null,
                'departamento' => $ticket['department']['name'] ?? null,
                'sitio' => $ticket['site']['name'] ?? null,
                'sdp_site_id' => $siteId,
                'prioridad' => $ticket['priority']['name'] ?? null,
                'urgencia' => $ticket['urgency']['name'] ?? null,
                'impacto' => $ticket['impact']['name'] ?? null,
                'tipo_solicitud' => $ticket['request_type']['name'] ?? null,
                'modo' => $ticket['mode']['name'] ?? null,
                'grupo' => $ticket['group']['name'] ?? null,
                // Fase 8 (Parte 3) — campos personalizados (UDF), mapeo
                // confirmado por el administrador real de SDP (ver
                // docs/mesaservicio-progreso.md). udf_charN llega como string
                // plano (o null); udf_dateN llega con el mismo shape
                // {value, display_value} que las demás fechas.
                'area_operativa' => $ticket['udf_fields']['udf_char24'] ?? null,
                'grupo_resolutor' => $ticket['udf_fields']['udf_char10'] ?? null,
                'super_categoria' => $ticket['udf_fields']['udf_char3'] ?? null,
                'n3_area_escalamiento' => $ticket['udf_fields']['udf_char11'] ?? null,
                'n3_fecha_escalamiento' => $this->parseEpochMs($ticket['udf_fields']['udf_date1']['value'] ?? null),
                'n3_fecha_solucion' => $this->parseEpochMs($ticket['udf_fields']['udf_date3']['value'] ?? null),
                'n3_no_seguimiento_proveedor' => $ticket['udf_fields']['udf_char13'] ?? null,
                'n3_recurso_escalamiento' => $ticket['udf_fields']['udf_char12'] ?? null,
                'n4_area_escalamiento' => $ticket['udf_fields']['udf_char14'] ?? null,
                'n4_fecha_escalamiento' => $this->parseEpochMs($ticket['udf_fields']['udf_date2']['value'] ?? null),
                'n4_fecha_solucion' => $this->parseEpochMs($ticket['udf_fields']['udf_date4']['value'] ?? null),
                'n4_no_seguimiento_proveedor' => $ticket['udf_fields']['udf_char16'] ?? null,
                'n4_recurso_escalamiento' => $ticket['udf_fields']['udf_char15'] ?? null,
                // created_time siempre debería venir en el payload real — el
                // fallback a now() es puramente defensivo (evitar un NOT NULL
                // roto en un caso que no debería ocurrir nunca).
                'created_time' => $this->parseEpochMs($ticket['created_time']['value'] ?? null) ?? now(),
                'responded_time' => $this->parseEpochMs($ticket['responded_time']['value'] ?? null),
                'resolved_time' => $this->parseEpochMs($ticket['resolved_time']['value'] ?? null),
                'completed_time' => $this->parseEpochMs($ticket['completed_time']['value'] ?? null),
                'due_time' => $this->parseEpochMs($ticket['due_by_time']['value'] ?? null),
                'assigned_time' => $this->parseEpochMs($ticket['assigned_time']['value'] ?? null),
                // time_elapsed llega como string plano en MILISEGUNDOS (ej.
                // "7984340000" = ~92 días), NO envuelto en {value,
                // display_value} como las demás fechas, y NO en segundos como
                // se asumió originalmente al agregar este campo — esa lectura
                // se basó en una única muestra ("256000") ambigua entre
                // segundos (71h, plausible) y milisegundos (4.3 min, también
                // plausible), y solo se detectó el error real en producción
                // el 2026-09-18 al desbordar la columna unsignedInteger con
                // un ticket de larga duración (7,984,340,000 no cabe ni
                // interpretado como segundos ni como milisegundos crudos en
                // la columna — el valor correcto tras dividir entre 1000 sí
                // cabe cómodo). Ver parseElapsedSeconds().
                'tiempo_transcurrido_segundos' => $this->parseElapsedSeconds($ticket['time_elapsed'] ?? null),
                'primera_respuesta_vencida' => (bool) ($ticket['is_first_response_overdue'] ?? false),
                'vencido' => (bool) ($ticket['is_overdue'] ?? false),
                'resolucion' => $ticket['resolution']['content'] ?? null,
                'raw_payload' => $ticket,
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * Crea el sitio al vuelo si no existe todavía, del objeto "site"
     * embebido en el ticket (solo trae id+name, a diferencia del catálogo
     * completo que trae sdp:sync-sites) — mismo criterio perezoso que
     * resolveTechnicianId(). No sobreescribe las columnas geográficas ricas
     * (pais/estado/región/etc.) que solo llena sdp:sync-sites, solo el
     * nombre.
     */
    private function resolveSiteId(?array $site): ?int
    {
        if (empty($site['id'])) {
            return null;
        }

        return SdpSite::updateOrCreate(
            ['sdp_id' => $site['id']],
            ['nombre' => $site['name'] ?? '']
        )->id;
    }

    private function parseElapsedSeconds(null|int|string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        // $value viene en milisegundos (ver comentario en upsertTicket()) —
        // se divide entre 1000 para guardar segundos, que es lo que dice la
        // columna (tiempo_transcurrido_segundos) y lo que espera el resto
        // del módulo.
        return (int) ((int) $value / 1000);
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

    /**
     * Fase 8 — recorre TODO el historial (paginado) del ticket recién
     * sincronizado buscando entradas `operation === 'merge_with'`, cada una
     * de las cuales indica que este ticket (el "absorbente") fusionó a otro
     * ticket cuyo display_id viene en `description`. Envuelto en su propio
     * try/catch, igual criterio de resiliencia que
     * dispatchSurveyIfNewlyCompleted(): un fallo obteniendo/procesando el
     * historial de ESTE ticket se registra en el log y no aborta el resto
     * de la sincronización del batch — es un enriquecimiento "nice to have",
     * no parte de la corrección central del sync.
     */
    private function detectMergesFromHistory(SdpClient $client, SdpTicket $ticketAbsorbente, ?int $combinadoStatusId): void
    {
        try {
            $startIndex = 1;
            $rowCount = 100;

            do {
                $payload = $client->getRequestHistory($ticketAbsorbente->sdp_id, $startIndex, $rowCount);

                $entries = $payload['history'] ?? [];

                foreach ($entries as $entry) {
                    if (($entry['operation'] ?? null) !== 'merge_with') {
                        continue;
                    }

                    $this->marcarTicketComoCombinado($entry, $ticketAbsorbente, $combinadoStatusId);
                }

                $listInfo = $payload['list_info'] ?? [];
                $hasMore = $this->hasMoreRows($listInfo, $startIndex, $rowCount, count($entries));

                $startIndex += $rowCount;
            } while ($hasMore);
        } catch (\Throwable $e) {
            Log::error('No se pudo obtener/procesar el historial del ticket para detectar folios combinados.', [
                'sdp_ticket_id' => $ticketAbsorbente->id,
                'sdp_id' => $ticketAbsorbente->sdp_id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * `description` de una entrada `merge_with` es el display_id (folio) del
     * ticket ABSORBIDO, no su id interno. Si nunca se capturó localmente
     * (no existe una fila con ese display_id) no hay nada que hacer — no se
     * crea un placeholder. Si ya estaba marcado como combinado en una
     * corrida anterior, se omite (evita escrituras redundantes en cada
     * corrida hourly, aunque reescribir el mismo valor sería inofensivo).
     */
    private function marcarTicketComoCombinado(array $entry, SdpTicket $ticketAbsorbente, ?int $combinadoStatusId): void
    {
        $displayIdAbsorbido = $entry['description'] ?? null;

        if (empty($displayIdAbsorbido)) {
            return;
        }

        $ticketAbsorbido = SdpTicket::where('display_id', $displayIdAbsorbido)->first();

        if (! $ticketAbsorbido || $ticketAbsorbido->combinado_con_display_id !== null) {
            return;
        }

        $ticketAbsorbido->update([
            'sdp_ticket_status_id' => $combinadoStatusId,
            'estado_nombre' => 'Combinado',
            'completed_time' => $this->parseEpochMs($entry['time']['value'] ?? null),
            'combinado_con_display_id' => $ticketAbsorbente->display_id,
            'combinado_detectado_en' => now(),
        ]);
    }
}
