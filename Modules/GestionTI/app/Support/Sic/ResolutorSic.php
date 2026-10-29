<?php

namespace Modules\GestionTI\Support\Sic;

use Illuminate\Support\Collection;
use Modules\GestionTI\Models\AssetAssignment;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Models\SolicitudSicBorrador;

/**
 * Resuelve el FOLIO de una SIC contra sus dos orígenes con la misma lógica:
 * la requisición de EBS (flujo principal) y la Solicitud de SIC local
 * (respaldo cuando EBS no está disponible). Si hay SIC local con ese folio se
 * usa esa (es la que ya trae empleado y ticket); si no, la requisición de EBS;
 * si no hay ninguna, el folio es solo una referencia a una SIC que todavía
 * no existe en el sistema.
 */
class ResolutorSic
{
    /** Estatus de EBS que ya no pueden cubrir una entrega. */
    private const ESTATUS_EBS_BLOQUEADOS = ['REJECTED', 'CANCELLED', 'CANCELED'];

    /**
     * @return array{
     *     tipo: 'local'|'ebs'|null,
     *     local: ?SolicitudSicBorrador,
     *     ebs: ?EbsRequisition,
     *     autorizada: bool,
     *     bloqueo: ?string,
     *     estatus: string
     * }
     */
    public static function porFolio(string $folio): array
    {
        $folio = trim($folio);
        $local = null;
        $ebs = null;

        if ($folio !== '') {
            $local = SolicitudSicBorrador::with('empleado', 'tipoEquipo')->where('folio_sic', $folio)->first();
            $ebs = EbsRequisition::with('lines')->where('code', $folio)->first();

            // Una SIC local ligada a una requisición de EBS y la requisición misma
            // son la MISMA SIC: la local manda (trae el empleado y el ticket).
            $local ??= $ebs?->solicitudSicBorrador()->with('empleado', 'tipoEquipo')->first();
        }

        $tipo = $local ? 'local' : ($ebs ? 'ebs' : null);

        $autorizada = match ($tipo) {
            'local' => $local->estatus === SolicitudSicBorrador::ESTATUS_AUTORIZADA,
            'ebs' => $ebs->status === 'APPROVED',
            default => false,
        };

        $bloqueo = null;

        if ($tipo === 'local' && $local->estatus === SolicitudSicBorrador::ESTATUS_RECHAZADA) {
            $bloqueo = "La SIC {$folio} fue rechazada: no puede cubrir una entrega.";
        } elseif ($tipo === 'ebs' && in_array(strtoupper((string) $ebs->status), self::ESTATUS_EBS_BLOQUEADOS, true)) {
            $bloqueo = "La requisición {$folio} está en estatus {$ebs->status} en EBS: no puede cubrir una entrega.";
        }

        $estatus = match (true) {
            $tipo === null => 'No encontrada en el sistema',
            $tipo === 'local' => ucfirst(str_replace('_', ' ', $local->estatus)),
            default => (string) ($ebs->status ?: 'Sin estatus'),
        };

        return compact('tipo', 'local', 'ebs', 'autorizada', 'bloqueo', 'estatus');
    }

    /**
     * Líneas de una requisición de EBS con su capacidad: cada línea tiene un
     * identificador único (`requisition_line_id`) y puede cubrir tantas
     * asignaciones como piezas trae (`quantity`).
     *
     * @return Collection<int, array{id: int, numero: int, descripcion: string, cantidad: int, asignadas: int, libres: int}>
     */
    public static function lineasEbs(EbsRequisition $requisicion, ?int $exceptoAsignacionId = null): Collection
    {
        $requisicion->loadMissing('lines');
        $ids = $requisicion->lines->pluck('requisition_line_id')->filter()->all();

        $asignadas = AssetAssignment::query()
            ->whereIn('ebs_requisition_line_id', $ids)
            ->when($exceptoAsignacionId, fn ($q) => $q->where('id', '!=', $exceptoAsignacionId))
            ->selectRaw('ebs_requisition_line_id, count(*) as total')
            ->groupBy('ebs_requisition_line_id')
            ->pluck('total', 'ebs_requisition_line_id');

        return $requisicion->lines->sortBy('line_number')->values()->map(function ($linea) use ($asignadas) {
            $cantidad = max(1, (int) ceil((float) $linea->quantity));
            $usadas = (int) ($asignadas[$linea->requisition_line_id] ?? 0);

            return [
                'id' => (int) $linea->requisition_line_id,
                'numero' => (int) $linea->line_number,
                'descripcion' => (string) ($linea->item_description ?: 'Sin descripción'),
                'cantidad' => $cantidad,
                'asignadas' => $usadas,
                'libres' => max(0, $cantidad - $usadas),
            ];
        });
    }
}
