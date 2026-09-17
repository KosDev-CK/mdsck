<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\EbsSyncFailure;
use Modules\GestionTI\Support\Ebs\EbsRequisitionsClient;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncException;
use Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService;
use Throwable;

/**
 * Reintenta los días de sincronización EBS pendientes (`EbsSyncFailure`) —
 * primero contra la API real, y si sigue fallando, intenta confirmar por
 * consecutivo de folio que no hubo SICs creadas ese día (solo aplica a
 * "requisition_header_line").
 *
 * Workaround nuestro mientras Oracle EBS no responda un error más
 * específico (o un payload legítimamente vacío) cuando de verdad no hay
 * datos ese día — `errorCode=10` hoy es indistinguible entre "no hay SICs
 * ese día" y una falla real de la fuente de datos del lado de Oracle. Ver
 * docs/gestionti-progreso.md para el detalle completo.
 *
 * Nunca dispara avisos (mismo criterio que `EbsBackfillCommand`, para no
 * inundar de avisos retroactivos).
 */
class EbsReintentarFallidosCommand extends Command
{
    protected $signature = 'gestionti:ebs-reintentar-fallidos';

    protected $description = 'Reintenta los días de sincronización EBS pendientes (ver EbsSyncFailure) — primero contra la API real, y si sigue fallando, intenta confirmar por consecutivo de folio que no hubo SICs creadas ese día (solo aplica a "requisition_header_line").';

    public function handle(EbsRequisitionSyncService $service): int
    {
        $hoy = now()->startOfDay();

        foreach (EbsSyncFailure::pendientes()->get() as $registro) {
            $fecha = Carbon::parse($registro->fecha)->startOfDay();

            // Carbon 3 cambió el default de diffInDays() a una diferencia
            // con signo — abs() explícito, mismo criterio que
            // EbsBackfillCommand.
            $offset = abs($hoy->diffInDays($fecha));

            $excepcion = null;

            try {
                if ($registro->metodo === EbsRequisitionsClient::METHOD_CREADAS) {
                    $service->sincronizarCreadas($offset);
                } else {
                    // Sin avisos, mismo criterio que el backfill — no
                    // inundar de avisos retroactivos.
                    $service->sincronizarAprobadas($offset, dispararAvisos: false);
                }

                $registro->update([
                    'resuelto_at' => now(),
                    'resuelto_via' => EbsSyncFailure::RESUELTO_VIA_REINTENTO,
                ]);

                $this->info("Reintento EBS: {$fecha->toDateString()} ({$registro->metodo}) — OK, resuelto vía reintento real.");

                continue;
            } catch (Throwable $e) {
                $excepcion = $e;
            }

            // El reintento real volvió a fallar — solo "creadas" tiene el
            // concepto de folio consecutivo (el folio visible vive en
            // ebs_requisitions.code, poblado por ese método).
            if ($registro->metodo === EbsRequisitionsClient::METHOD_CREADAS) {
                $confirmacion = $this->confirmarSinSicsPorConsecutivo($fecha);

                if ($confirmacion) {
                    $registro->update([
                        'resuelto_at' => now(),
                        'resuelto_via' => EbsSyncFailure::RESUELTO_VIA_CONSECUTIVO,
                    ]);

                    $this->info("Reintento EBS: {$fecha->toDateString()} ({$registro->metodo}) — confirmado sin SICs por folio consecutivo (anterior={$confirmacion['anterior']}, siguiente={$confirmacion['siguiente']}).");

                    continue;
                }
            }

            $errorCode = $excepcion instanceof EbsRequisitionSyncException ? $excepcion->errorCode : null;
            $errorMsg = $excepcion instanceof EbsRequisitionSyncException ? $excepcion->errorMsg : $excepcion?->getMessage();

            EbsSyncFailure::registrar($fecha, $registro->metodo, $errorCode, $errorMsg);

            $this->error("Reintento EBS: {$fecha->toDateString()} ({$registro->metodo}) — sigue fallando, pendiente.");
        }

        return self::SUCCESS;
    }

    /**
     * Prueba que no se creó ningún SIC el día `$fecha` comparando el folio
     * (`code`, estrictamente consecutivo — confirmado por el usuario) del
     * `EbsRequisition` real más cercano ANTES de ese día contra el más
     * cercano DESPUÉS — si son consecutivos (`siguiente == anterior + 1`),
     * no hay hueco: no se creó nada ese día (ni en ningún otro día sin
     * datos reales en medio, ej. un fin de semana completo).
     *
     * Vive como método privado de este comando (no en `EbsSyncFailure` ni
     * en el servicio) por decisión de diseño: es una regla de negocio
     * específica del flujo de reintento/backfill, no algo que el resto del
     * módulo necesite invocar.
     *
     * @return array{anterior: string, siguiente: string}|null
     */
    private function confirmarSinSicsPorConsecutivo(Carbon $fecha): ?array
    {
        $anterior = EbsRequisition::whereNotNull('code')
            ->where('fecha_creacion', '<', $fecha->copy()->startOfDay())
            ->orderByDesc('fecha_creacion')
            ->first();

        $siguiente = EbsRequisition::whereNotNull('code')
            ->where('fecha_creacion', '>=', $fecha->copy()->addDay()->startOfDay())
            ->orderBy('fecha_creacion')
            ->first();

        if (! $anterior || ! $siguiente) {
            return null; // no hay suficiente data confirmada de un lado u otro, no se puede probar nada
        }

        if (! ctype_digit((string) $anterior->code) || ! ctype_digit((string) $siguiente->code)) {
            return null; // code no es puramente numérico en alguno de los dos, no se puede comparar
        }

        if ((int) $siguiente->code !== (int) $anterior->code + 1) {
            return null; // hay un salto — sí se crearon SICs ese día (o en el rango), no se puede confirmar vacío
        }

        return ['anterior' => $anterior->code, 'siguiente' => $siguiente->code];
    }
}
