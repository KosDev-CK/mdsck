<?php

namespace Modules\GestionTI\Http\Controllers\Compras;

use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Support\Exports\StreamsXlsxDownloads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Excel de la Solicitud a Proveedor: arriba los datos de cabecera (los mismos
 * del PDF) y abajo una tabla con el folio de la solicitud en cada fila y los
 * artículos que contiene. Mismo permiso que el PDF (ver routes/web.php).
 */
class SolicitudProveedorExcelController extends Controller
{
    use StreamsXlsxDownloads;

    private const ESTATUS = [
        'solicitada' => 'Solicitada',
        'enviada' => 'Enviada',
        'parcialmente_recibida' => 'Parcialmente recibida',
        'recibida' => 'Recibida',
        'facturada' => 'Facturada',
        'cancelada' => 'Cancelada',
    ];

    public function __invoke(SolicitudProveedor $solicitudProveedor)
    {
        $s = $solicitudProveedor->load(['vendor', 'ticket', 'creadoPor', 'lineas.articulo', 'lineas.sic', 'lineas.ebsRequisition.lines', 'lineas.lugarEntrega']);

        $cabecera = [
            ['Folio', $s->folio],
            [$s->vendor?->es_interno ? 'Área' : 'Proveedor', $s->vendor?->nombre_comercial ?? '—'],
            ['Fecha de solicitud', optional($s->fecha_solicitud)->format('d/m/Y') ?? '—'],
            ['Fecha de entrega comprometida', optional($s->fecha_entrega_prometida)->format('d/m/Y') ?? '—'],
            ['Solicitante', $s->creadoPor?->name ?? '—'],
            ['Estatus', self::ESTATUS[$s->estatus] ?? $s->estatus],
            ['Ticket relacionado', $s->ticket?->sdp_display_id ?? '—'],
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Solicitud');

        $fila = 1;
        foreach ($cabecera as $par) {
            $this->writeRow($sheet, $fila, $par);
            $sheet->getStyle("A{$fila}")->getFont()->setBold(true);
            $fila++;
        }

        $fila++; // renglón en blanco entre cabecera y tabla
        $encabezados = ['Folio', 'Línea', 'SIC', 'Artículo', 'Cantidad', 'Precio unitario', 'Lugar de entrega', 'Observaciones'];
        $this->writeRow($sheet, $fila, $encabezados);
        $sheet->getStyle("A{$fila}:H{$fila}")->getFont()->setBold(true);
        $sheet->getStyle("A{$fila}:H{$fila}")->getFill()->setFillType('solid')->getStartColor()->setRGB('F3F4F6');
        $fila++;

        foreach ($s->lineas->sortBy('id')->values() as $i => $linea) {
            $this->writeRow($sheet, $fila, [
                $s->folio,
                $i + 1,
                $linea->folioSicDisplay() ?? '—',
                $linea->articulo?->descripcion ?? $linea->descripcion_libre ?? '—',
                $linea->cantidad_solicitada,
                $linea->precio_unitario_cotizado ?? '—',
                $linea->lugarEntrega?->nombre ?? 'Sin lugar de entrega',
                $linea->observaciones_especificaciones ?? '—',
            ]);
            $fila++;
        }

        foreach (range(1, count($encabezados)) as $columna) {
            $sheet->getColumnDimensionByColumn($columna)->setAutoSize(true);
        }

        $writer = new XlsxWriter($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, "solicitud-proveedor-{$s->folio}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
