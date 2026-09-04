<?php

namespace Modules\GestionTI\Http\Controllers\Ayuda;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Routing\Controller;
use Modules\GestionTI\Support\Ayuda\AyudaCatalog;

/**
 * PDF descargable del contenido de ayuda de una pantalla — mismo texto
 * que se ve en su modal "?" (fuente única en `AyudaCatalog`), solo con
 * una plantilla de CSS plano en vez de Tailwind (Dompdf no procesa
 * Tailwind). Gateado solo por `auth` (ver routes/web.php), no por el
 * permiso específico de cada pantalla — es contenido instructivo
 * genérico, no datos de negocio, mismo criterio que la campanita de
 * notificaciones o "Mi perfil".
 */
class AyudaPdfController extends Controller
{
    public function __invoke(string $slug)
    {
        abort_unless(AyudaCatalog::existe($slug), 404);

        return Pdf::loadView('gestionti::pdf.ayuda', [
            'contenido' => AyudaCatalog::contenido($slug),
            'cabecera' => $this->imagenDataUri('cabecera.png'),
            'pie' => $this->imagenDataUri('pie.png'),
        ])->download("ayuda-{$slug}.pdf");
    }

    /**
     * Encabezado (3cm de alto) y pie (8mm de alto) van embebidos como data
     * URI (no como <img src="URL pública">) — Dompdf resuelve rutas de
     * archivo/base64 de forma mucho más confiable que peticiones HTTP a
     * assets propios, sin depender de que el servidor se pueda llamar a sí
     * mismo. Son las 2 imágenes ya armadas y aprobadas por el usuario (no se
     * recrean con CSS/logos sueltos — ese primer intento no coincidía con el
     * diseño real), guardadas en Modules/GestionTI/resources/assets/ayuda/.
     */
    private function imagenDataUri(string $filename): string
    {
        $path = module_path('GestionTI', "resources/assets/ayuda/{$filename}");

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
