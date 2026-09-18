<?php

namespace Modules\MesaServicio\Console\Commands;

use Illuminate\Console\Command;
use Modules\MesaServicio\Console\Commands\Concerns\PaginatesSdpResults;
use Modules\MesaServicio\Models\SdpTechnician;
use Modules\MesaServicio\Services\SdpClient;

/**
 * Fase 8 (Parte 1) — sdp:sync-technicians deriva el catálogo local del
 * recurso real /users (API v3, scope SDPOnDemand.users.ALL) filtrado por
 * `search_criteria` `is_technician = true`, en vez de escanear el objeto
 * "technician" embebido en los tickets de los últimos 12 meses (criterio
 * anterior, Fase 1/2 — se abandonó porque no detectaba técnicos sin ningún
 * ticket asignado todavía, y no distinguía si el técnico tiene un login real
 * en SDP).
 *
 * `zuid` (id de cuenta de login de Zoho/SDP) es el dato nuevo clave: un valor
 * numérico real indica que el técnico tiene un login funcional; el string
 * literal "-1" indica que el usuario está marcado como técnico en el sentido
 * de rol de SDP pero SIN acceso real — confirmado con ejemplos reales de la
 * instancia. `tiene_acceso_sdp` se deriva de esto (true solo si `zuid` viene
 * y no es "-1").
 *
 * El criterio de inactivación no cambia de forma (sigue siendo "activo=true
 * que no aparece en esta corrida => se marca inactivo"), solo cambia la
 * fuente de la que se deduce qué técnicos "aparecieron" — ahora son los
 * usuarios que SDP marca con is_technician=true AHORA MISMO, no los que
 * tuvieron actividad de tickets en los últimos 12 meses. Es una señal más
 * precisa de "¿sigue siendo un rol de técnico en SDP?", que es lo que
 * `activo` siempre ha significado en este catálogo.
 */
class SyncTechniciansCommand extends Command
{
    use PaginatesSdpResults;

    protected $signature = 'sdp:sync-technicians';

    protected $description = 'Sincroniza el catálogo local de técnicos (sdp_technicians) desde el recurso /users de ServiceDesk Plus (is_technician=true)';

    public function handle(SdpClient $client): int
    {
        $searchCriteria = [
            ['field' => 'is_technician', 'condition' => 'is', 'value' => true],
        ];

        $startIndex = 1;
        $rowCount = 100;
        $seenSdpIds = [];
        $usersSeen = 0;

        do {
            try {
                $payload = $client->listUsers($searchCriteria, [], $startIndex, $rowCount);
            } catch (\Throwable $e) {
                $this->error("No se pudo obtener el catálogo de técnicos: {$e->getMessage()}");

                return self::FAILURE;
            }

            $users = $payload['users'] ?? [];

            foreach ($users as $user) {
                if (empty($user['id'])) {
                    continue;
                }

                $zuid = $user['zuid'] ?? null;
                $tieneAccesoSdp = $zuid !== null && $zuid !== '' && (string) $zuid !== '-1';

                SdpTechnician::updateOrCreate(
                    ['sdp_id' => $user['id']],
                    [
                        'nombre' => $user['name'] ?? '',
                        'correo' => $user['email_id'] ?? null,
                        'puesto' => $user['job_title'] ?? null,
                        'zuid' => $zuid !== null ? (string) $zuid : null,
                        'tiene_acceso_sdp' => $tieneAccesoSdp,
                        'activo' => true,
                    ]
                );

                $seenSdpIds[] = $user['id'];
            }

            $usersSeen += count($users);

            $listInfo = $payload['list_info'] ?? [];
            $hasMore = $this->hasMoreRows($listInfo, $startIndex, $rowCount, count($users));

            $startIndex += $rowCount;
        } while ($hasMore);

        // Mismo cuidado que la versión anterior (basada en tickets): dedupe
        // antes del whereNotIn() para no reventar el límite de placeholders
        // de MySQL — aquí el volumen ya viene deduplicado por SDP en sí
        // mismo (cada usuario aparece una sola vez en /users), pero se
        // conserva la deduplicación explícita como red de seguridad barata.
        $uniqueSdpIds = array_values(array_unique($seenSdpIds));

        $inactivated = SdpTechnician::where('activo', true)
            ->whereNotIn('sdp_id', $uniqueSdpIds)
            ->update(['activo' => false]);

        $this->info(sprintf(
            'Técnicos revisados: %d. Marcados inactivos: %d.',
            $usersSeen,
            $inactivated
        ));

        return self::SUCCESS;
    }
}
