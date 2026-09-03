<?php

namespace Modules\GestionTI\Support\SharePoint;

use RuntimeException;

/**
 * Lanzada por `SharePointClient` cuando Microsoft Graph falla a nivel HTTP
 * (timeout, 4xx/5xx) o cuando falta configuración necesaria para resolver
 * una operación (ej. carpeta no definida para un tipo de documento). Quien
 * llama decide qué hacer con ella (mostrar el error en la pantalla) — el
 * cliente nunca se la traga en silencio. Mismo criterio que
 * `Modules\GestionTI\Support\Ebs\EbsRequisitionSyncException`.
 */
class SharePointException extends RuntimeException
{
}
