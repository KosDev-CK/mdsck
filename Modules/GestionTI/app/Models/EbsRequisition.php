<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Réplica local de una requisición (SIC) de Oracle EBS. Ver
 * `Modules\GestionTI\Support\Ebs\EbsRequisitionSyncService` y
 * docs/gestionti-progreso.md para el diseño completo de la sincronización.
 *
 * "EbsRequisition" -> "ebs_requisitions" es pluralización regular en
 * inglés, no requiere `$table` explícito (mismo criterio ya documentado
 * para `Invoice`).
 */
class EbsRequisition extends Model
{
    protected $fillable = [
        'requisition_header_id',
        'code',
        'description',
        'status',
        'fecha_creacion',
        'wf_item_key',
        'wf_item_type',
        'organization_code',
        'organization_description',
        'created_by_user',
        'created_by_description',
        'sequence_num',
        'approver_user',
        'approver_name',
        'approver_date',
        'action_code',
        'action_date',
        'ultima_sincronizacion_creadas_at',
        'ultima_sincronizacion_aprobadas_at',
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'approver_date' => 'datetime',
        'action_date' => 'datetime',
        'ultima_sincronizacion_creadas_at' => 'datetime',
        'ultima_sincronizacion_aprobadas_at' => 'datetime',
        'sequence_num' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(EbsRequisitionLine::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EbsRequisitionNote::class);
    }

    /**
     * La FK real vive en `solicitudes_sic_borrador.ebs_requisition_id`, no
     * aquí — `hasOne` inverso.
     */
    public function solicitudSicBorrador(): HasOne
    {
        return $this->hasOne(SolicitudSicBorrador::class, 'ebs_requisition_id');
    }

    /**
     * Líneas de Solicitud a Proveedor que recogieron ESTA requisición
     * DIRECTAMENTE (`SolicitudProveedorLinea::ebs_requisition_id`) — el
     * tercer origen de línea, exclusivo de requisiciones que nunca tuvieron
     * match de SIC local (ver ese modelo). Una requisición con SIC local
     * vinculada nunca usa este camino — su asignación se consulta vía
     * `solicitudSicBorrador->solicitudProveedorLineas` en su lugar.
     */
    public function solicitudProveedorLineas(): HasMany
    {
        return $this->hasMany(SolicitudProveedorLinea::class, 'ebs_requisition_id');
    }

    /**
     * Segundo camino de elegibilidad para armar una Solicitud a Proveedor
     * (ver `SolicitudSicBorrador::scopeAutorizadaYSeleccionable()` para el
     * primero, "SIC local") — usado por requisiciones que NUNCA tuvieron SIC
     * local: aprobada en EBS, sin SIC local vinculada, y sin asignar todavía
     * a ninguna línea de Solicitud a Proveedor por el camino directo. Filtra
     * solo lo expresable en SQL — el criterio de "su artículo mapeado es de
     * categoría va a Compra" se resuelve aparte en PHP, ver
     * `articuloMapeadoDeCompra()`, porque depende de la PRIMERA línea (por
     * `line_number`) de cada requisición, no expresable en una sola cláusula
     * SQL portátil.
     *
     * `$exceptSolicitudId` reabre el hueco para el modo edición de
     * `SolicitudesProveedor`, mismo criterio que
     * `SolicitudSicBorrador::scopeAutorizadaYSeleccionable()`.
     */
    public function scopeElegibleDirectoSinSic($query, ?int $exceptSolicitudId = null)
    {
        return $query->where('status', 'APPROVED')
            ->whereDoesntHave('solicitudSicBorrador')
            ->where(function ($q) use ($exceptSolicitudId) {
                $q->whereDoesntHave('solicitudProveedorLineas')
                    ->when($exceptSolicitudId, fn ($q2) => $q2->orWhereHas(
                        'solicitudProveedorLineas',
                        fn ($q3) => $q3->where('solicitud_id', $exceptSolicitudId)
                    ));
            });
    }

    /**
     * Resuelve si el `item_id` de la PRIMERA línea (por `line_number`) está
     * mapeado, vía `EbsArticulo`, a un `ArticuloSolicitud` de categoría "va a
     * Compra" — mismo criterio que antes usaba
     * `EbsRequisitionSyncService::autoCrearSolicitudDesdeArticuloMapeado()`
     * (eliminado, ver docs/gestionti-progreso.md) para decidir si crear una
     * SIC esqueleto; ahora solo se usa para elegibilidad del camino directo,
     * sin crear nada. Requiere `lines` cargadas para evitar N+1 en listas —
     * `loadMissing()` como respaldo si no se cargaron antes.
     */
    public function articuloMapeadoDeCompra(): ?ArticuloSolicitud
    {
        $this->loadMissing('lines');

        $primeraLinea = $this->lines->sortBy('line_number')->first();

        if (! $primeraLinea || ! $primeraLinea->item_id) {
            return null;
        }

        $ebsArticulo = EbsArticulo::where('ebs_item_id', $primeraLinea->item_id)->first();

        if (! $ebsArticulo || ! $ebsArticulo->articulo_id) {
            return null;
        }

        $articulo = $ebsArticulo->articulo()->with('categoria')->first();

        return $articulo && $articulo->categoria?->es_compra ? $articulo : null;
    }

    /**
     * Búsqueda general usada por la pantalla "SIC en EBS" (input "Buscar
     * por código...") y su export a Excel — deben producir exactamente el
     * mismo conjunto de resultados, así que ambos llaman este scope en vez
     * de repetir la condición por su cuenta. Busca por coincidencia parcial
     * en el código, la descripción, quién creó/autorizó la requisición y el
     * contenido de sus notas.
     */
    public function scopeMatchesSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('created_by_user', 'like', "%{$term}%")
                ->orWhere('created_by_description', 'like', "%{$term}%")
                ->orWhere('approver_user', 'like', "%{$term}%")
                ->orWhere('approver_name', 'like', "%{$term}%")
                ->orWhereHas('notes', fn ($q2) => $q2->where('valor', 'like', "%{$term}%"));
        });
    }
}
