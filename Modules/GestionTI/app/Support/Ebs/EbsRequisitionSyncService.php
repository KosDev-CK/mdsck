<?php

namespace Modules\GestionTI\Support\Ebs;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Support\Avisos\AvisoDispatcher;

/**
 * Sincronización real EBS -> réplica local -> `SolicitudSicBorrador` (Fase
 * 5, punto 1). Ver docs/gestionti-progreso.md para el diseño completo.
 *
 * Ninguno de los 2 métodos públicos captura excepciones de
 * `EbsRequisitionsClient` — se propagan tal cual, a propósito: es cada
 * llamador (los 3 comandos `gestionti:ebs-*`) quien decide si loggea y
 * continúa (caso normal, "el API está caída no debe tronar nada del flujo
 * manual") o si tolera un solo día fallido dentro de un rango más largo
 * (backfill).
 */
class EbsRequisitionSyncService
{
    public function __construct(
        private readonly EbsRequisitionsClient $client,
        private readonly AvisoDispatcher $avisoDispatcher,
    ) {
    }

    /**
     * "requisition_header_line" — upsert de cabecera + líneas (delete +
     * recrear, mismo patrón ya usado en Configuración de Avisos para los
     * destinatarios), e intento de vinculación automática. Nunca dispara
     * avisos (solo `sincronizarAprobadas()` lo hace).
     */
    public function sincronizarCreadas(int $daysOffset): void
    {
        $requisiciones = $this->client->obtenerCreadas($daysOffset);

        foreach ($requisiciones as $data) {
            $this->upsertCreada($data);
        }
    }

    /**
     * "requisition_header_approved" — upsert SOLO de los campos que trae
     * este método (status, action_code, action_date, approver_user,
     * approver_name, approver_date, sequence_num), reemplazo de `notes[]`,
     * intento de vinculación, y — solo si `$dispararAvisos` — el aviso
     * `SIC_AUTORIZADA`/`SIC_RECHAZADA` cuando la SIC vinculada transiciona
     * realmente de estatus en esta corrida (idempotente: correr el comando
     * 2 veces el mismo día no duplica el aviso).
     */
    public function sincronizarAprobadas(int $daysOffset, bool $dispararAvisos = true): void
    {
        $requisiciones = $this->client->obtenerAprobadas($daysOffset);

        foreach ($requisiciones as $data) {
            $this->upsertAprobada($data, $dispararAvisos);
        }
    }

    /**
     * Vinculación manual (pantalla "SIC en EBS") — mismo mapeo de estatus
     * que la vinculación automática, sin disparar avisos (no es uno de los
     * disparadores documentados, y el usuario ya está viendo la pantalla en
     * tiempo real).
     */
    public function vincularManualmente(SolicitudSicBorrador $solicitud, EbsRequisition $ebsRequisicion): void
    {
        $solicitud->ebs_requisition_id = $ebsRequisicion->id;

        $nuevoEstatus = self::mapearEstatusLocal($ebsRequisicion->status ?? '', $solicitud->estatus);

        if ($nuevoEstatus) {
            $solicitud->estatus = $nuevoEstatus;
        }

        $solicitud->save();
    }

    /**
     * Mapeo de estatus EBS -> local. `IN PROCESS` solo avanza si el estatus
     * local sigue en `capturado` (nunca retrocede un estatus ya avanzado).
     * `APPROVED`/`REJECTED` siempre resuelven a un valor fijo. Cualquier
     * otro valor de EBS: `null` (no cambia nada, no truena, no asume).
     */
    public static function mapearEstatusLocal(string $statusEbs, string $estatusLocalActual): ?string
    {
        return match ($statusEbs) {
            'IN PROCESS' => $estatusLocalActual === SolicitudSicBorrador::ESTATUS_CAPTURADO
                ? SolicitudSicBorrador::ESTATUS_SIC_CREADA
                : null,
            'APPROVED' => SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'REJECTED' => SolicitudSicBorrador::ESTATUS_RECHAZADA,
            default => null,
        };
    }

