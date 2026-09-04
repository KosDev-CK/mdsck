<?php

namespace Modules\GestionTI\Support\Cfdi;

use DOMDocument;
use DOMXPath;

/**
 * Extrae los campos clave de un CFDI (factura electrónica mexicana, XML
 * timbrado por el SAT) para el "visor de XML" de Facturación — no es un
 * validador fiscal ni verifica el sello/timbre, solo lee los atributos ya
 * presentes en el XML para mostrarlos de un vistazo en vez de obligar a leer
 * el XML crudo. Sin librería externa (`DOMDocument`/`DOMXPath` vienen con
 * PHP) — mismo criterio del resto del módulo de evitar dependencias nuevas
 * cuando la extensión estándar de PHP ya alcanza.
 *
 * Usa `local-name()` en XPath a propósito, en vez de registrar los
 * namespaces `cfdi`/`tfd` reales — el prefijo de namespace en el XML es
 * arbitrario (algunos PACs usan otros), pero el nombre del elemento sin
 * prefijo es estable entre CFDI 3.3 y 4.0 para los campos que se leen aquí.
 */
class CfdiReader
{
    /**
     * @return array{uuid: ?string, fecha: ?string, total: ?string, moneda: ?string, emisor_rfc: ?string, emisor_nombre: ?string, receptor_rfc: ?string, receptor_nombre: ?string}|null
     *         `null` si el contenido no es XML válido o no trae un nodo
     *         "Comprobante" reconocible (no es un CFDI) — el llamador decide
     *         qué mostrar en ese caso (típicamente solo el XML crudo, sin el
     *         resumen).
     */
    public static function parse(string $xmlContent): ?array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $cargado = $dom->loadXML($xmlContent, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        if (! $cargado) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $comprobante = $xpath->query("//*[local-name()='Comprobante']")->item(0);

        if (! $comprobante) {
            return null;
        }

        $emisor = $xpath->query("//*[local-name()='Emisor']")->item(0);
        $receptor = $xpath->query("//*[local-name()='Receptor']")->item(0);
        $timbre = $xpath->query("//*[local-name()='TimbreFiscalDigital']")->item(0);

        return [
            'uuid' => $timbre?->getAttribute('UUID') ?: null,
            'fecha' => $comprobante->getAttribute('Fecha') ?: null,
            'total' => $comprobante->getAttribute('Total') ?: null,
            'moneda' => $comprobante->getAttribute('Moneda') ?: null,
            'emisor_rfc' => $emisor?->getAttribute('Rfc') ?: null,
            'emisor_nombre' => $emisor?->getAttribute('Nombre') ?: null,
            'receptor_rfc' => $receptor?->getAttribute('Rfc') ?: null,
            'receptor_nombre' => $receptor?->getAttribute('Nombre') ?: null,
        ];
    }
}
