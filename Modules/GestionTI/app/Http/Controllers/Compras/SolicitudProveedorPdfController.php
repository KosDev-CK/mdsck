<?php

namespace Modules\GestionTI\Http\Controllers\Compras;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Models\SolicitudProveedor;

/**
 * PDF de la Solicitud a Proveedor (punto 13 del rediseño, ver
 * docs/gestionti-progreso.md) — para autorización externa/impresión,
 * disponible siempre, se haya enviado o no por correo. Mismo patrón exacto
 * que `Modules\GestionTI\Http\Controllers\Ayuda\AyudaPdfController`
 * (`Pdf::loadView(...)->download(...)`), gateado por el mismo permiso de la
 * pantalla (`screens.gestionti-solicitudes-proveedor.manage`, ver
 * routes/web.php) — a diferencia de `AyudaPdfController`, aquí sí es dato de
 * negocio real, no contenido instructivo genérico.
 */
class SolicitudProveedorPdfController extends Controller
{
    public function __invoke(SolicitudProveedor $solicitudProveedor)
    {
        $solicitudProveedor->load(['vendor', 'ticket', 'creadoPor', 'lineas.articulo', 'lineas.sic', 'lineas.ebsRequisition']);

        return Pdf::loadView('gestionti::pdf.solicitud-proveedor', [
            'solicitud' => $solicitudProveedor,
        ])->download("solicitud-proveedor-{$solicitudProveedor->folio}.pdf");
    }
}
