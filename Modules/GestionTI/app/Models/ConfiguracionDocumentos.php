<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton (`id` siempre 1, mismo patrón que `App\Models\SiteSetting::current()`)
 * que decide qué tipos de documento (`DocumentoDigitalizado.tipo_documento`)
 * se suben a SharePoint vía Microsoft Graph en vez de guardarse en el disco
 * `public` — ver `DocumentoDigitalizado::storeUploaded()`. Pantalla asociada:
 * "Configuración de Almacenamiento" (`Modules\GestionTI\Livewire\Configuracion\AlmacenamientoDocumentos`).
 */
class ConfiguracionDocumentos extends Model
{
    protected $table = 'configuracion_documentos';

    protected $fillable = [
        'tipos_sharepoint',
    ];

    protected $casts = [
        'tipos_sharepoint' => 'array',
    ];

    /**
     * Los 5 valores reales de `tipo_documento` usados hoy por
     * `DocumentoDigitalizado` en todo el módulo (ver comentario en su
     * migración) — única fuente de verdad de la lista completa, tanto para
     * los checkboxes de la pantalla de configuración como para validar que
     * no se guarde basura en `tipos_sharepoint`.
     */
    public const TIPOS_DOCUMENTO = [
        'sic',
        'responsiva',
        'remision_proveedor',
        'factura',
        'factura_xml',
        'orden_servicio',
    ];

    /**
     * Default al sembrarse por primera vez — deliberadamente vacío, aunque
     * el destino acordado con el usuario es activar "responsiva" y
     * "remision_proveedor". Se deja apagado hasta que el permiso `Sites.Selected`
     * esté concedido en Azure y las carpetas reales existan en el sitio: si
     * este default trajera esos 2 tipos ya activados y el módulo se despliega
     * antes de tener ese permiso, la primera Asignación/Recepción real
     * tronaría al intentar subir el archivo (`SharePointClient` no tiene
     * carpeta/credenciales usables todavía). Activar los 2 tipos a mano desde
     * "Configuración de Almacenamiento" es cosa de un clic una vez que
     * `docs/sharepoint-graph-integracion.md` esté completado — no hace falta
     * otro deploy para prender esto.
     */
    public const DEFAULTS = [
        'tipos_sharepoint' => [],
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], self::DEFAULTS);
    }

    public function usaSharePoint(string $tipoDocumento): bool
    {
        return in_array($tipoDocumento, $this->tipos_sharepoint ?? [], true);
    }
}