    private function upsertCreada(array $data): void
    {
        $headerId = $data['requisitionHeaderId'] ?? null;

        if (! $headerId) {
            return;
        }

        DB::transaction(function () use ($data, $headerId) {
            $requisicion = $data['requisition'] ?? [];
            $wf = $data['wf'] ?? [];
            $action = $data['action'] ?? [];
            $approver = $data['approver'] ?? [];
            $create = $data['create'] ?? [];
            $organization = $data['organization'] ?? [];

            // Los datos de EBS SIEMPRE pisan lo que hubiera en la réplica,
            // sin excepción.
            $ebsRequisicion = EbsRequisition::updateOrCreate(
                ['requisition_header_id' => $headerId],
                [
                    'code' => $requisicion['code'] ?? null,
                    'description' => $requisicion['description'] ?? null,
                    'status' => $requisicion['status'] ?? null,
                    'fecha_creacion' => $this->nullableDate($requisicion['date'] ?? null),
                    'wf_item_key' => $wf['itemKey'] ?? null,
                    'wf_item_type' => $wf['itemType'] ?? null,
                    'organization_code' => $organization['code'] ?? null,
                    'organization_description' => $organization['description'] ?? null,
                    'created_by_user' => $create['user'] ?? null,
                    // Typo real del proveedor ("decription"), ver
                    // EbsRequisitionsClient.
                    'created_by_description' => $create['decription'] ?? null,
                    'sequence_num' => $data['sequenceNum'] ?? null,
                    'approver_user' => $approver['user'] ?? null,
                    'approver_name' => $approver['name'] ?? null,
                    'approver_date' => $this->nullableDate($approver['date'] ?? null),
                    'action_code' => $action['code'] ?? null,
                    'action_date' => $this->nullableDate($action['date'] ?? null),
                    'ultima_sincronizacion_creadas_at' => now(),
                ]
            );

            $ebsRequisicion->lines()->delete();

            foreach (($data['requisition_lines'] ?? []) as $line) {
                // Typo real del proveedor ("requsition_line_id"), ver
                // EbsRequisitionsClient. Sin este id no hay forma de
                // identificar la línea de forma estable — se descarta.
                if (! isset($line['requsition_line_id'])) {
                    continue;
                }

                $ebsRequisicion->lines()->create([
                    'requisition_line_id' => $line['requsition_line_id'],
                    'line_number' => $line['lineNumber'] ?? null,
                    'line_type_id' => $line['lineTypeId'] ?? null,
                    'category_id' => $line['categoryId'] ?? null,
                    'item_id' => $line['itemId'] ?? null,
                    'item_description' => $line['itemDescription'] ?? null,
                    'unit_measurement' => $line['unitMeasurement'] ?? null,
                    'unit_price' => $line['unitPrice'] ?? null,
                    'quantity' => $line['quantity'] ?? null,
                    'currency_code' => $line['currencyCode'] ?? null,
                ]);
            }

            // Nunca dispara avisos, aunque el mapeo resultante avance el
            // estatus (ej. una requisición que ya llega "APPROVED" el mismo
            // día que se crea) — solo `sincronizarAprobadas()` lo hace.
            $this->vincularYActualizarEstatus($ebsRequisicion);
        });
    }

