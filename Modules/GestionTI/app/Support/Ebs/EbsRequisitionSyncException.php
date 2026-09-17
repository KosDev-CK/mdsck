<?php

namespace Modules\GestionTI\Support\Ebs;

use RuntimeException;

/**
 * Lanzada por `EbsRequisitionsClient` cuando la API de EBS falla a nivel
 * HTTP (timeout, 5xx, etc.) o responde `status.errorCode !== 0` (falla de
 * negocio, HTTP 200 igual). Quien llama al cliente decide qué hacer con
 * ella (loggear y seguir) — el cliente nunca debe tragarse el error en
 * silencio. Ver docs/gestionti-progreso.md.
 *
 * Además del mensaje de texto ya armado (histórico — algunos tests
 * existentes verifican su forma exacta, no cambia), expone 3 propiedades
 * estructuradas para quien necesite el detalle sin re-parsear el string:
 * `errorCode`/`errorMsg` (ambos `null` cuando la falla es a nivel HTTP, no
 * de negocio — ahí solo se conoce el método) y `metodo` (uno de
 * `EbsRequisitionsClient::METHOD_*`). Las usa `EbsSyncFailure::registrar()`
 * para persistir el detalle del fallo — ver docs/gestionti-progreso.md.
 */
class EbsRequisitionSyncException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $errorCode = null,
        public readonly ?string $errorMsg = null,
        public readonly ?string $metodo = null,
    ) {
        parent::__construct($message);
    }
}
