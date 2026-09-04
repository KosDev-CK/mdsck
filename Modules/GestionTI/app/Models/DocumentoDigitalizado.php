<?php

namespace Modules\GestionTI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\GestionTI\Support\SharePoint\SharePointClient;

/**
 * Entidad genérica de "documento adjunto/digitalizado" reutilizable por
 * varias pantallas de Fase 3 (esta primera etapa solo la usa el adjunto de
 * Solicitud de SIC vía `tipo_documento = 'sic'`; futuras etapas la
 * reutilizarán para la remisión de Recepción, la responsiva firmada de
 * Asignación, la orden de servicio de Mantenimiento y el adjunto de
 * Factura). `entidad_relacionada`/`entidad_id` es una llave genérica por
 * nombre de clase base + id, no una relación morph real de Eloquent —
 * deliberadamente simple porque hoy solo hay 1 llamador real.
 *
 * Fase 5 (SharePoint): `proveedor_almacenamiento` ya no es siempre 'local'
 * — `storeUploaded()` decide por tipo de documento, según
 * `ConfiguracionDocumentos::current()`, si el archivo se sube a SharePoint
 * (Microsoft Graph, vía `SharePointClient`) o se sigue guardando en el
 * disco `public` (comportamiento histórico, sigue intacto para los tipos no
 * marcados). Cuando es 'sharepoint', `referencia` guarda el `driveItemId` de
 * Graph (no un path) y `url_externa` el `webUrl` — usar siempre el accessor
 * `url()` para resolver el link correcto, nunca `Storage::disk('public')->url()`
 * directo desde una vista (deja de funcionar para documentos en SharePoint).
 */
class DocumentoDigitalizado extends Model
{
    protected $table = 'documentos_digitalizados';

    protected $fillable = [
        'entidad_relacionada',
        'entidad_id',
        'tipo_documento',
        'proveedor_almacenamiento',
        'referencia',
        'url_externa',
        'nombre_archivo',
        'fecha_subida',
        'subido_por_id',
    ];

    protected $casts = [
        'fecha_subida' => 'datetime',
    ];

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por_id');
    }

    /**
     * `driveItemId` de todos los archivos de SharePoint ya vinculados (subidos
     * o elegidos vía "Buscar en SharePoint") para un `$tipoDocumento` dado —
     * usado para excluirlos del listado del modal "Buscar en SharePoint" y
     * así no ofrecer un archivo que ya quedó relacionado con otro registro.
     *
     * @return array<int, string>
     */
    public static function driveItemIdsVinculados(string $tipoDocumento): array
    {
        return static::query()
            ->where('tipo_documento', $tipoDocumento)
            ->where('proveedor_almacenamiento', 'sharepoint')
            ->pluck('referencia')
            ->all();
    }

    /**
     * Guarda un archivo subido vía Livewire (`WithFileUploads`). Si
     * `$tipoDocumento` está marcado en `ConfiguracionDocumentos` para ir a
     * SharePoint, sube el binario vía `SharePointClient` y crea el registro
     * con `proveedor_almacenamiento = 'sharepoint'`; si `SharePointClient`
     * lanza `SharePointException` (falla de Graph o carpeta sin configurar),
     * esta excepción se propaga tal cual — a propósito NO se captura aquí,
     * así nunca se crea un `DocumentoDigitalizado` a medias (ni el archivo
     * queda "subido pero sin registro" ni el registro "creado pero sin
     * archivo real"); quien llama decide qué mostrarle al usuario. Si el
     * tipo no está marcado, sigue el comportamiento histórico intacto: disco
     * `public`, `proveedor_almacenamiento = 'local'`.
     */
    public static function storeUploaded(UploadedFile $file, Model $entidad, string $tipoDocumento, ?int $subidoPorId): self
    {
        if (ConfiguracionDocumentos::current()->usaSharePoint($tipoDocumento)) {
            $subida = app(SharePointClient::class)->subirArchivoParaTipo(
                $tipoDocumento,
                $file->getClientOriginalName(),
                file_get_contents($file->getRealPath()),
                $file->getMimeType() ?: 'application/octet-stream'
            );

            return static::create([
                'entidad_relacionada' => class_basename($entidad),
                'entidad_id' => $entidad->getKey(),
                'tipo_documento' => $tipoDocumento,
                'proveedor_almacenamiento' => 'sharepoint',
                'referencia' => $subida['driveItemId'],
                'url_externa' => $subida['webUrl'],
                'nombre_archivo' => $file->getClientOriginalName(),
                'fecha_subida' => now(),
                'subido_por_id' => $subidoPorId,
            ]);
        }

        $path = $file->store('documentos-digitalizados', 'public');

        return static::create([
            'entidad_relacionada' => class_basename($entidad),
            'entidad_id' => $entidad->getKey(),
            'tipo_documento' => $tipoDocumento,
            'proveedor_almacenamiento' => 'local',
            'referencia' => $path,
            'url_externa' => null,
            'nombre_archivo' => $file->getClientOriginalName(),
            'fecha_subida' => now(),
            'subido_por_id' => $subidoPorId,
        ]);
    }

    /**
     * Vincula un archivo que YA existe en SharePoint (subido fuera de este
     * sistema) sin subir nada — usado por el modal "Buscar en SharePoint" de
     * Asignaciones/Recepciones. `$archivoSharePoint` es una de las entradas
     * devueltas por `SharePointClient::listarArchivos()`
     * (`['driveItemId' => ..., 'nombre' => ..., 'webUrl' => ...]`).
     */
    public static function linkExisting(array $archivoSharePoint, Model $entidad, string $tipoDocumento, ?int $subidoPorId): self
    {
        return static::create([
            'entidad_relacionada' => class_basename($entidad),
            'entidad_id' => $entidad->getKey(),
            'tipo_documento' => $tipoDocumento,
            'proveedor_almacenamiento' => 'sharepoint',
            'referencia' => $archivoSharePoint['driveItemId'],
            'url_externa' => $archivoSharePoint['webUrl'],
            'nombre_archivo' => $archivoSharePoint['nombre'],
            'fecha_subida' => now(),
            'subido_por_id' => $subidoPorId,
        ]);
    }

    /**
     * URL para abrir/descargar el documento — resuelve según
     * `proveedor_almacenamiento`: 'local' usa el disco `public` como
     * siempre; 'sharepoint' usa el `webUrl` ya persistido en `url_externa`
     * (no se vuelve a llamar a Graph — más rápido y no depende de que Graph
     * esté disponible solo para mostrar un link). Las vistas Blade deben
     * usar siempre este accessor, nunca `Storage::disk('public')->url()`
     * directo.
     */
    public function url(): string
    {
        if ($this->proveedor_almacenamiento === 'sharepoint') {
            return (string) $this->url_externa;
        }

        return Storage::disk('public')->url($this->referencia);
    }
}