    private function upsertAprobada(array $data, bool $dispararAvisos): void
    {
        $headerId = $data['requisitionHeaderId'] ?? null;

        if (! $headerId) {
            return;
        }

        DB::transaction(function () use ($data, $dispararAvisos, $headerId) {
            $requisicion = $data['requisition'] ?? [];
            $action = $data['action'] ?? [];
            $approver = $data['approver'] ?? [];

            // Upsert SOLO de los campos que trae este método — a propósito
            // no toca code/description/wf_*/organization_*: esos ya deben
            // existir de una corrida previa de sincronizarCreadas() (que se
            // corre primero, siempre) — ver docs/gestionti-progreso.md.
            $ebsRequisicion = EbsRequisition::updateOrCreate(
                ['requisition_header_id' => $headerId],
                [
                    'status' => $requisicion['status'] ?? null,
                    'action_code' => $action['code'] ?? null,
                    'action_date' => $this->nullableDate($action['date'] ?? null),
                    'approver_user' => $approver['user'] ?? null,
                    'approver_name' => $approver['name'] ?? null,
                    'approver_date' => $this->nullableDate($approver['date'] ?? null),
                    'sequence_num' => $data['sequenceNum'] ?? null,
                    'ultima_sincronizacion_aprobadas_at' => now(),
                ]
            );

            $ebsRequisicion->notes()->delete();

            foreach (($data['notes'] ?? []) as $note) {
                if (! isset($note['key'])) {
                    continue;
                }

                $ebsRequisicion->notes()->create([
                    'clave' => $note['key'],
                    'valor' => $note['value'] ?? null,
                ]);
            }

            $resultado = $this->vincularYActualizarEstatus($ebsRequisicion);

            if ($dispararAvisos && $resultado && $resultado['estatus_antes'] !== $resultado['estatus_despues']) {
                $this->dispararAvisoTransicion($resultado['solicitud'], $resultado['estatus_despues']);
            }
        });
    }

    /**
     * Busca una `SolicitudSicBorrador` candidata a vincular (folio_sic ===
     * code, comparación exacta) que no tenga ya un `ebs_requisition_id`
     * DISTINTO asignado — si ya está vinculada a otra requisición, se
     * ignora por completo (no se reasigna nunca).
     */
    private function solicitudCandidata(EbsRequisition $ebsRequisicion): ?SolicitudSicBorrador
    {
        if (! $ebsRequisicion->code) {
            return null;
        }

        return SolicitudSicBorrador::where('folio_sic', $ebsRequisicion->code)
            ->where(function ($query) use ($ebsRequisicion) {
                $query->whereNull('ebs_requisition_id')
                    ->orWhere('ebs_requisition_id', $ebsRequisicion->id);
            })
            ->first();
    }

    /**
     * Vincula (si aplica) y sincroniza el estatus local — SOLO
     * `ebs_requisition_id` y `estatus`, nunca toca `motivo`/`empleado_id`/
     * `centro_costo_id`/`tipo_equipo_id`/ningún otro campo de captura
     * humana.
     *
     * @return array{solicitud: SolicitudSicBorrador, estatus_antes: string, estatus_despues: string}|null
     */
    private function vincularYActualizarEstatus(EbsRequisition $ebsRequisicion): ?array
    {
        $solicitud = $this->solicitudCandidata($ebsRequisicion);

        if (! $solicitud) {
            return null;
        }

        $estatusAntes = $solicitud->estatus;

        if ($solicitud->ebs_requisition_id !== $ebsRequisicion->id) {
            $solicitud->ebs_requisition_id = $ebsRequisicion->id;
        }

        $nuevoEstatus = self::mapearEstatusLocal($ebsRequisicion->status ?? '', $estatusAntes);

        if ($nuevoEstatus) {
            $solicitud->estatus = $nuevoEstatus;
        }

        $solicitud->save();

        return [
            'solicitud' => $solicitud,
            'estatus_antes' => $estatusAntes,
            'estatus_despues' => $solicitud->estatus,
        ];
    }

    /**
     * Mismo patrón que `SolicitudesSic::marcarAutorizada()`/
     * `marcarRechazada()` (mismas variables: folio, empleado).
     */
    private function dispararAvisoTransicion(SolicitudSicBorrador $solicitud, string $nuevoEstatus): void
    {
        $solicitud->loadMissing('empleado');

        $evento = match ($nuevoEstatus) {
            SolicitudSicBorrador::ESTATUS_AUTORIZADA => TipoAviso::EVENTO_SIC_AUTORIZADA,
            SolicitudSicBorrador::ESTATUS_RECHAZADA => TipoAviso::EVENTO_SIC_RECHAZADA,
            default => null,
        };

        if (! $evento) {
            return;
        }

        $this->avisoDispatcher->disparar(
            $evento,
            $solicitud,
            solicitante: $solicitud->empleado,
            variables: [
                'folio' => $solicitud->folio_sic ?? "SIC #{$solicitud->id}",
                'empleado' => $solicitud->empleado?->nombre,
            ]
        );
    }

    private function nullableDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
