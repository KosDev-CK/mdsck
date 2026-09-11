<?php

namespace Modules\MesaServicio\Console\Commands\Concerns;

/**
 * Criterio de paginación compartido por los comandos de sincronización
 * contra la API v3 de SDP (sdp:sync-ticket-statuses, sdp:sync-technicians,
 * sdp:sync-tickets) — antes vivía duplicado como método protegido idéntico
 * en cada comando; extraído a este trait al escribir el tercer comando
 * (Fase 2) para no repetirlo una vez más.
 */
trait PaginatesSdpResults
{
    /**
     * SDP no siempre confirma explícitamente "has_more_rows" — si viene, se
     * usa tal cual; si no, se infiere comparando contra "total_count". Una
     * página vacía siempre corta la paginación (red de seguridad contra
     * loops infinitos si el payload no trae ninguno de los dos campos).
     */
    protected function hasMoreRows(array $listInfo, int $startIndex, int $rowCount, int $itemsReturned): bool
    {
        if ($itemsReturned === 0) {
            return false;
        }

        if (array_key_exists('has_more_rows', $listInfo)) {
            return (bool) $listInfo['has_more_rows'];
        }

        if (array_key_exists('total_count', $listInfo)) {
            return ($startIndex - 1 + $itemsReturned) < $listInfo['total_count'];
        }

        return false;
    }
}
