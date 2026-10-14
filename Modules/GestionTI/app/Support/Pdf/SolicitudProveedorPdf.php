<?php

namespace Modules\GestionTI\Support\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Modules\GestionTI\Models\SolicitudProveedor;

/**
 * PDF de la Solicitud a Proveedor, con el pie "folio · Página X de Y" en todas
 * las hojas. Lo comparten la descarga (controlador) y el adjunto del correo
 * al proveedor para que ambos salgan idénticos.
 */
class SolicitudProveedorPdf
{
    public static function generar(SolicitudProveedor $solicitud): DompdfWrapper
    {
        $pdf = Pdf::loadView('gestionti::pdf.solicitud-proveedor', ['solicitud' => $solicitud]);

        // La numeración se agrega sobre el PDF ya paginado ({PAGE_COUNT} solo se conoce al final).
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('Helvetica');
        $color = [0.42, 0.45, 0.5];
        $y = $canvas->get_height() - 24;

        $canvas->page_text(30, $y, "Solicitud a Proveedor {$solicitud->folio}", $fuente, 8, $color);
        $canvas->page_text($canvas->get_width() - 100, $y, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, $color);

        return $pdf;
    }
}
