<?php

namespace Modules\GestionTI\Support\Codigos;

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Código de barras Code 128 como `data:` URI para incrustarlo en los PDF
 * (Dompdf). Se usa en el PDF de la Solicitud a Proveedor para que Recepción
 * de Proveedor pueda abrir la solicitud escaneando su folio (lector USB o
 * cámara del celular). Code 128 porque lo lee cualquier lector, incluso los
 * lineales baratos, y soporta el folio alfanumérico con guiones.
 */
class CodigoDeBarras
{
    public static function dataUri(string $valor, int $anchoModulo = 2, int $alto = 55): string
    {
        $png = (new BarcodeGeneratorPNG)->getBarcode($valor, BarcodeGeneratorPNG::TYPE_CODE_128, $anchoModulo, $alto);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
