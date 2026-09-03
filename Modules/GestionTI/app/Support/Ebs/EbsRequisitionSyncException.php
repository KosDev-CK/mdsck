<?php

namespace Modules\GestionTI\Support\Ebs;

use RuntimeException;

/**
 * Lanzada por `EbsRequisitionsClient` cuando la API de EBS falla a nivel
 * HTTP (timeout, 5xx, etc.) o responde `status.errorCode !== 0` (falla de
 * negocio, HTTP 200 igual). Quien llama al cliente decide qué hacer con
 * ella (loggear y seguir) — el cliente nunca debe tragarse el error en
 * silencio. Ver docs/gestionti-progreso.md.
 */
class EbsRequisitionSyncException extends RuntimeException
{
}
