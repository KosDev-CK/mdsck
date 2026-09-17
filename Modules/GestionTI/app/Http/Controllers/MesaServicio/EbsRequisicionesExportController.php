<?php

namespace Modules\GestionTI\Http\Controllers\MesaServicio;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\EbsRequisition;
use Modules\GestionTI\Support\Exports\StreamsXlsxDownloads;

/**
 * "Descargar Excel" de la pantalla "SIC en EBS" — un .xlsx con las mismas
 * columnas mostradas en la tabla, respetando exactamente los mismos filtros
 * activos en pantalla (código/estatus/vinculación/fechas), sin paginar.
 * Misma lógica de consulta que `EbsRequisiciones::render()` — el filtro de
 * búsqueda general vive en `EbsRequisition::scopeMatchesSearch()` para que
 * ambos produzcan siempre el mismo conjunto de resultados.
 */
class EbsRequisicionesExportController extends Controller
{
    use StreamsXlsxDownloads;

    public function __invoke(Request $request)
    {
        $codigo = trim((string) $request->query('codigo', ''));
        $estatus = trim((string) $request->query('estatus', ''));
        $vinculacion = trim((string) $request->query('vinculacion', ''));
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));
        $aprobadaDesde = trim((string) $request->query('aprobada_desde', ''));
        $aprobadaHasta = trim((string) $request->query('aprobada_hasta', ''));

        $records = EbsRequisition::query()
            ->with(['solicitudSicBorrador.ticket'])
            ->when($codigo !== '', fn ($q) => $q->matchesSearch($codigo))
            ->when($estatus !== '', fn ($q) => $q->where('status', $estatus))
            ->when($vinculacion === 'vinculada', fn ($q) => $q->whereHas('solicitudSicBorrador'))
            ->when($vinculacion === 'no_vinculada', fn ($q) => $q->whereDoesntHave('solicitudSicBorrador'))
            ->when($desde !== '', fn ($q) => $q->whereDate('fecha_creacion', '>=', $desde))
            ->when($hasta !== '', fn ($q) => $q->whereDate('fecha_creacion', '<=', $hasta))
            ->when($aprobadaDesde !== '', fn ($q) => $q->whereDate('approver_date', '>=', $aprobadaDesde))
            ->when($aprobadaHasta !== '', fn ($q) => $q->whereDate('approver_date', '<=', $aprobadaHasta))
            ->orderByDesc('fecha_creacion')
            ->get();

        $headers = ['Código', 'Descripción', 'Estatus', 'Fecha de creación', 'Fecha de autorización', 'Vinculada'];

        $rows = $records->map(fn ($r) => [
            $r->code,
            $r->description,
            $r->status,
            $r->fecha_creacion?->format('d/m/Y'),
            $r->approver_date?->format('d/m/Y'),
            $this->vinculadaLabel($r),
        ]);

        return $this->streamXlsx('sic-ebs-'.now()->format('Y-m-d').'.xlsx', $headers, $rows->all());
    }

    private function vinculadaLabel(EbsRequisition $record): string
    {
        if (! $record->solicitudSicBorrador) {
            return 'No vinculada';
        }

        $label = 'SIC #'.$record->solicitudSicBorrador->id;

        if ($record->solicitudSicBorrador->ticket) {
            $ticket = $record->solicitudSicBorrador->ticket;
            $label .= ' — Ticket '.($ticket->sdp_display_id ?? $ticket->sdp_id ?? ('#'.$ticket->id));
        }

        return $label;
    }
}
