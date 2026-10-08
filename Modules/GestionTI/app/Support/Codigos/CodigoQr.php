<?php

namespace Modules\GestionTI\Support\Codigos;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Código QR como `data:` URI PNG para incrustarlo en los PDF (Dompdf). Se
 * dibuja con GD a partir de la matriz que calcula `bacon/bacon-qr-code`
 * (ya dependencia del proyecto por el 2FA), sin pasar por SVG/Imagick: un PNG
 * es lo más seguro de renderizar en Dompdf. Se usa en el encabezado de la
 * Solicitud a Proveedor (el contenido es el folio): la cámara del celular lo
 * lee mucho mejor que un código de barras lineal.
 */
class CodigoQr
{
    public static function dataUri(string $contenido, int $pixelesPorModulo = 8, int $margenModulos = 4): string
    {
        $matriz = Encoder::encode($contenido, ErrorCorrectionLevel::M())->getMatrix();
        $ancho = $matriz->getWidth();
        $tamano = ($ancho + 2 * $margenModulos) * $pixelesPorModulo;

        $imagen = imagecreatetruecolor($tamano, $tamano);
        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        $negro = imagecolorallocate($imagen, 0, 0, 0);
        imagefilledrectangle($imagen, 0, 0, $tamano, $tamano, $blanco);

        for ($y = 0; $y < $ancho; $y++) {
            for ($x = 0; $x < $ancho; $x++) {
                if ($matriz->get($x, $y) === 1) {
                    $x0 = ($x + $margenModulos) * $pixelesPorModulo;
                    $y0 = ($y + $margenModulos) * $pixelesPorModulo;
                    imagefilledrectangle($imagen, $x0, $y0, $x0 + $pixelesPorModulo - 1, $y0 + $pixelesPorModulo - 1, $negro);
                }
            }
        }

        ob_start();
        imagepng($imagen);
        $png = (string) ob_get_clean();
        imagedestroy($imagen);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
