<?php

namespace Modules\GestionTI\Support\Codigos;

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Código de barras Code 128 como `data:` URI para incrustarlo en los PDF
 * (Dompdf). Se usa en el PDF de la Solicitud a Proveedor, en cada línea
 * (`{folio}-L{n}`), para que Recepción de Proveedor pueda ir a esa línea
 * escaneándola (lector USB o cámara del celular). Code 128 porque lo lee
 * cualquier lector, incluso los lineales baratos, y soporta el texto
 * alfanumérico con guiones.
 *
 * Se agrega la zona de silencio (margen blanco de 10 módulos a cada lado) que
 * exige el estándar: sin ella, un código pegado al borde de la imagen o junto
 * a otro texto no lo lee todo lector.
 */
class CodigoDeBarras
{
    public static function dataUri(string $valor, int $anchoModulo = 2, int $alto = 55): string
    {
        $png = (new BarcodeGeneratorPNG)->getBarcode($valor, BarcodeGeneratorPNG::TYPE_CODE_128, $anchoModulo, $alto);

        $barras = imagecreatefromstring($png);
        $margen = 10 * $anchoModulo;
        $lienzo = imagecreatetruecolor(imagesx($barras) + 2 * $margen, imagesy($barras));
        imagefilledrectangle($lienzo, 0, 0, imagesx($lienzo), imagesy($lienzo), imagecolorallocate($lienzo, 255, 255, 255));
        imagecopy($lienzo, $barras, $margen, 0, 0, 0, imagesx($barras), imagesy($barras));

        ob_start();
        imagepng($lienzo);
        $conMargen = (string) ob_get_clean();
        imagedestroy($barras);
        imagedestroy($lienzo);

        return 'data:image/png;base64,'.base64_encode($conMargen);
    }
}
